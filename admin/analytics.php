<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function buildMonthLabels(int $count = 6): array
{
    $labels = [];
    for ($i = $count - 1; $i >= 0; $i--) {
        $date = new DateTime("first day of -{$i} month");
        $labels[] = $date->format('M');
    }
    return $labels;
}

$monthLabels = buildMonthLabels(6);
$monthlySales = array_fill(0, 6, ['month' => '', 'sales' => 0, 'orders' => 0, 'customers' => 0]);
foreach ($monthLabels as $index => $month) {
    $monthlySales[$index]['month'] = $month;
}

$sql = "SELECT DATE_FORMAT(order_date, '%b') AS month, SUM(total) AS sales, COUNT(*) AS orders, COUNT(DISTINCT customer_id) AS customers
        FROM customer_orders
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(order_date), MONTH(order_date)
        ORDER BY YEAR(order_date), MONTH(order_date)";
$result = $conn->query($sql);
if ($result) {
    $salesByMonth = [];
    while ($row = $result->fetch_assoc()) {
        $salesByMonth[$row['month']] = [
            'sales' => (float)$row['sales'],
            'orders' => (int)$row['orders'],
            'customers' => (int)$row['customers'],
        ];
    }
    foreach ($monthlySales as $index => $data) {
        if (isset($salesByMonth[$data['month']])) {
            $monthlySales[$index] = array_merge($monthlySales[$index], $salesByMonth[$data['month']]);
        }
    }
}

$weekStart = new DateTime('monday this week');
$weekEnd   = new DateTime('sunday this week');
$weeklySales = [
    'Mon' => 0,
    'Tue' => 0,
    'Wed' => 0,
    'Thu' => 0,
    'Fri' => 0,
    'Sat' => 0,
    'Sun' => 0,
];
$sql = "SELECT DAYNAME(order_date) AS day, SUM(total) AS sales
        FROM customer_orders
        WHERE order_date BETWEEN ? AND ?
        GROUP BY DAYNAME(order_date)";
$stmt = $conn->prepare($sql);
$weekStartStr = $weekStart->format('Y-m-d');
$weekEndStr = $weekEnd->format('Y-m-d');
$stmt->bind_param('ss', $weekStartStr, $weekEndStr);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $shortDay = substr($row['day'], 0, 3);
        if (isset($weeklySales[$shortDay])) {
            $weeklySales[$shortDay] = (float)$row['sales'];
        }
    }
}
$stmt->close();

$dailySales = [];
foreach ($weeklySales as $day => $sales) {
    $dailySales[] = ['day' => $day, 'sales' => $sales];
}

$categories = [];
$sql = "SELECT p.category AS name, SUM(oi.quantity * oi.unit_price) AS revenue
        FROM order_items oi
        JOIN products p ON oi.product_name = p.productName
        GROUP BY p.category
        ORDER BY revenue DESC
        LIMIT 4";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $totalCategoryRevenue = 0;
    while ($row = $result->fetch_assoc()) {
        $totalCategoryRevenue += (float)$row['revenue'];
        $categories[] = [
            'name' => $row['name'],
            'value' => (float)$row['revenue'],
            'color' => '#0d9488',
        ];
    }
    $colors = ['#0d9488', '#38bdf8', '#a3e635', '#fbbf24'];
    foreach ($categories as $index => $category) {
        $categories[$index]['color'] = $colors[$index] ?? '#6366f1';
        $categories[$index]['percent'] = $totalCategoryRevenue > 0
            ? ($category['value'] / $totalCategoryRevenue) * 100
            : 0;
    }
}

$topProducts = [];
$sql = "SELECT oi.product_name AS name, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unit_price) AS revenue
        FROM order_items oi
        GROUP BY oi.product_name
        ORDER BY revenue DESC
        LIMIT 5";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topProducts[] = [
            'name' => $row['name'],
            'units' => (int)$row['units'],
            'revenue' => (float)$row['revenue'],
        ];
    }
}

