<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Static analytics data (placeholder, later can be queried from DB)
$monthlySales = [
    ['month' => 'Jan', 'sales' => 13000],
    ['month' => 'Feb', 'sales' => 15500],
    ['month' => 'Mar', 'sales' => 19200],
    ['month' => 'Apr', 'sales' => 16800],
    ['month' => 'May', 'sales' => 22500],
    ['month' => 'Jun', 'sales' => 25800],
];

$dailySales = [
    ['day' => 'Mon', 'sales' => 3200],
    ['day' => 'Tue', 'sales' => 4100],
    ['day' => 'Wed', 'sales' => 3800],
    ['day' => 'Thu', 'sales' => 4500],
    ['day' => 'Fri', 'sales' => 5200],
    ['day' => 'Sat', 'sales' => 6100],
    ['day' => 'Sun', 'sales' => 4800],
];

$categories = [
    ['name' => 'Medication',    'value' => 45, 'color' => '#0d9488'],
    ['name' => 'Supplements',   'value' => 30, 'color' => '#38bdf8'],
    ['name' => 'Personal Care', 'value' => 15, 'color' => '#a3e635'],
    ['name' => 'Equipment',     'value' => 10, 'color' => '#fbbf24'],
];

$topProducts = [
    ['name' => 'Paracetamol 500mg',      'units' => 1250, 'revenue' => 15625],
    ['name' => 'Vitamin C 1000mg',       'units' => 980,  'revenue' => 44100],
    ['name' => 'Omega-3 Fish Oil',       'units' => 756,  'revenue' => 51408],
    ['name' => 'Multivitamin Complex',   'units' => 645,  'revenue' => 50310],
    ['name' => 'Blood Pressure Monitor', 'units' => 234,  'revenue' => 35100],
];

$totalSales      = array_sum(array_column($monthlySales, 'sales'));
$totalOrders     = 1223;
$activeCustomers = 739;
$avgOrderValue   = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/admin-analytics.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
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
            <a href="analytics.php" class="nav-item active">
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
            <a href="#" class="nav-item">
                <span class="nav-icon">💬</span>
                <span class="nav-label">Support Chat</span>
            </a>
            <a href="#" class="nav-item">
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
        <section class="analytics-header">
            <div class="analytics-header-top">
                <div>
                    <h1>Analytics</h1>
                    <p>Detailed insights into your store performance</p>
                </div>
                <div class="analytics-header-actions">
                    <button class="btn-outline">
                        <span class="btn-icon">⬇</span>
                        Export CSV
                    </button>
                    <button class="btn-solid">
                        <span class="btn-icon">⬇</span>
                        Export PDF
                    </button>
                </div>
            </div>
        </section>

        <section class="filter-card">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">Quick Select</label>
                    <select class="filter-select">
                        <option>This Month</option>
                        <option>This Week</option>
                        <option>Today</option>
                        <option>This Quarter</option>
                        <option>This Year</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="start-date">Start Date</label>
                    <div class="date-input-wrapper">
                        <span class="calendar-icon">📅</span>
                        <input id="start-date" type="date" class="filter-input" value="2024-01-01">
                    </div>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="end-date">End Date</label>
                    <div class="date-input-wrapper">
                        <span class="calendar-icon">📅</span>
                        <input id="end-date" type="date" class="filter-input" value="2024-01-31">
                    </div>
                </div>
                <button class="filter-apply-btn">Apply Filter</button>
            </div>
        </section>

        <section class="analytics-metric-grid">
            <div class="metric-card">
                <div class="metric-icon">RM</div>
                <div class="metric-main">
                    <div class="metric-value">RM <?php echo number_format($totalSales, 0); ?></div>
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-change positive">
                        <span>▲</span><span>+12.5% vs last period</span>
                    </div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">🛒</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($totalOrders, 0); ?></div>
                    <div class="metric-label">Total Orders</div>
                    <div class="metric-change positive">
                        <span>▲</span><span>+8.2% vs last period</span>
                    </div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">👥</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($activeCustomers, 0); ?></div>
                    <div class="metric-label">Active Customers</div>
                    <div class="metric-change positive">
                        <span>▲</span><span>+15.3% vs last period</span>
                    </div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">Ⓥ</div>
                <div class="metric-main">
                    <div class="metric-value">RM <?php echo number_format($avgOrderValue, 2); ?></div>
                    <div class="metric-label">Avg Order Value</div>
                    <div class="metric-change negative">
                        <span>▼</span><span>-2.1% vs last period</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="analytics-tabs-header">
            <div class="tabs-list">
                <div class="tab-pill active">Sales Overview</div>
                <div class="tab-pill">Categories</div>
                <div class="tab-pill">Top Products</div>
            </div>
            <div class="chart-type-toggle">
                <div class="chart-type-btn active">📊 Bar</div>
                <div class="chart-type-btn">📈 Line</div>
            </div>
        </section>

        <section class="analytics-main-row">
            <div class="card">
                <div class="card-header">
                    <h2 class="analytics-card-title">Monthly Sales</h2>
                    <p class="analytics-card-desc">Revenue performance over the past 6 months</p>
                </div>
                <div class="chart-area">
                    <div class="chart-bars teal">
                        <?php foreach ($monthlySales as $point): ?>
                            <div class="chart-bar">
                                <div class="bar" style="height: <?php echo (int)($point['sales'] / 150); ?>px;"></div>
                                <div class="bar-label"><?php echo htmlspecialchars($point['month']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="analytics-card-title">Daily Sales (This Week)</h2>
                    <p class="analytics-card-desc">Day-by-day sales breakdown</p>
                </div>
                <div class="chart-area">
                    <div class="chart-bars blue">
                        <?php foreach ($dailySales as $point): ?>
                            <div class="chart-bar">
                                <div class="bar" style="height: <?php echo (int)($point['sales'] / 40); ?>px;"></div>
                                <div class="bar-label"><?php echo htmlspecialchars($point['day']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="analytics-bottom-row">
            <div class="card">
                <div class="card-header">
                    <h2 class="analytics-card-title">Category Performance</h2>
                    <p class="analytics-card-desc">Distribution of sales across product categories</p>
                </div>
                <div class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-row">
                            <div class="category-row-header">
                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                                <span><?php echo (int)$category['value']; ?>%</span>
                            </div>
                            <div class="category-bar-track">
                                <div class="category-bar-fill"
                                     style="width: <?php echo (int)$category['value']; ?>%; background-color: <?php echo htmlspecialchars($category['color']); ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="analytics-card-title">Top Products</h2>
                    <p class="analytics-card-desc">Products with highest sales volume</p>
                </div>
                <table class="top-products-table">
                    <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Product Name</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $rank = 1; ?>
                    <?php foreach ($topProducts as $product): ?>
                        <tr>
                            <td>#<?php echo $rank++; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo number_format($product['units'], 0); ?></td>
                            <td>RM <?php echo number_format($product['revenue'], 0); ?></td>
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

