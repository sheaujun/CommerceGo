<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/app-config.php';
require_once __DIR__ . '/../includes/product-expiry.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login.php');
    exit;
}

disableExpiredProducts($conn);

$userID = (int)$_SESSION['userID'];
$cartCount = 0;
$message = '';
$orderID = null;

if (isset($_GET['session_id'])) {
    try {
        $stripeSecretKey = commercego_stripe_secret_key();
        if ($stripeSecretKey === '') {
            throw new Exception('Stripe secret key is not configured.');
        }

        \Stripe\Stripe::setApiKey($stripeSecretKey);

        $sessionID = $_GET['session_id'];
        $session = \Stripe\Checkout\Session::retrieve($sessionID);

        if ($session->payment_status === 'paid') {
            $paymentMethod = 'Stripe';
            if (!empty($session->payment_method_types) && is_array($session->payment_method_types)) {
                $paymentMethod = 'Stripe - ' . strtoupper((string) $session->payment_method_types[0]);
            }

            $orderID = processOrder($userID, $conn, $paymentMethod);
            $message = 'Payment successful! Your order has been placed.';
        } else {
            $message = 'Payment was not completed.';
        }
    } catch (Exception $e) {
        $message = 'Error processing payment: ' . $e->getMessage();
    }
} else {
    try {
        $orderID = processOrder($userID, $conn, 'Direct Payment');
        $message = 'Payment successful! Your order has been placed.';
    } catch (Exception $e) {
        $message = 'Error processing order: ' . $e->getMessage();
    }
}

function processOrder(int $userID, mysqli $conn, string $paymentMethod): int
{
    $cartItems = getCartItems($userID, $conn);

    if (empty($cartItems)) {
        throw new Exception('Cart is empty or this order has already been processed.');
    }

    $customerID = resolveCustomerID($userID, $conn);
    if ($customerID === null) {
        throw new Exception('Customer profile could not be created.');
    }

    $total = 0.0;
    $itemCount = 0;
    foreach ($cartItems as $item) {
        $quantity = (int) $item['quantity'];
        $price = (float) $item['price'];
        $availableStock = (int) ($item['availableStock'] ?? 0);

        if ($quantity > $availableStock) {
            throw new Exception('One or more items no longer have enough stock.');
        }

        $total += $price * $quantity;
        $itemCount += $quantity;
    }

    $orderID = 0;

    try {
        $conn->begin_transaction();

        $orderCode = generateOrderCode($conn);
        $status = 'Pending';
        $stmt = $conn->prepare(
            'INSERT INTO customer_orders (order_code, customer_id, order_date, total, items, status, paymentMethod)
             VALUES (?, ?, CURDATE(), ?, ?, ?, ?)'
        );
        if (!$stmt) {
            throw new Exception('Unable to prepare order insert statement.');
        }

        $stmt->bind_param('sidiss', $orderCode, $customerID, $total, $itemCount, $status, $paymentMethod);
        if (!$stmt->execute()) {
            throw new Exception('Unable to save the order.');
        }
        $orderID = (int) $conn->insert_id;
        $stmt->close();

        $itemStmt = $conn->prepare(
            'INSERT INTO order_items (order_id, product_name, quantity, unit_price) VALUES (?, ?, ?, ?)'
        );
        $stockStmt = $conn->prepare(
            'UPDATE products
             SET physicalStock = GREATEST(stockQuantity - ?, 0),
                 onlineStock = GREATEST(stockQuantity - ?, 0),
                 stockQuantity = stockQuantity - ?
             WHERE productID = ?
               AND stockQuantity >= ?'
        );

        if (!$itemStmt || !$stockStmt) {
            throw new Exception('Unable to prepare order item statements.');
        }

        foreach ($cartItems as $item) {
            $productName = (string) $item['productName'];
            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $item['price'];
            $productID = (int) $item['productID'];

            $itemStmt->bind_param('isid', $orderID, $productName, $quantity, $unitPrice);
            if (!$itemStmt->execute()) {
                throw new Exception('Unable to save order items.');
            }

            $stockStmt->bind_param('iiiii', $quantity, $quantity, $quantity, $productID, $quantity);
            if (!$stockStmt->execute()) {
                throw new Exception('Unable to update product stock.');
            }
            if ($stockStmt->affected_rows < 1) {
                throw new Exception('Online stock is no longer sufficient for this order.');
            }
        }

        $itemStmt->close();
        $stockStmt->close();

        $clearStmt = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
        if (!$clearStmt) {
            throw new Exception('Unable to prepare cart cleanup.');
        }

        $clearStmt->bind_param('i', $userID);
        if (!$clearStmt->execute()) {
            throw new Exception('Unable to clear the cart.');
        }
        $clearStmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return $orderID;
}

function getCartItems(int $userID, mysqli $conn): array
{
    $sql = "SELECT c.quantity, p.productID, p.productName, p.price,
                   p.stockQuantity AS availableStock
            FROM cart c
            JOIN products p ON c.product_id = p.productID
            WHERE c.user_id = ? AND p.status = 'Active' AND p.complianceStatus = 'Approved' AND (p.expiryDate IS NULL OR p.expiryDate >= CURDATE())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Unable to load cart items.');
    }

    $stmt->bind_param('i', $userID);
    $stmt->execute();
    $cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $cartItems;
}

