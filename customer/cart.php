<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/product-expiry.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login.php');
    exit;
}

disableExpiredProducts($conn);

$userID = $_SESSION['userID'];

// Get cart count
$cartCountResult = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $userID");
$cartCount = $cartCountResult ? $cartCountResult->fetch_assoc()['total'] ?? 0 : 0;

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Handle AJAX request for cart count
if (isset($_GET['action']) && $_GET['action'] === 'get_count') {
    $cartCountResult = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $userID");
    $cartCount = $cartCountResult ? $cartCountResult->fetch_assoc()['total'] ?? 0 : 0;
    echo $cartCount;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productID = (int)($_POST['product_id'] ?? 0);

    if ($action === 'add' && $productID > 0) {
        $quantity = (int)($_POST['quantity'] ?? 1);
        if ($quantity < 1) $quantity = 1;

        // Check if product exists and is available
        $stmt = $conn->prepare("SELECT stockQuantity AS availableStock FROM products WHERE productID = ? AND status = 'Active' AND complianceStatus = 'Approved' AND (expiryDate IS NULL OR expiryDate >= CURDATE())");
        $stmt->bind_param('i', $productID);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();

        if ($product && (int) $product['availableStock'] > 0) {
            // Check if already in cart
            $stmt = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param('ii', $userID, $productID);
            $stmt->execute();
            $result = $stmt->get_result();
            $cartItem = $result->fetch_assoc();
            $stmt->close();

            if ($cartItem) {
                // Update quantity
                $newQuantity = min($cartItem['quantity'] + $quantity, (int) $product['availableStock']);
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
                $stmt->bind_param('ii', $newQuantity, $cartItem['cart_id']);
                $stmt->execute();
                $stmt->close();
                $response = ['success' => true, 'message' => 'Item quantity updated in cart!'];
            } else {
                // Add new item
                $quantity = min($quantity, (int) $product['availableStock']);
                $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->bind_param('iii', $userID, $productID, $quantity);
                $stmt->execute();
                $stmt->close();
                $response = ['success' => true, 'message' => 'Item added to cart!'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Sorry, this item is currently unavailable.'];
        }
    } elseif ($action === 'update' && $productID > 0) {
        $quantity = (int)($_POST['quantity'] ?? 0);
        if ($quantity > 0) {
            // Check stock
            $stmt = $conn->prepare("SELECT stockQuantity AS availableStock FROM products WHERE productID = ? AND status = 'Active' AND complianceStatus = 'Approved' AND (expiryDate IS NULL OR expiryDate >= CURDATE())");
            $stmt->bind_param('i', $productID);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();

            if ($product && $quantity <= (int) $product['availableStock']) {
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param('iii', $quantity, $userID, $productID);
                $stmt->execute();
                $stmt->close();
                $response = ['success' => true, 'message' => 'Cart updated successfully!'];
            } else {
                $response = ['success' => false, 'message' => 'Requested quantity exceeds available stock.'];
            }
        } else {
            // Remove if quantity is 0
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param('ii', $userID, $productID);
            $stmt->execute();
            $stmt->close();
            $response = ['success' => true, 'message' => 'Item removed from cart.'];
        }
    } elseif ($action === 'remove' && $productID > 0) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param('ii', $userID, $productID);
        $stmt->execute();
        $stmt->close();
        $response = ['success' => true, 'message' => 'Item removed from cart.'];
    } elseif ($action === 'clear') {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $stmt->close();
        $response = ['success' => true, 'message' => 'Cart cleared successfully.'];
    }

    // For AJAX requests, return JSON response
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // For regular form submissions, set session message and redirect
    $_SESSION['cart_message'] = $response['message'];
    $redirectUrl = ($action === 'add') ? 'dashboard.php' : 'cart.php';
    header('Location: ' . $redirectUrl);
    exit;
}

// Get cart items
$sql = "SELECT c.cart_id, c.quantity, p.productID, p.productName, p.productDescription, p.price,
               p.stockQuantity AS availableStock, p.imagePath
        FROM cart c
        JOIN products p ON c.product_id = p.productID
        WHERE c.user_id = ? AND p.status = 'Active' AND p.complianceStatus = 'Approved' AND (p.expiryDate IS NULL OR p.expiryDate >= CURDATE())
        ORDER BY c.added_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userID);
$stmt->execute();
$result = $stmt->get_result();
$cartItems = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate totals
$total = 0;
$itemCount = 0;
foreach ($cartItems as $item) {
    $total += $item['price'] * $item['quantity'];
    $itemCount += $item['quantity'];
}

