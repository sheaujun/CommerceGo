<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function h($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = '';
$orderId = (int)($_GET['order_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_order_status') {
    $orderId   = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');
    $allowedStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

    if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
        $errors[] = 'Invalid order or status selected.';
    } else {
        $stmt = $conn->prepare('UPDATE customer_orders SET status = ?, updated_at = NOW() WHERE order_id = ?');
        $stmt->bind_param('si', $newStatus, $orderId);
        if ($stmt->execute()) {
            $success = 'Order status updated successfully.';
        } else {
            $errors[] = 'Unable to update order status. Please try again.';
        }
        $stmt->close();
    }

    if ($orderId > 0 && empty($errors)) {
        header('Location: view-order.php?order_id=' . $orderId . '&updated=1');
        exit;
    }
}

if (isset($_GET['updated'])) {
    $success = 'Order status updated successfully.';
}

$order = null;
$orderItems = [];

if ($orderId > 0) {
    $stmt = $conn->prepare(
        'SELECT o.order_id, o.order_code, o.order_date, o.total, o.items, o.status, o.paymentMethod, o.created_at,
                c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address
         FROM customer_orders o
         JOIN customers c ON o.customer_id = c.customer_id
         WHERE o.order_id = ?'
    );
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();
    }

    if ($order) {
        $stmt = $conn->prepare('SELECT product_name, quantity, unit_price FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC');
        if ($stmt) {
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $itemsResult = $stmt->get_result();
            $orderItems = $itemsResult->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - View Order</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const adminLayout = document.querySelector('.admin-layout');
            if (!sidebarToggle || !adminLayout) return;

            sidebarToggle.addEventListener('click', function () {
                const collapsed = adminLayout.classList.toggle('collapsed');
                sidebarToggle.setAttribute('aria-pressed', collapsed.toString());
            });
        });
    </script>
    <link rel="stylesheet" href="css/admin-orders.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <button type="button" id="sidebarToggle" class="sidebar-toggle" aria-pressed="false" aria-label="Toggle sidebar">☰</button>
            <div class="logo-circle">
                <span class="logo-icon">⧉</span>
            </div>
            <div class="sidebar-brand">
                <div class="brand-title">Essen Pharmacy</div>
                <div class="brand-subtitle">Admin Portal</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="analytics.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Analytics</span>
            </a>
            <a href="staff.php" class="nav-item">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Staff Management</span>
            </a>
            <a href="products.php" class="nav-item">
                <span class="nav-icon">💊</span>
                <span class="nav-label">Products</span>
            </a>
            <a href="approvals.php" class="nav-item">
                <span class="nav-icon">✅</span>
                <span class="nav-label">Approvals</span>
            </a>
            <a href="customers.php" class="nav-item">
                <span class="nav-icon">🧾</span>
                <span class="nav-label">Customers</span>
            </a>
            <a href="orders.php" class="nav-item active">
                <span class="nav-icon">🛒</span>
                <span class="nav-label">Orders</span>
            </a>
            <a href="support-chat.php" class="nav-item">
                <span class="nav-icon">💬</span>
                <span class="nav-label">Support Chat</span>
            </a>
            <a href="profile.php" class="nav-item">
                <span class="nav-icon">👤</span>
                <span class="nav-label">Profile</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-item">
                <span class="nav-icon">↩</span>
                <span class="nav-label">Sign Out</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <div class="page-header-brand">
                <div>
                    <h1>View Order</h1>
                    <p>Review the order in full and update its delivery status.</p>
                </div>
            </div>
        </header>

        <?php if (!$order): ?>
            <div class="orders-alert error">Order not found. Please return to the order list.</div>
            <a href="orders.php" class="orders-button">Back to Orders</a>
        <?php else: ?>
            <?php if (!empty($success)): ?>
                <div class="orders-alert success"><?php echo h($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="orders-alert error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo h($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="order-view-header">
                <div>
                    <p class="order-view-meta">Order ID</p>
                    <h2><?php echo h($order['order_code']); ?></h2>
                    <p class="order-details-subtitle"><?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></p>
                </div>
                <div class="order-view-actions">
                    <a href="orders.php" class="orders-button">Back to Orders</a>
                    <span class="orders-badge orders-badge-<?php echo strtolower($order['status']); ?>"><?php echo h($order['status']); ?></span>
                </div>
            </section>

            <section class="order-details-card">
                <div class="order-details-grid">
                    <div class="order-details-panel">
                        <div class="panel-block">
                            <strong>Customer Information</strong>
                            <p><?php echo h($order['customer_name']); ?></p>
                            <p class="muted"><?php echo h($order['customer_email']); ?></p>
                            <p class="muted"><?php echo h($order['customer_phone']); ?></p>
                            <p class="muted"><?php echo h($order['customer_address']); ?></p>
                        </div>
                        <div class="panel-block">
                            <strong>Payment Method</strong>
                            <p><?php echo h($order['paymentMethod']); ?></p>
                        </div>
                        <div class="panel-block">
                            <strong>Order Summary</strong>
                            <p>Items: <?php echo (int)$order['items']; ?></p>
                            <p>Total: <strong>RM <?php echo number_format((float)$order['total'], 2); ?></strong></p>
                        </div>
                    </div>

                    <div class="order-items-panel">
                        <div class="panel-block">
                            <strong>Order Items</strong>
                        </div>
                        <div class="order-items-list">
                            <?php if (empty($orderItems)): ?>
                                <p class="muted">There are no order items recorded for this order.</p>
                            <?php else: ?>
                                <?php foreach ($orderItems as $item): ?>
                                    <div class="order-item-row">
                                        <div>
                                            <span class="order-item-name"><?php echo h($item['product_name']); ?></span>
                                            <span class="muted">x<?php echo (int)$item['quantity']; ?></span>
                                        </div>
                                        <span>RM <?php echo number_format((float)$item['unit_price'] * (int)$item['quantity'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($order['status'] !== 'Delivered' && $order['status'] !== 'Cancelled'): ?>
                            <form method="post" class="order-actions-form">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                                <?php if ($order['status'] === 'Pending'): ?>
                                    <button type="submit" name="new_status" value="Processing" class="orders-button orders-button-primary">Mark as Processing</button>
                                <?php elseif ($order['status'] === 'Processing'): ?>
                                    <button type="submit" name="new_status" value="Shipped" class="orders-button orders-button-primary">Mark as Shipped</button>
                                <?php elseif ($order['status'] === 'Shipped'): ?>
                                    <button type="submit" name="new_status" value="Delivered" class="orders-button orders-button-primary">Mark as Delivered</button>
                                <?php endif; ?>
                                <button type="submit" name="new_status" value="Cancelled" class="orders-button orders-button-danger">Cancel Order</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
