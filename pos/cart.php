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

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function resolveImageUrl(?string $imagePath): string
{
    $imagePath = trim((string) $imagePath);
    if ($imagePath === '') {
        return '';
    }
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }
    $imagePath = ltrim($imagePath, '/');
    return str_starts_with($imagePath, 'admin/') ? '../' . $imagePath : '../admin/' . $imagePath;
}

function loadProduct(mysqli $conn, int $productId): ?array
{
    $stmt = $conn->prepare(
        "SELECT productID, productName, category, price, stockQuantity, imagePath, barcode
         FROM products
         WHERE productID = ?
           AND status = 'Active'
           AND complianceStatus = 'Approved'
           AND stockQuantity > 0
           AND (expiryDate IS NULL OR expiryDate >= CURDATE())
         LIMIT 1"
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $product ?: null;
}

function findProductByBarcode(mysqli $conn, string $barcode): ?array
{
    $barcode = trim($barcode);
    if ($barcode === '') {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT productID, productName, productDescription, category, price, stockQuantity, imagePath, barcode,
                status, complianceStatus, expiryDate
         FROM products
         WHERE barcode = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $barcode);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $product ?: null;
}

function productAvailabilityMessage(array $product): string
{
    if (($product['status'] ?? '') !== 'Active') {
        return 'This product is inactive and cannot be added to the sale.';
    }
    if (($product['complianceStatus'] ?? '') !== 'Approved') {
        return 'This product is awaiting approval and cannot be added to the sale.';
    }
    if ((int) ($product['stockQuantity'] ?? 0) <= 0) {
        return 'This product is out of stock.';
    }
    if (!empty($product['expiryDate']) && $product['expiryDate'] < date('Y-m-d')) {
        return 'This product has expired and cannot be sold.';
    }

    return '';
}

function scannedProductPayload(array $product): array
{
    $unavailableReason = productAvailabilityMessage($product);

    return [
        'productID' => (int) $product['productID'],
        'name' => $product['productName'],
        'description' => $product['productDescription'] ?? '',
        'category' => $product['category'],
        'price' => (float) $product['price'],
        'stock' => (int) $product['stockQuantity'],
        'barcode' => $product['barcode'],
        'imageUrl' => resolveImageUrl($product['imagePath'] ?? ''),
        'available' => $unavailableReason === '',
        'availabilityMessage' => $unavailableReason,
    ];
}

function cartItems(mysqli $conn): array
{
    $cart = $_SESSION['pos_cart'] ?? [];
    $items = [];

    foreach ($cart as $productId => $quantity) {
        $product = loadProduct($conn, (int) $productId);
        if (!$product) {
            unset($_SESSION['pos_cart'][$productId]);
            continue;
        }

        $quantity = min((int) $quantity, (int) $product['stockQuantity']);
        if ($quantity <= 0) {
            unset($_SESSION['pos_cart'][$productId]);
            continue;
        }

        $_SESSION['pos_cart'][$productId] = $quantity;
        $lineTotal = $quantity * (float) $product['price'];
        $items[] = [
            'productID' => (int) $product['productID'],
            'name' => $product['productName'],
            'category' => $product['category'],
            'price' => (float) $product['price'],
            'stock' => (int) $product['stockQuantity'],
            'quantity' => $quantity,
            'lineTotal' => $lineTotal,
            'imageUrl' => resolveImageUrl($product['imagePath'] ?? ''),
        ];
    }

    return $items;
}

function cartPayload(mysqli $conn, string $message = ''): array
{
    $items = cartItems($conn);
    $subtotal = array_reduce($items, fn($sum, $item) => $sum + $item['lineTotal'], 0.0);
    return [
        'success' => true,
        'message' => $message,
        'items' => $items,
        'subtotal' => $subtotal,
        'total' => $subtotal,
        'count' => array_reduce($items, fn($sum, $item) => $sum + $item['quantity'], 0),
    ];
}

ensureProductBarcodeSchema($conn);
if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'summary';

try {
    if ($action === 'summary') {
        echo json_encode(cartPayload($conn));
        exit;
    }

    if ($action === 'add_barcode') {
        $product = findProductByBarcode($conn, (string) ($_POST['barcode'] ?? ''));
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'No product found for this barcode.']);
            exit;
        }
        $scannedProduct = scannedProductPayload($product);
        if (!$scannedProduct['available']) {
            echo json_encode([
                'success' => false,
                'message' => $scannedProduct['availabilityMessage'],
                'scannedProduct' => $scannedProduct,
            ]);
            exit;
        }
        $productId = (int) $product['productID'];
        $currentQty = (int) ($_SESSION['pos_cart'][$productId] ?? 0);
        if ($currentQty + 1 > (int) $product['stockQuantity']) {
            echo json_encode([
                'success' => false,
                'message' => 'Insufficient stock for ' . $product['productName'] . '.',
                'scannedProduct' => $scannedProduct,
            ]);
            exit;
        }
        $_SESSION['pos_cart'][$productId] = $currentQty + 1;
        $payload = cartPayload($conn, $product['productName'] . ' added to cart.');
        $payload['scannedProduct'] = $scannedProduct;
        echo json_encode($payload);
        exit;
    }

    if ($action === 'add') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $product = loadProduct($conn, $productId);
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product is unavailable.']);
            exit;
        }
        $currentQty = (int) ($_SESSION['pos_cart'][$productId] ?? 0);
        if ($currentQty + 1 > (int) $product['stockQuantity']) {
            echo json_encode(['success' => false, 'message' => 'Insufficient stock.']);
            exit;
        }
        $_SESSION['pos_cart'][$productId] = $currentQty + 1;
        echo json_encode(cartPayload($conn, $product['productName'] . ' added to cart.'));
        exit;
    }

    if ($action === 'update') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = max(0, (int) ($_POST['quantity'] ?? 0));
        $product = loadProduct($conn, $productId);
        if (!$product) {
            unset($_SESSION['pos_cart'][$productId]);
            echo json_encode(cartPayload($conn, 'Unavailable product removed.'));
            exit;
        }
        if ($quantity === 0) {
            unset($_SESSION['pos_cart'][$productId]);
        } else {
            $_SESSION['pos_cart'][$productId] = min($quantity, (int) $product['stockQuantity']);
        }
        echo json_encode(cartPayload($conn));
        exit;
    }

    if ($action === 'remove') {
        unset($_SESSION['pos_cart'][(int) ($_POST['product_id'] ?? 0)]);
        echo json_encode(cartPayload($conn, 'Item removed.'));
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['pos_cart'] = [];
        echo json_encode(cartPayload($conn, 'Cart cleared.'));
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown POS cart action.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