function resolveImageUrl($imagePath) {
    $imagePath = trim($imagePath);
    if ($imagePath === '') {
        return null;
    }
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }

    $imagePath = ltrim($imagePath, '/');

    if (str_starts_with($imagePath, 'admin/')) {
        return '../' . $imagePath;
    }

    if (str_starts_with($imagePath, 'uploads/')) {
        return '../admin/' . $imagePath;
    }

    return '../admin/' . $imagePath;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - Essen Pharmacy</title>
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
                    <span class="nav-icon">📦</span>
                    <span>Products</span>
                </a>
                <a href="cart.php" class="nav-item active">
                    <span class="nav-icon">🛒</span>
                    <span>My Cart<?php if ($cartCount > 0): ?> (<?php echo $cartCount; ?>)<?php endif; ?></span>
                </a>
                <a href="order-history.php" class="nav-item">
                    <span class="nav-icon">📜</span>
                    <span>Order History</span>
                </a>
                <a href="support-chat.php" class="nav-item">
                    <span class="nav-icon">💬</span>
                    <span>Support Chat</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <span class="nav-icon">👤</span>
                    <span>Profile</span>
                </a>
                <a href="../logout.php" class="nav-item">
                    <span class="nav-icon">↩</span>
                    <span>Sign Out</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-footer">
            <p class="support-title">Need help?</p>
            <p class="support-copy">Contact our pharmacist</p>
            <a href="tel:18001234567" class="support-link">1-800-PHARMACY</a>
        </div>
    </aside>

    <main class="main-panel">
        <header class="page-header">
            <div>
                <p class="eyebrow">Shopping Cart</p>
                <h2>Review your items before checkout.</h2>
            </div>
            <button type="button" id="sidebar-toggle" class="sidebar-toggle" aria-label="Toggle sidebar">
                <span class="toggle-icon">☰</span>
            </button>
        </header>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <h3>Your cart is empty</h3>
                <p>Browse our products and add items to your cart.</p>
                <a href="dashboard.php" class="primary-button">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items-section">
                    <div class="cart-header">
                        <h3>Cart Items (<?php echo $itemCount; ?>)</h3>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="clear-cart-btn" onclick="return confirm('Are you sure you want to clear your cart?')">Clear All</button>
                        </form>
                    </div>

                    <div class="cart-items">
                        <?php foreach ($cartItems as $item): ?>
                            <?php $imageUrl = resolveImageUrl($item['imagePath']); ?>
                            <div class="cart-item">
                                <div class="item-image">
                                    <?php if ($imageUrl): ?>
                                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($item['productName']); ?>">
                                    <?php else: ?>
                                        <div class="item-icon">💊</div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['productName']); ?></h4>
                                    <p><?php echo htmlspecialchars($item['productDescription'] ?: 'No description available.'); ?></p>
                                    <div class="item-price">RM <?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                <div class="item-quantity">
                                    <form method="post" class="quantity-form">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?php echo $item['productID']; ?>">
                                        <button type="button" class="qty-btn" onclick="changeQuantity(this, -1)">-</button>
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['availableStock']; ?>" class="qty-input">
                                        <button type="button" class="qty-btn" onclick="changeQuantity(this, 1)">+</button>
                                        <button type="submit" class="update-btn">Update</button>
                                    </form>
                                </div>
                                <div class="item-total">
                                    <div>RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?php echo $item['productID']; ?>">
                                        <button type="submit" class="remove-btn" onclick="return confirm('Remove this item from cart?')">Remove</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="continue-shopping">
                        <a href="dashboard.php" class="secondary-button">Continue Shopping</a>
                    </div>
                </div>

                <div class="order-summary">
                    <div class="summary-card">
                        <h3>Order Summary</h3>
                        <div class="summary-items">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="summary-item">
                                    <span><?php echo htmlspecialchars($item['productName']); ?> x <?php echo $item['quantity']; ?></span>
                                    <span>RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>RM <?php echo number_format($total, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span class="free-shipping">Free</span>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-total">
                            <span>Total</span>
                            <span>RM <?php echo number_format($total, 2); ?></span>
                        </div>
                        <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
document.getElementById('sidebar-toggle').addEventListener('click', function() {
    document.querySelector('.customer-layout').classList.toggle('collapsed');
});

function changeQuantity(btn, delta) {
    const form = btn.closest('.quantity-form');
    const input = form.querySelector('.qty-input');
    const currentValue = parseInt(input.value);
    const newValue = currentValue + delta;
    const max = parseInt(input.max);

    if (newValue >= 1 && newValue <= max) {
        input.value = newValue;
    }
}
</script>
</body>
</html>
