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

$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');

$statusMap = [
    'all' => 'All',
    'pending' => 'Pending',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
];

$statusConfig = [
    'Pending' => ['icon' => '⏳', 'class' => 'orders-stat-icon-pending'],
    'Processing' => ['icon' => '📦', 'class' => 'orders-stat-icon-processing'],
    'Shipped' => ['icon' => '🚚', 'class' => 'orders-stat-icon-shipped'],
    'Delivered' => ['icon' => '✅', 'class' => 'orders-stat-icon-delivered'],
    'Cancelled' => ['icon' => '❌', 'class' => 'orders-stat-icon-cancelled'],
];

$where = '1=1';
$params = [];
$types = '';

if ($search !== '') {
    $where .= ' AND (o.order_code LIKE ? OR c.name LIKE ? OR c.email LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($statusFilter !== 'all' && isset($statusMap[$statusFilter])) {
    $where .= ' AND o.status = ?';
    $params[] = $statusMap[$statusFilter];
    $types .= 's';
}

$sql = "SELECT o.order_id, o.order_code, o.customer_id, o.order_date, o.total, o.items, o.status, o.paymentMethod, o.created_at,
               c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address
        FROM customer_orders o
        JOIN customers c ON o.customer_id = c.customer_id
        WHERE $where
        ORDER BY o.order_date DESC, o.order_id DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $ordersResult = $stmt->get_result();
    $orders = $ordersResult->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $orders = [];
}

$statusCounts = [
    'Pending' => 0,
    'Processing' => 0,
    'Shipped' => 0,
    'Delivered' => 0,
    'Cancelled' => 0,
];
$countResult = $conn->query('SELECT status, COUNT(*) AS total FROM customer_orders GROUP BY status');
if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $statusCounts[$row['status']] = (int)$row['total'];
    }
}
$statusCounts['All'] = array_sum($statusCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Orders Management</title>
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
                    <h1>Orders</h1>
                    <p>Manage customer orders, update status, and review order details.</p>
                </div>
            </div>
        </header>

        <section class="orders-stats-grid">
            <?php foreach (['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'] as $status): ?>
                <?php $config = $statusConfig[$status]; ?>
                <article class="orders-stat-card">
                    <div class="orders-stat-card-icon <?php echo $config['class']; ?>"><?php echo $config['icon']; ?></div>
                    <div>
                        <div class="orders-stat-title">
                            <?php echo h($status); ?>
                        </div>
                        <div class="orders-stat-value"><?php echo number_format($statusCounts[$status]); ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="orders-filter-card">
            <form class="orders-filter-row" method="get" action="orders.php">
                <label class="orders-search-group">
                    <span class="orders-label">Search</span>
                    <input
                        type="search"
                        name="q"
                        value="<?php echo h($search); ?>"
                        placeholder="Order code, customer name, email"
                        class="orders-search-input"
                    />
                </label>
                <label class="orders-filter-group">
                    <span class="orders-label">Status</span>
                    <select name="status" class="orders-filter-select" onchange="this.form.submit()">
                        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>All Orders (<?php echo number_format($statusCounts['All']); ?>)</option>
                        <?php foreach ($statusMap as $key => $label): ?>
                            <?php if ($key === 'all') continue; ?>
                            <option value="<?php echo h($key); ?>"<?php echo $statusFilter === $key ? ' selected' : ''; ?>><?php echo h($label); ?> (<?php echo number_format($statusCounts[$label]); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="orders-filter-button">Apply</button>
            </form>
        </section>

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

        <section class="orders-table-card">
            <div class="orders-table-header">
                <h2>Order List</h2>
                <div class="orders-table-meta"><?php echo number_format(count($orders)); ?> order<?php echo count($orders) === 1 ? '' : 's'; ?> found</div>
            </div>

            <div class="orders-table-wrapper">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="orders-empty">No orders match your filters.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="order-code"><?php echo h($order['order_code']); ?></td>
                                <td>
                                    <div class="order-customer">
                                        <span class="order-customer-name"><?php echo h($order['customer_name']); ?></span>
                                        <span class="order-customer-email"><?php echo h($order['customer_email']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo (int)$order['items']; ?></td>
                                <td>RM <?php echo number_format((float)$order['total'], 2); ?></td>
                                <td><?php echo h($order['paymentMethod']); ?></td>
                                <td><span class="orders-badge orders-badge-<?php echo strtolower($order['status']); ?>"><?php echo h($order['status']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                <td class="text-right">
                                    <a class="orders-button" href="view-order.php?order_id=<?php echo (int)$order['order_id']; ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