$totalTopRevenue = array_sum(array_column($topProducts, 'revenue'));
$totalSales = array_sum(array_column($monthlySales, 'sales'));
$totalOrders = array_sum(array_column($monthlySales, 'orders'));
$activeCustomers = 0;
$result = $conn->query("SELECT COUNT(*) AS count FROM customers WHERE status = 'active'");
if ($result) {
    $row = $result->fetch_assoc();
    $activeCustomers = (int)$row['count'];
}
$avgOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Analytics</title>
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
    <link rel="stylesheet" href="css/admin-analytics.css">
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
        <section class="analytics-header">
            <div class="analytics-header-top">
                <div>
                    <h1>Analytics</h1>
                    <p>Detailed insights into your store performance</p>
                </div>
                <div class="analytics-header-actions">
                    <button id="downloadCsv" class="btn-outline">
                        <span class="btn-icon">⬇</span>
                        Export CSV
                    </button>
                    <button id="downloadPdf" class="btn-solid">
                        <span class="btn-icon">⬇</span>
                        Export PDF
                    </button>
                </div>
            </div>
        </section>

        <section class="filter-card">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label" for="preset-select">Quick Select</label>
                    <select id="preset-select" class="filter-select">
                        <option value="this-month">This Month</option>
                        <option value="this-week">This Week</option>
                        <option value="today">Today</option>
                        <option value="this-quarter">This Quarter</option>
                        <option value="this-year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="start-date">Start Date</label>
                    <div class="date-input-wrapper">
                        <span class="calendar-icon">📅</span>
                        <input id="start-date" type="date" class="filter-input" value="<?php echo date('Y-m-01'); ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="end-date">End Date</label>
                    <div class="date-input-wrapper">
                        <span class="calendar-icon">📅</span>
                        <input id="end-date" type="date" class="filter-input" value="<?php echo date('Y-m-t'); ?>">
                    </div>
                </div>
                <button id="apply-filter" class="filter-apply-btn">Apply Filter</button>
            </div>
        </section>

        <section class="analytics-metric-grid">
            <div class="metric-card">
                <div class="metric-icon metric-icon-primary">$</div>
                <div class="metric-main">
                    <div class="metric-value">RM <?php echo number_format($totalSales, 0); ?></div>
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-change positive">
                        <span>▲</span><span>+12.5% vs last period</span>
                    </div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon metric-icon-blue">🛒</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($totalOrders, 0); ?></div>
                    <div class="metric-label">Total Orders</div>
                    <div class="metric-change positive">
                        <span>▲</span><span>+8.2% vs last period</span>
                    </div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon metric-icon-green">👥</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($activeCustomers, 0); ?></div>
                    <div class="metric-label">Active Customers</div>
                    <div class="metric-change positive">
                        <span>▲</span><span>+15.3% vs last period</span>
                    </div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon metric-icon-purple">Ⓥ</div>
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
            <div class="tabs-list" role="tablist">
                <button class="tab-pill active" data-tab="sales">Sales Overview</button>
                <button class="tab-pill" data-tab="categories">Categories</button>
                <button class="tab-pill" data-tab="products">Top Products</button>
            </div>
            <div class="chart-type-toggle">
                <button class="chart-type-btn active" data-type="bar">📊 Bar</button>
                <button class="chart-type-btn" data-type="line">📈 Line</button>
            </div>
        </section>

        <section class="analytics-main-row">
            <div class="card analytics-card" data-panel="sales">
                <div class="card-header">
                    <h2 class="analytics-card-title">Monthly Sales</h2>
                    <p class="analytics-card-desc">Revenue performance over the past 6 months</p>
                </div>
                <div class="chart-card-body">
                    <canvas id="monthlySalesChart"></canvas>
                </div>
            </div>

            <div class="card analytics-card" data-panel="sales">
                <div class="card-header">
                    <h2 class="analytics-card-title">Daily Sales (This Week)</h2>
                    <p class="analytics-card-desc">Day-by-day sales breakdown</p>
                </div>
                <div class="chart-card-body">
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>

            <div class="card analytics-card" data-panel="categories" hidden>
                <div class="card-header">
                    <h2 class="analytics-card-title">Sales by Category</h2>
                    <p class="analytics-card-desc">Distribution of sales across product categories</p>
                </div>
                <div class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-row">
                            <div class="category-row-header">
                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                                <span><?php echo number_format($category['percent'], 1); ?>%</span>
                            </div>
                            <div class="category-bar-track">
                                <div class="category-bar-fill" style="width: <?php echo number_format($category['percent'], 1); ?>%; background-color: <?php echo htmlspecialchars($category['color']); ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card analytics-card" data-panel="categories" hidden>
                <div class="card-header">
                    <h2 class="analytics-card-title">Category Performance</h2>
                    <p class="analytics-card-desc">Detailed breakdown by category</p>
                </div>
                <div class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-row small-row">
                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                            <span><?php echo number_format($category['percent'], 1); ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card analytics-card" data-panel="products" hidden>
                <div class="card-header">
                    <h2 class="analytics-card-title">Top Selling Products</h2>
                    <p class="analytics-card-desc">Products with highest sales volume</p>
                </div>
                <div class="table-wrapper">
                    <table class="top-products-table">
                        <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product Name</th>
                            <th class="text-right">Units Sold</th>
                            <th class="text-right">Revenue</th>
                            <th class="text-right">Share</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $rank = 1; foreach ($topProducts as $product):
                            $share = $totalTopRevenue > 0 ? ($product['revenue'] / $totalTopRevenue) * 100 : 0;
                            ?>
                            <tr>
                                <td>#<?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="text-right"><?php echo number_format($product['units'], 0); ?></td>
                                <td class="text-right">RM <?php echo number_format($product['revenue'], 0); ?></td>
                                <td class="text-right"><?php echo number_format($share, 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha512-Oe0cCI9qhdAsV3Tw0s+zzlR2Td5W8yx197GbcnTJy5T6hN3eAm/FOm+8tIel/Qx5W2HJqQ8+EIx7aQGxQ7B/Yg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    const monthlySales = <?php echo json_encode($monthlySales); ?>;
    const dailySales = <?php echo json_encode($dailySales); ?>;

    let currentChartType = 'bar';

    const monthlyCtx = document.getElementById('monthlySalesChart').getContext('2d');
    const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');

    const monthlyChart = new Chart(monthlyCtx, {
        type: currentChartType,
        data: {
            labels: monthlySales.map(item => item.month),
            datasets: [{
                label: 'Sales (RM)',
                data: monthlySales.map(item => item.sales),
                backgroundColor: '#0d9488',
                borderColor: '#0d9488',
                borderWidth: 2,
                fill: currentChartType === 'line',
                tension: 0.3,
                borderRadius: 8,
                barThickness: 22,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: context => `RM ${context.parsed.y.toLocaleString()}`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => `RM ${value}` }
                }
            }
        }
    });

    const dailyChart = new Chart(dailyCtx, {
        type: currentChartType,
        data: {
            labels: dailySales.map(item => item.day),
            datasets: [{
                label: 'Sales (RM)',
                data: dailySales.map(item => item.sales),
                backgroundColor: '#38bdf8',
                borderColor: '#38bdf8',
                borderWidth: 2,
                fill: currentChartType === 'line',
                tension: 0.3,
                borderRadius: 8,
                barThickness: 22,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: context => `RM ${context.parsed.y.toLocaleString()}`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => `RM ${value}` }
                }
            }
        }
    });

    function updateCharts(type) {
        monthlyChart.config.type = type;
        monthlyChart.config.data.datasets[0].fill = type === 'line';
        dailyChart.config.type = type;
        dailyChart.config.data.datasets[0].fill = type === 'line';
        monthlyChart.update();
        dailyChart.update();
    }

    document.querySelectorAll('.chart-type-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.chart-type-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            currentChartType = button.dataset.type;
            updateCharts(currentChartType);
        });
    });

    document.querySelectorAll('.tab-pill').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab-pill').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            const target = tab.dataset.tab;
            document.querySelectorAll('.analytics-card').forEach(card => {
                card.hidden = card.dataset.panel !== target;
            });
        });
    });

    document.getElementById('preset-select').addEventListener('change', event => {
        const value = event.target.value;
        const today = new Date();
        let start = new Date(today);
        const end = new Date(today);

        switch (value) {
            case 'today':
                break;
            case 'this-week':
                start.setDate(today.getDate() - (today.getDay() || 7) + 1);
                break;
            case 'this-month':
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            case 'this-quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                start = new Date(today.getFullYear(), quarter * 3, 1);
                break;
            case 'this-year':
                start = new Date(today.getFullYear(), 0, 1);
                break;
            case 'custom':
                document.getElementById('start-date').focus();
                return;
        }

        document.getElementById('start-date').value = start.toISOString().slice(0, 10);
        document.getElementById('end-date').value = end.toISOString().slice(0, 10);
    });

    document.getElementById('apply-filter').addEventListener('click', () => {
        alert('Filter applied. Data is currently based on the dashboard summary and page load values.');
    });

    document.getElementById('downloadCsv').addEventListener('click', () => {
        const rows = [
            ['Month', 'Sales', 'Orders', 'Customers'],
            ...monthlySales.map(item => [item.month, `RM ${item.sales}`, item.orders, item.customers])
        ];
        const csv = rows.map(row => row.join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'analytics-report.csv';
        a.click();
        URL.revokeObjectURL(url);
    });

    document.getElementById('downloadPdf').addEventListener('click', () => {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF();
        pdf.setFontSize(18);
        pdf.text('Analytics Report', 20, 20);
        pdf.setFontSize(12);
        pdf.text(`Total Revenue: RM ${<?php echo number_format($totalSales, 0); ?>}`, 20, 32);
        pdf.text(`Total Orders: ${<?php echo number_format($totalOrders, 0); ?>}`, 20, 40);
        pdf.text(`Active Customers: ${<?php echo number_format($activeCustomers, 0); ?>}`, 20, 48);
        pdf.text(`Avg Order Value: RM ${<?php echo number_format($avgOrderValue, 2); ?>}`, 20, 56);
        pdf.save('analytics-report.pdf');
    });
</script>
</body>
</html>

