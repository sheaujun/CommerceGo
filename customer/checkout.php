<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/app-config.php';
require_once __DIR__ . '/../includes/product-expiry.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Stripe autoload

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login.php');
    exit;
}

disableExpiredProducts($conn);

$userID = $_SESSION['userID'];

// Get cart items
$sql = "SELECT c.cart_id, c.quantity, p.productID, p.productName, p.price,
               p.stockQuantity AS availableStock
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

if (empty($cartItems)) {
    header('Location: cart.php');
    exit;
}

// Calculate totals
$total = 0;
foreach ($cartItems as $item) {
    $total += $item['price'] * $item['quantity'];
}

$stripeSecretKey = commercego_stripe_secret_key();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($stripeSecretKey === '') {
            throw new Exception('Stripe secret key is not configured.');
        }

        \Stripe\Stripe::setApiKey($stripeSecretKey);

        // Create Stripe Checkout Session
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => array_map(function($item) {
                return [
                    'price_data' => [
                        'currency' => 'myr',
                        'product_data' => [
                            'name' => $item['productName'],
                        ],
                        'unit_amount' => $item['price'] * 100, // Amount in cents
                    ],
                    'quantity' => $item['quantity'],
                ];
            }, $cartItems),
            'mode' => 'payment',
            'success_url' => commercego_app_url('customer/success.php?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => commercego_app_url('customer/cart.php'),
            'metadata' => [
                'user_id' => $userID,
            ],
        ]);

        header('Location: ' . $session->url);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Essen Pharmacy</title>
    <link rel="stylesheet" href="css/customer-cart.css"> <!-- Reuse cart styles -->
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
                <a href="cart.php" class="nav-item">
                    <span class="nav-icon">🛒</span>
                    <span>My Cart</span>
                </a>
                <a href="order-history.php" class="nav-item">
                    <span class="nav-icon">📜</span>
                    <span>Order History</span>
                </a>
                <a href="find-us.php" class="nav-item">
                    <span class="nav-icon">&#128205;</span>
                    <span>Find Us</span>
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
            <a href="support-chat.php" class="support-link">Support Chat</a>
        </div>
    </aside>

    <main class="main-panel">
        <header class="page-header">
            <div>
                <p class="eyebrow">Checkout</p>
                <h2>Complete your purchase</h2>
            </div>
        </header>

        <div class="cart-layout">
            <div class="cart-items-section">
                <h3>Order Summary</h3>
                <div class="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item">
                            <div class="item-details">
                                <h4><?php echo htmlspecialchars($item['productName']); ?></h4>
                                <div class="item-price">RM <?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?></div>
                            </div>
                            <div class="item-total">
                                RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="order-summary">
                <div class="summary-card">
                    <h3>Payment Details</h3>
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

                    <?php if (isset($error)): ?>
                        <div class="error-message" style="color: red; margin: 10px 0;">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <button type="submit" class="checkout-btn">Pay Here</button>
                    </form>

                    <a href="cart.php" class="secondary-button" style="display: block; text-align: center; margin-top: 10px;">Back to Cart</a>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
