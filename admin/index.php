<?php
session_start();
require_once __DIR__ . '/../db.php';

// Simple admin guard
if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Placeholder metrics (later can be pulled from real tables)
$totalRevenue      = 48294.00;
$ordersToday       = 142;
$activeCustomers   = 2847;
$conversionRate    = 3.24;
$revenueChange     = '+12.5%';
$ordersChange      = '+8.2%';
$customersChange   = '+4.1%';
$conversionChange  = '+0.8%';

// Dummy weekly sales data
$weeklySales = [
    'Mon' => 120,
    'Tue' => 180,
    'Wed' => 110,
    'Thu' => 210,
    'Fri' => 190,
    'Sat' => 220,
    'Sun' => 150,
];

// Dummy recent orders
$recentOrders = [
    ['name' => 'Sarah Johnson', 'code' => 'ORD-001', 'time' => '2 min ago',  'amount' => 89.99, 'status' => 'Paid'],
    ['name' => 'Michael Chen',  'code' => 'ORD-002', 'time' => '15 min ago', 'amount' => 156.50, 'status' => 'Processing'],
    ['name' => 'Emily Davis',   'code' => 'ORD-003', 'time' => '32 min ago', 'amount' => 42.00,  'status' => 'Paid'],
    ['name' => 'James Wilson',  'code' => 'ORD-004', 'time' => '1 hour ago', 'amount' => 234.75, 'status' => 'Shipped'],
    ['name' => 'Lisa Anderson', 'code' => 'ORD-005', 'time' => '2 hours ago','amount' => 67.25,  'status' => 'Paid'],
];

// Dummy top products
$topProducts = [
    ['rank' => 1, 'name' => 'Amoxicillin 500mg', 'sold' => 245, 'revenue' => 3182],
    ['rank' => 2, 'name' => 'Vitamin D3 1000IU', 'sold' => 189, 'revenue' => 3023],
    ['rank' => 3, 'name' => 'Ibuprofen 400mg',   'sold' => 156, 'revenue' => 1324],
    ['rank' => 4, 'name' => 'Paracetamol 500mg', 'sold' => 421, 'revenue' => 2520],
];

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
            <a href="#" class="nav-item active">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="profile.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Analytics</span>
            </a>
            <a href="profile.php" class="nav-item">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Staff Management</span>
            </a>
            <a href="profile.php" class="nav-item">
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
                    <div class="metric-value">$<?php echo number_format($totalRevenue, 0); ?></div>
                    <div class="metric-label">Total Revenue</div>
                </div>
                <div class="metric-badge positive"><?php echo htmlspecialchars($revenueChange); ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">🛒</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($ordersToday); ?></div>
                    <div class="metric-label">Orders Today</div>
                </div>
                <div class="metric-badge positive"><?php echo htmlspecialchars($ordersChange); ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">👥</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($activeCustomers); ?></div>
                    <div class="metric-label">Active Customers</div>
                </div>
                <div class="metric-badge positive"><?php echo htmlspecialchars($customersChange); ?></div>
            </div>

            <div class="metric-card">
                <div class="metric-icon">📈</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($conversionRate, 2); ?>%</div>
                    <div class="metric-label">Conversion Rate</div>
                </div>
                <div class="metric-badge positive"><?php echo htmlspecialchars($conversionChange); ?></div>
            </div>
        </section>

        <section class="main-row">
            <div class="card large">
                <div class="card-header">
                    <h2>Weekly Sales</h2>
                </div>
                <div class="chart-bars">
                    <?php foreach ($weeklySales as $day => $value): ?>
                        <div class="chart-bar">
                            <div class="bar" style="height: <?php echo (int)$value; ?>px;"></div>
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
                    <div class="alert-pill warning">
                        <strong>Low stock alert:</strong> Cetirizine 10mg (12 units remaining)
                    </div>
                    <div class="alert-pill warning">
                        <strong>Expiring soon:</strong> Omeprazole 20mg (Feb 25, 2026)
                    </div>
                    <div class="alert-pill info">
                        2 products pending compliance verification
                    </div>
                </div>
            </div>
        </section>

        <section class="bottom-row">
            <div class="card">
                <div class="card-header">
                    <h2>Recent Orders</h2>
                </div>
                <div class="table-list">
                    <?php foreach ($recentOrders as $order): ?>
                        <div class="table-row">
                            <div class="table-main">
                                <div class="row-title"><?php echo htmlspecialchars($order['name']); ?></div>
                                <div class="row-subtitle">
                                    <?php echo htmlspecialchars($order['code']); ?> · <?php echo htmlspecialchars($order['time']); ?>
                                </div>
                            </div>
                            <div class="row-meta">
                                <div class="row-amount">$<?php echo number_format($order['amount'], 2); ?></div>
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
                    <?php foreach ($topProducts as $product): ?>
                        <div class="table-row">
                            <div class="table-rank"><?php echo (int)$product['rank']; ?></div>
                            <div class="table-main">
                                <div class="row-title"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="row-subtitle">
                                    <?php echo (int)$product['sold']; ?> units sold
                                </div>
                            </div>
                            <div class="row-meta">
                                <div class="row-amount">$<?php echo number_format($product['revenue'], 0); ?></div>
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

