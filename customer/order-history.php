<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function resolveCustomerID(int $userId, mysqli $conn): ?int
{
    $customerStmt = $conn->prepare('SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
    if ($customerStmt) {
        $customerStmt->bind_param('i', $userId);
        $customerStmt->execute();
        $customerResult = $customerStmt->get_result();
        if ($row = $customerResult->fetch_assoc()) {
            $customerStmt->close();
            return (int) $row['customer_id'];
        }
        $customerStmt->close();
    }

    $userStmt = $conn->prepare('SELECT firstName, lastName, email, phoneNo, address FROM users WHERE userID = ? LIMIT 1');
    if (!$userStmt) {
        return null;
    }

    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userRow = $userResult->fetch_assoc();
    $userStmt->close();

    if (!$userRow) {
        return null;
    }

    $fullName = trim(($userRow['firstName'] ?? '') . ' ' . ($userRow['lastName'] ?? ''));
    if ($fullName === '') {
        $fullName = $userRow['email'] ?? ('Customer ' . $userId);
    }

    $customerCode = 'CUST' . str_pad((string) $userId, 3, '0', STR_PAD_LEFT);
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

    $insertStmt->bind_param('issssss', $userId, $customerCode, $fullName, $email, $phone, $address, $status);
    if (!$insertStmt->execute()) {
        $insertStmt->close();
        return null;
    }

    $customerId = (int) $conn->insert_id;
    $insertStmt->close();

    return $customerId;
}

function getStatusMeta(string $status): array
{
    $map = [
        'Pending' => ['class' => 'status-pending', 'icon' => '&#128337;'],
        'Processing' => ['class' => 'status-processing', 'icon' => '&#128230;'],
        'Shipped' => ['class' => 'status-shipped', 'icon' => '&#128666;'],
        'Delivered' => ['class' => 'status-delivered', 'icon' => '&#9989;'],
        'Cancelled' => ['class' => 'status-cancelled', 'icon' => '&#10060;'],
    ];

    return $map[$status] ?? ['class' => 'status-pending', 'icon' => '&#128337;'];
}

function getPaymentMeta(string $paymentMethod): array
{
    $normalized = strtolower(trim($paymentMethod));
    if ($normalized === '' || $normalized === 'unknown') {
        return ['label' => 'Pending', 'class' => 'payment-pending'];
    }

    if (str_contains($normalized, 'cash on delivery')) {
        return ['label' => 'Pending', 'class' => 'payment-pending'];
    }

    return ['label' => 'Paid', 'class' => 'payment-paid'];
}

$userId = (int) $_SESSION['userID'];
$cartCount = 0;
$cartStmt = $conn->prepare('SELECT COALESCE(SUM(quantity), 0) AS total FROM cart WHERE user_id = ?');
if ($cartStmt) {
    $cartStmt->bind_param('i', $userId);
    $cartStmt->execute();
    $cartResult = $cartStmt->get_result();
    $cartRow = $cartResult->fetch_assoc();
    $cartCount = (int) ($cartRow['total'] ?? 0);
    $cartStmt->close();
}

$customerId = resolveCustomerID($userId, $conn);
$orders = [];
$selectedOrder = null;
$selectedOrderItems = [];
$selectedOrderId = (int) ($_GET['order_id'] ?? 0);

if ($customerId !== null) {
    $orderStmt = $conn->prepare(
        'SELECT o.order_id, o.order_code, o.order_date, o.total, o.items, o.status, o.paymentMethod, o.created_at,
                c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address
         FROM customer_orders o
         JOIN customers c ON o.customer_id = c.customer_id
         WHERE o.customer_id = ?
         ORDER BY o.created_at DESC, o.order_id DESC'
    );

    if ($orderStmt) {
        $orderStmt->bind_param('i', $customerId);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        $orders = $orderResult->fetch_all(MYSQLI_ASSOC);
        $orderStmt->close();
    }

    if ($selectedOrderId <= 0 && !empty($orders)) {
        $selectedOrderId = (int) $orders[0]['order_id'];
    }

    if ($selectedOrderId > 0) {
        foreach ($orders as $order) {
            if ((int) $order['order_id'] === $selectedOrderId) {
                $selectedOrder = $order;
                break;
            }
        }

        if ($selectedOrder) {
            $itemStmt = $conn->prepare(
                'SELECT product_name, quantity, unit_price
                 FROM order_items
                 WHERE order_id = ?
                 ORDER BY order_item_id ASC'
            );
            if ($itemStmt) {
                $itemStmt->bind_param('i', $selectedOrderId);
                $itemStmt->execute();
                $itemResult = $itemStmt->get_result();
                $selectedOrderItems = $itemResult->fetch_all(MYSQLI_ASSOC);
                $itemStmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Essen Pharmacy - Order History</title>
    <link rel="stylesheet" href="css/customer-dashboard.css">
    <link rel="stylesheet" href="css/customer-order-history.css">
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
                <a href="order-history.php" class="nav-item active" aria-current="page">
                    <span class="nav-icon">&#128220;</span>
                    <span>Order History</span>
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
            <a href="tel:18001234567" class="support-link">1-800-PHARMACY</a>
        </div>
    </aside>

    <main class="main-panel history-panel">
        <header class="page-header">
            <div>
                <p class="eyebrow">Order History</p>
                <h2>Track and review your past orders.</h2>
            </div>
            <button type="button" id="sidebar-toggle" class="sidebar-toggle" aria-label="Toggle sidebar">
                <span class="toggle-icon">&#9776;</span>
            </button>
        </header>

        <?php if (empty($orders)): ?>
            <section class="history-empty">
                <div class="history-empty-icon">&#128221;</div>
                <h3>No orders yet</h3>
                <p>Your order history will appear here after your first purchase.</p>
                <a href="dashboard.php" class="history-primary-button">Start Shopping</a>
            </section>
        <?php else: ?>
            <div class="history-layout">
                <section class="history-list">
                    <?php foreach ($orders as $order): ?>
                        <?php
                            $statusMeta = getStatusMeta($order['status']);
                            $paymentMeta = getPaymentMeta($order['paymentMethod']);
                            $isActive = $selectedOrder && (int) $selectedOrder['order_id'] === (int) $order['order_id'];
                        ?>
                        <a href="order-history.php?order_id=<?php echo (int) $order['order_id']; ?>" class="history-card<?php echo $isActive ? ' active' : ''; ?>">
                            <div class="history-card-top">
                                <div>
                                    <h3><?php echo h($order['order_code']); ?></h3>
                                    <p><?php echo h(date('d M Y', strtotime($order['order_date']))); ?></p>
                                </div>
                                <div class="history-badges">
                                    <span class="history-badge <?php echo h($statusMeta['class']); ?>">
                                        <span class="badge-icon"><?php echo $statusMeta['icon']; ?></span>
                                        <?php echo h($order['status']); ?>
                                    </span>
                                    <span class="history-badge <?php echo h($paymentMeta['class']); ?>">
                                        <?php echo h($paymentMeta['label']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="history-card-meta">
                                <span><strong><?php echo (int) $order['items']; ?></strong> item<?php echo (int) $order['items'] === 1 ? '' : 's'; ?></span>
                                <span class="meta-divider"></span>
                                <span>Total: <strong>RM <?php echo number_format((float) $order['total'], 2); ?></strong></span>
                                <span class="meta-divider"></span>
                                <span><?php echo h($order['paymentMethod']); ?></span>
                            </div>

                            <span class="history-link-copy">View details &#8250;</span>
                        </a>
                    <?php endforeach; ?>
                </section>

                <aside class="history-detail">
                    <?php if (!$selectedOrder): ?>
                        <div class="history-detail-card">
                            <p>Select an order to see its details.</p>
                        </div>
                    <?php else: ?>
                        <?php $selectedStatusMeta = getStatusMeta($selectedOrder['status']); ?>
                        <?php $selectedPaymentMeta = getPaymentMeta($selectedOrder['paymentMethod']); ?>
                        <section class="history-detail-card">
                            <div class="detail-header">
                                <div>
                                    <p class="detail-label">Order ID</p>
                                    <h3><?php echo h($selectedOrder['order_code']); ?></h3>
                                    <p class="detail-date">Placed on <?php echo h(date('d M Y, g:i A', strtotime($selectedOrder['created_at']))); ?></p>
                                </div>
                                <div class="history-badges">
                                    <span class="history-badge <?php echo h($selectedStatusMeta['class']); ?>">
                                        <span class="badge-icon"><?php echo $selectedStatusMeta['icon']; ?></span>
                                        <?php echo h($selectedOrder['status']); ?>
                                    </span>
                                    <span class="history-badge <?php echo h($selectedPaymentMeta['class']); ?>">
                                        <?php echo h($selectedPaymentMeta['label']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="timeline-card">
                                <h4>Order Status</h4>
                                <?php if ($selectedOrder['status'] === 'Cancelled'): ?>
                                    <div class="cancelled-state">
                                        <span class="cancelled-icon">&#10060;</span>
                                        <div>
                                            <strong>Order Cancelled</strong>
                                            <p>This order is no longer being processed.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php
                                            $steps = ['Pending', 'Processing', 'Shipped', 'Delivered'];
                                            $currentIndex = array_search($selectedOrder['status'], $steps, true);
                                            if ($currentIndex === false) {
                                                $currentIndex = 0;
                                            }
                                        ?>
                                        <?php foreach ($steps as $index => $step): ?>
                                            <?php $stepMeta = getStatusMeta($step); ?>
                                            <?php $isComplete = $index <= $currentIndex; ?>
                                            <div class="timeline-step">
                                                <div class="timeline-node<?php echo $isComplete ? ' complete' : ''; ?>">
                                                    <?php echo $stepMeta['icon']; ?>
                                                </div>
                                                <span class="timeline-label<?php echo $isComplete ? ' complete' : ''; ?>"><?php echo h($step); ?></span>
                                            </div>
                                            <?php if ($index < count($steps) - 1): ?>
                                                <div class="timeline-line<?php echo $index < $currentIndex ? ' complete' : ''; ?>"></div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="detail-grid">
                                <div class="detail-section">
                                    <h4>Items</h4>
                                    <div class="detail-box">
                                        <?php foreach ($selectedOrderItems as $item): ?>
                                            <div class="detail-row">
                                                <div>
                                                    <strong><?php echo h($item['product_name']); ?></strong>
                                                    <span>x<?php echo (int) $item['quantity']; ?></span>
                                                </div>
                                                <span>RM <?php echo number_format((float) $item['unit_price'] * (int) $item['quantity'], 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="detail-total">
                                            <span>Total</span>
                                            <strong>RM <?php echo number_format((float) $selectedOrder['total'], 2); ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <h4>Shipping Address</h4>
                                    <div class="detail-box">
                                        <p class="address-name"><?php echo h($selectedOrder['customer_name']); ?></p>
                                        <p><?php echo h($selectedOrder['customer_phone'] ?: '-'); ?></p>
                                        <p><?php echo h($selectedOrder['customer_email'] ?: '-'); ?></p>
                                        <p><?php echo h($selectedOrder['customer_address'] ?: 'No address saved.'); ?></p>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <h4>Payment</h4>
                                    <div class="detail-box payment-box">
                                        <div>
                                            <p class="payment-method"><?php echo h($selectedOrder['paymentMethod']); ?></p>
                                            <p class="payment-note">Payment information recorded with this order.</p>
                                        </div>
                                        <span class="history-badge <?php echo h($selectedPaymentMeta['class']); ?>">
                                            <?php echo h($selectedPaymentMeta['label']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>
                </aside>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
document.getElementById('sidebar-toggle').addEventListener('click', function() {
    document.querySelector('.customer-layout').classList.toggle('collapsed');
});
</script>
</body>
</html>