function resolveCustomerID(int $userID, mysqli $conn): ?int
{
    $customerStmt = $conn->prepare('SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
    if ($customerStmt) {
        $customerStmt->bind_param('i', $userID);
        $customerStmt->execute();
        $customerResult = $customerStmt->get_result();
        if ($row = $customerResult->fetch_assoc()) {
            $customerStmt->close();
            return (int) $row['customer_id'];
        }
        $customerStmt->close();
    }

    $userStmt = $conn->prepare(
        'SELECT firstName, lastName, email, phoneNo, address FROM users WHERE userID = ? LIMIT 1'
    );
    if (!$userStmt) {
        return null;
    }

    $userStmt->bind_param('i', $userID);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userRow = $userResult->fetch_assoc();
    $userStmt->close();

    if (!$userRow) {
        return null;
    }

    $fullName = trim(($userRow['firstName'] ?? '') . ' ' . ($userRow['lastName'] ?? ''));
    if ($fullName === '') {
        $fullName = $userRow['email'] ?? ('Customer ' . $userID);
    }

    $customerCode = 'CUST' . str_pad((string) $userID, 3, '0', STR_PAD_LEFT);
    $email = (string) ($userRow['email'] ?? '');
    $phone = (string) ($userRow['phoneNo'] ?? '');
    $address = (string) ($userRow['address'] ?? '');
    $status = 'active';

    $insertStmt = $conn->prepare(
        'INSERT INTO customers (user_id, customer_code, name, email, phone, address, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$insertStmt) {
        return null;
    }

    $insertStmt->bind_param('issssss', $userID, $customerCode, $fullName, $email, $phone, $address, $status);
    if (!$insertStmt->execute()) {
        $insertStmt->close();
        return null;
    }

    $customerID = (int) $conn->insert_id;
    $insertStmt->close();

    return $customerID;
}

function generateOrderCode(mysqli $conn): string
{
    do {
        $candidate = 'ORD' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $stmt = $conn->prepare('SELECT order_id FROM customer_orders WHERE order_code = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('Unable to generate an order code.');
        }

        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $candidate;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Essen Pharmacy</title>
    <link rel="stylesheet" href="css/customer-cart.css">
</head>
<body>
<div class="customer-layout">
    <aside class="sidebar">
        <div>
            <div class="brand">
                <img src="../logo.png" alt="Essen Pharmacy" class="brand-inline-logo" width="22" height="22" style="width:22px;height:22px;object-fit:contain;flex:0 0 22px;display:block;">
                <div>
                    <h1>Essen Pharmacy</h1>
                    <p>Customer Portal</p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon">&#128230;</span>
                    <span>Products</span>
                </a>
                <a href="cart.php" class="nav-item">
                    <span class="nav-icon">&#128722;</span>
                    <span>My Cart<?php if ($cartCount > 0): ?> (<?php echo $cartCount; ?>)<?php endif; ?></span>
                </a>
                <a href="order-history.php" class="nav-item">
                    <span class="nav-icon">&#128220;</span>
                    <span>Order History</span>
                </a>
                <a href="find-us.php" class="nav-item">
                    <span class="nav-icon">&#128205;</span>
                    <span>Find Us</span>
                </a>
                <a href="support-chat.php" class="nav-item">
                    <span class="nav-icon">&#128172;</span>
                    <span>Support Chat</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <span class="nav-icon">&#128100;</span>
                    <span>Profile</span>
                </a>
                <a href="../logout.php" class="nav-item">
                    <span class="nav-icon">&#8617;</span>
                    <span>Sign Out</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-footer">
            <p class="support-title">Need help?</p>
            <p class="support-copy">Contact our pharmacist</p>
            <a href="support-chat.php" class="support-link">Support Chat</a>
        </div>
    </aside>

    <main class="main-panel">
        <header class="page-header">
            <div>
                <p class="eyebrow">Payment Status</p>
                <h2><?php echo htmlspecialchars($message); ?></h2>
            </div>
        </header>

        <div class="empty-cart" style="text-align: center;">
            <?php if ($orderID): ?>
                <div class="empty-cart-icon">&#9989;</div>
                <h3>Thank you for your purchase!</h3>
                <p>Your order #<?php echo $orderID; ?> has been confirmed.</p>
                <a href="dashboard.php" class="primary-button">Continue Shopping</a>
            <?php else: ?>
                <div class="empty-cart-icon">&#10060;</div>
                <h3>Payment Failed</h3>
                <p><?php echo htmlspecialchars($message); ?></p>
                <a href="cart.php" class="primary-button">Back to Cart</a>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
