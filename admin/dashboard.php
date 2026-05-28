<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/product-expiry.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

disableExpiredProducts($conn);

function customerCount(mysqli $conn, string $whereClause = '1=1'): int
{
    $result = $conn->query("SELECT COUNT(*) AS total FROM customers WHERE {$whereClause}");
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int) ($row['total'] ?? 0);
}

function relativeTime(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }

    return date('d M Y', $timestamp);
}

$today = new DateTimeImmutable('today');
$weekStart = $today->modify('monday this week');
$weekEnd = $weekStart->modify('+6 days');

$todayStr = $today->format('Y-m-d');
$weekStartStr = $weekStart->format('Y-m-d');
$weekEndStr = $weekEnd->format('Y-m-d');

$summarySql = "
    SELECT
        COALESCE(SUM(total), 0) AS total_revenue,
        SUM(CASE WHEN order_date = ? THEN 1 ELSE 0 END) AS orders_today
    FROM customer_orders
    WHERE status <> 'Cancelled'
";
$summaryStmt = $conn->prepare($summarySql);
$summaryStmt->bind_param('s', $todayStr);
$summaryStmt->execute();
$summaryRow = $summaryStmt->get_result()->fetch_assoc() ?: [];
$summaryStmt->close();

$totalRevenue = (float) ($summaryRow['total_revenue'] ?? 0);
$ordersToday = (int) ($summaryRow['orders_today'] ?? 0);
$activeCustomers = customerCount($conn, "status = 'active'");

$pendingApprovals = 0;
$pendingApprovalsResult = $conn->query("SELECT COUNT(*) AS total FROM product_submissions WHERE status = 'Pending'");
if ($pendingApprovalsResult) {
    $pendingApprovals = (int) (($pendingApprovalsResult->fetch_assoc()['total'] ?? 0));
}

$weeklySales = [
    'Mon' => 0.0,
    'Tue' => 0.0,
    'Wed' => 0.0,
    'Thu' => 0.0,
    'Fri' => 0.0,
    'Sat' => 0.0,
    'Sun' => 0.0,
];
$weeklyStmt = $conn->prepare(
    "SELECT DAYNAME(order_date) AS day_name, COALESCE(SUM(total), 0) AS sales
     FROM customer_orders
     WHERE status <> 'Cancelled' AND order_date BETWEEN ? AND ?
     GROUP BY DAYNAME(order_date)"
);
$weeklyStmt->bind_param('ss', $weekStartStr, $weekEndStr);
$weeklyStmt->execute();
$weeklyResult = $weeklyStmt->get_result();
while ($row = $weeklyResult->fetch_assoc()) {
    $shortDay = substr((string) $row['day_name'], 0, 3);
    if (isset($weeklySales[$shortDay])) {
        $weeklySales[$shortDay] = (float) $row['sales'];
    }
}
$weeklyStmt->close();

$recentOrders = [];
$recentOrdersResult = $conn->query(
    "SELECT o.order_code, o.created_at, o.total, o.status, c.name
     FROM customer_orders o
     JOIN customers c ON o.customer_id = c.customer_id
     ORDER BY o.created_at DESC, o.order_id DESC
     LIMIT 5"
);
if ($recentOrdersResult) {
    while ($row = $recentOrdersResult->fetch_assoc()) {
        $recentOrders[] = [
            'name' => $row['name'],
            'code' => $row['order_code'],
            'time' => relativeTime((string) $row['created_at']),
            'amount' => (float) $row['total'],
            'status' => $row['status'] === 'Delivered' ? 'Paid' : $row['status'],
        ];
    }
}

$topProducts = [];
$topProductsResult = $conn->query(
    "SELECT
        oi.product_name AS name,
        SUM(oi.quantity) AS sold,
        SUM(oi.quantity * oi.unit_price) AS revenue
     FROM order_items oi
     JOIN customer_orders o ON oi.order_id = o.order_id
     WHERE o.status <> 'Cancelled'
     GROUP BY oi.product_name
     ORDER BY revenue DESC, sold DESC
     LIMIT 4"
);
if ($topProductsResult) {
    $rank = 1;
    while ($row = $topProductsResult->fetch_assoc()) {
        $topProducts[] = [
            'rank' => $rank++,
            'name' => $row['name'],
            'sold' => (int) $row['sold'],
            'revenue' => (float) $row['revenue'],
        ];
    }
}

