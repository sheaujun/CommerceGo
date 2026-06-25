<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/product-expiry.php';
require_once __DIR__ . '/../includes/product-schema.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff access required.']);
    exit;
}

disableExpiredProducts($conn);

function ensurePosSchema(mysqli $conn): void
{
    ensureProductBarcodeSchema($conn);

    $columns = [
        'cashier_user_id' => "ALTER TABLE customer_orders ADD COLUMN cashier_user_id INT(10) UNSIGNED NULL AFTER customer_id",
        'transaction_datetime' => "ALTER TABLE customer_orders ADD COLUMN transaction_datetime DATETIME NULL AFTER order_date",
        'amount_paid' => "ALTER TABLE customer_orders ADD COLUMN amount_paid DECIMAL(12,2) NULL AFTER paymentMethod",
        'change_amount' => "ALTER TABLE customer_orders ADD COLUMN change_amount DECIMAL(12,2) NULL AFTER amount_paid",
        'order_source' => "ALTER TABLE customer_orders ADD COLUMN order_source VARCHAR(30) NOT NULL DEFAULT 'Online' AFTER change_amount",
    ];

    foreach ($columns as $column => $sql) {
        $exists = $conn->query("SHOW COLUMNS FROM customer_orders LIKE '{$column}'");
        if ($exists && $exists->num_rows === 0) {
            $conn->query($sql);
        }
    }
}

function loadPosCart(mysqli $conn): array
{
    $cart = $_SESSION['pos_cart'] ?? [];
    $items = [];

    foreach ($cart as $productId => $quantity) {
        $stmt = $conn->prepare(
            "SELECT productID, productName, price, stockQuantity
             FROM products
             WHERE productID = ?
               AND status = 'Active'
               AND complianceStatus = 'Approved'
               AND (expiryDate IS NULL OR expiryDate >= CURDATE())
             LIMIT 1"
        );
        $productId = (int) $productId;
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new RuntimeException('One or more cart items are no longer available.');
        }

        $quantity = (int) $quantity;
        if ($quantity < 1 || $quantity > (int) $product['stockQuantity']) {
            throw new RuntimeException($product['productName'] . ' does not have enough stock.');
        }

        $items[] = [
            'productID' => (int) $product['productID'],
            'productName' => $product['productName'],
            'price' => (float) $product['price'],
            'quantity' => $quantity,
        ];
    }

    return $items;
}

function walkInCustomerId(mysqli $conn): int
{
    $code = 'POSWALKIN';
    $stmt = $conn->prepare('SELECT customer_id FROM customers WHERE customer_code = ? LIMIT 1');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return (int) $row['customer_id'];
    }

    $name = 'POS Walk-in Customer';
    $email = 'pos.walkin@essen.local';
    $phone = '';
    $address = 'In-store POS transaction';
    $status = 'active';
    $stmt = $conn->prepare(
        'INSERT INTO customers (customer_code, name, email, phone, address, join_date, status)
         VALUES (?, ?, ?, ?, ?, CURDATE(), ?)'
    );
    $stmt->bind_param('ssssss', $code, $name, $email, $phone, $address, $status);
    $stmt->execute();
    $customerId = (int) $conn->insert_id;
    $stmt->close();

    return $customerId;
}

function generateOrderCode(mysqli $conn): string
{
    do {
        $code = 'POS' . date('ymdHis') . random_int(10, 99);
        $stmt = $conn->prepare('SELECT order_id FROM customer_orders WHERE order_code = ? LIMIT 1');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } while ($exists);

    return $code;
}

try {
    ensurePosSchema($conn);

    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $paymentMethod = ($payload['payment_method'] ?? 'Cash') === 'Card' ? 'Card' : 'Cash';
    $amountPaid = max(0, (float) ($payload['amount_paid'] ?? 0));
    $items = loadPosCart($conn);

    if (empty($items)) {
        throw new RuntimeException('The POS cart is empty.');
    }

    $total = 0.0;
    $itemCount = 0;
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
        $itemCount += $item['quantity'];
    }

    if ($paymentMethod === 'Cash' && $amountPaid < $total) {
        throw new RuntimeException('Cash amount is lower than the sale total.');
    }
    if ($paymentMethod === 'Card') {
        $amountPaid = $total;
    }
    $change = max(0, $amountPaid - $total);

    $conn->begin_transaction();

    $customerId = walkInCustomerId($conn);
    $orderCode = generateOrderCode($conn);
    $cashierId = (int) $_SESSION['userID'];
    $status = 'Delivered';
    $source = 'POS';
    $transactionDate = date('Y-m-d H:i:s');
    $paymentLabel = $paymentMethod === 'Card' ? 'POS Card' : 'POS Cash';

    $orderStmt = $conn->prepare(
        'INSERT INTO customer_orders
         (order_code, customer_id, cashier_user_id, order_date, transaction_datetime, total, items, status, paymentMethod, amount_paid, change_amount, order_source)
         VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$orderStmt) {
        throw new RuntimeException('Unable to prepare POS order.');
    }
    $orderStmt->bind_param(
        'siisdissdds',
        $orderCode,
        $customerId,
        $cashierId,
        $transactionDate,
        $total,
        $itemCount,
        $status,
        $paymentLabel,
        $amountPaid,
        $change,
        $source
    );
    $orderStmt->execute();
    $orderId = (int) $conn->insert_id;
    $orderStmt->close();

    $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_name, quantity, unit_price) VALUES (?, ?, ?, ?)');
    $stockStmt = $conn->prepare(
        'UPDATE products
         SET physicalStock = GREATEST(stockQuantity - ?, 0),
             onlineStock = GREATEST(stockQuantity - ?, 0),
             stockQuantity = stockQuantity - ?
         WHERE productID = ?
           AND stockQuantity >= ?'
    );
    if (!$itemStmt || !$stockStmt) {
        throw new RuntimeException('Unable to prepare POS sale lines.');
    }

    foreach ($items as $item) {
        $productName = $item['productName'];
        $quantity = (int) $item['quantity'];
        $unitPrice = (float) $item['price'];
        $productId = (int) $item['productID'];

        $itemStmt->bind_param('isid', $orderId, $productName, $quantity, $unitPrice);
        $itemStmt->execute();

        // Centralized omnichannel inventory synchronization:
        // POS, online checkout, and staff inventory all write to products.stockQuantity.
        // This conditional UPDATE prevents overselling when another channel sells at the same time.
        $stockStmt->bind_param('iiiii', $quantity, $quantity, $quantity, $productId, $quantity);
        $stockStmt->execute();
        if ($stockStmt->affected_rows < 1) {
            throw new RuntimeException($productName . ' stock changed before checkout. Please rescan the item.');
        }
    }

    $itemStmt->close();
    $stockStmt->close();
    $conn->commit();

    $_SESSION['pos_cart'] = [];

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'total' => $total,
        'amount_paid' => $amountPaid,
        'change' => $change,
    ]);
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // no-op; mysqli does not expose transaction state
    }
    @$conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