$maxWeeklySales = max($weeklySales ?: [0]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Admin Dashboard</title>
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
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <button type="button" id="sidebarToggle" class="sidebar-toggle" aria-pressed="false" aria-label="Toggle sidebar">&#9776;</button>
            <div class="logo-circle">
                <img src="../logo-transparent.png" alt="Essen Pharmacy" class="logo-image">
            </div>
            <div class="sidebar-brand">
                <div class="brand-title">Essen Pharmacy</div>
                <div class="brand-subtitle">Admin Portal</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
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
            <a href="orders.php" class="nav-item">
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
            <div>
                <h1>Dashboard</h1>
                <p>Welcome back! Here is your pharmacy overview.</p>
            </div>
        </header>

        <section class="metric-grid">
            <div class="metric-card">
                <div class="metric-icon">$</div>
                <div class="metric-main">
                    <div class="metric-value">RM <?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="metric-label">Total Revenue</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">&#128722;</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($ordersToday); ?></div>
                    <div class="metric-label">Orders Today</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">&#128101;</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($activeCustomers); ?></div>
                    <div class="metric-label">Active Customers</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">&#9989;</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($pendingApprovals); ?></div>
                    <div class="metric-label">Total Pending Approvals</div>
                </div>
            </div>
        </section>

        <section class="main-row">
            <div class="card large">
                <div class="card-header">
                    <h2>Weekly Sales</h2>
                </div>
                <div class="chart-bars">
                    <?php foreach ($weeklySales as $day => $value): ?>
                        <?php $barHeight = $maxWeeklySales > 0 ? max(24, (int) round(($value / $maxWeeklySales) * 220)) : 24; ?>
                        <div class="chart-bar">
                            <div class="bar" style="height: <?php echo $barHeight; ?>px;"></div>
                            <div class="bar-label"><?php echo htmlspecialchars($day); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card alerts-card">
                <div class="card-header">
                    <h2>Alerts</h2>
                </div>
                <div class="alerts-list">
                    <?php
                    $lowStockResult = $conn->query(
                        "SELECT productName, stockQuantity
                         FROM products
                         WHERE status = 'Active' AND stockQuantity <= 12
                         ORDER BY stockQuantity ASC
                         LIMIT 2"
                    );
                    $hasAlerts = false;
                    if ($lowStockResult && $lowStockResult->num_rows > 0):
                        while ($alert = $lowStockResult->fetch_assoc()):
                            $hasAlerts = true;
                    ?>
                        <div class="alert-pill warning">
                            <strong>Low stock alert:</strong> <?php echo htmlspecialchars($alert['productName']); ?> (<?php echo (int) $alert['stockQuantity']; ?> units remaining)
                        </div>
                    <?php
                        endwhile;
                    endif;

                    $expiringResult = $conn->query(
                        "SELECT productName, expiryDate
                         FROM products
                         WHERE status = 'Active' AND expiryDate IS NOT NULL AND expiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                         ORDER BY expiryDate ASC
                         LIMIT 1"
                    );
                    if ($expiringResult && ($expiring = $expiringResult->fetch_assoc())):
                        $hasAlerts = true;
                    ?>
                        <div class="alert-pill warning">
                            <strong>Expiring soon:</strong> <?php echo htmlspecialchars($expiring['productName']); ?> (<?php echo date('M d, Y', strtotime($expiring['expiryDate'])); ?>)
                        </div>
                    <?php endif; ?>

                    <?php if ($pendingApprovals > 0): ?>
                        <?php $hasAlerts = true; ?>
                        <div class="alert-pill info">
                            <?php echo number_format($pendingApprovals); ?> products pending compliance verification
                        </div>
                    <?php endif; ?>

                    <?php if (!$hasAlerts): ?>
                        <div class="alert-pill info">No urgent alerts right now.</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="bottom-row">
            <div class="card">
                <div class="card-header">
                    <h2>Recent Orders</h2>
                </div>
                <div class="table-list">
                    <?php if (empty($recentOrders)): ?>
                        <div class="table-row">
                            <div class="table-main">
                                <div class="row-title">No orders yet</div>
                                <div class="row-subtitle">Recent customer orders will appear here.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($recentOrders as $order): ?>
                        <div class="table-row">
                            <div class="table-main">
                                <div class="row-title"><?php echo htmlspecialchars($order['name']); ?></div>
                                <div class="row-subtitle">
                                    <?php echo htmlspecialchars($order['code']); ?> · <?php echo htmlspecialchars($order['time']); ?>
                                </div>
                            </div>
                            <div class="row-meta">
                                <div class="row-amount">RM <?php echo number_format($order['amount'], 2); ?></div>
                                <div class="row-status"><?php echo htmlspecialchars($order['status']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Top Products</h2>
                </div>
                <div class="table-list">
                    <?php if (empty($topProducts)): ?>
                        <div class="table-row">
                            <div class="table-main">
                                <div class="row-title">No sales data yet</div>
                                <div class="row-subtitle">Top products will appear after orders are placed.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($topProducts as $product): ?>
                        <div class="table-row">
                            <div class="table-rank"><?php echo (int) $product['rank']; ?></div>
                            <div class="table-main">
                                <div class="row-title"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="row-subtitle">
                                    <?php echo (int) $product['sold']; ?> units sold
                                </div>
                            </div>
                            <div class="row-meta">
                                <div class="row-amount">RM <?php echo number_format($product['revenue'], 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
