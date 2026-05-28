<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function buildMonthLabelsBetween(DateTime $start, DateTime $end): array
{
    $labels = [];
    $cursor = new DateTime($start->format('Y-m-01'));
    $limit = new DateTime($end->format('Y-m-01'));

    while ($cursor <= $limit) {
        $labels[] = $cursor->format('M');
        $cursor->modify('+1 month');
    }

    return $labels;
}

function resolvePresetDates(string $preset): array
{
    $today = new DateTime('today');
    $start = clone $today;
    $end = clone $today;

    switch ($preset) {
        case 'today':
            break;
        case 'this-week':
            $start = new DateTime('monday this week');
            $end = new DateTime('sunday this week');
            break;
        case 'this-quarter':
            $month = (int) $today->format('n');
            $quarterStartMonth = ((int) floor(($month - 1) / 3) * 3) + 1;
            $start = new DateTime($today->format('Y') . '-' . str_pad((string) $quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01');
            $end = clone $today;
            break;
        case 'this-year':
            $start = new DateTime($today->format('Y-01-01'));
            $end = clone $today;
            break;
        case 'custom':
            break;
        case 'this-month':
        default:
            $start = new DateTime($today->format('Y-m-01'));
            $end = new DateTime($today->format('Y-m-t'));
            $preset = 'this-month';
            break;
    }

    return [$preset, $start, $end];
}

$selectedPreset = trim($_GET['preset'] ?? 'this-month');
[$selectedPreset, $filterStart, $filterEnd] = resolvePresetDates($selectedPreset);

$requestedStart = trim($_GET['start'] ?? '');
$requestedEnd = trim($_GET['end'] ?? '');
if ($selectedPreset === 'custom' && $requestedStart !== '' && $requestedEnd !== '') {
    $customStart = DateTime::createFromFormat('Y-m-d', $requestedStart);
    $customEnd = DateTime::createFromFormat('Y-m-d', $requestedEnd);
    if ($customStart && $customEnd) {
        $filterStart = $customStart;
        $filterEnd = $customEnd;
    }
}

if ($filterStart > $filterEnd) {
    [$filterStart, $filterEnd] = [$filterEnd, $filterStart];
}

$filterStartStr = $filterStart->format('Y-m-d');
$filterEndStr = $filterEnd->format('Y-m-d');

$monthLabels = buildMonthLabelsBetween($filterStart, $filterEnd);
$monthlySales = array_fill(0, max(1, count($monthLabels)), ['month' => '', 'sales' => 0, 'orders' => 0, 'customers' => 0]);
foreach ($monthLabels as $index => $month) {
    $monthlySales[$index]['month'] = $month;
}

$sql = "SELECT DATE_FORMAT(order_date, '%b') AS month, SUM(total) AS sales, COUNT(*) AS orders, COUNT(DISTINCT customer_id) AS customers
        FROM customer_orders
        WHERE order_date BETWEEN ? AND ?
        GROUP BY YEAR(order_date), MONTH(order_date)
        ORDER BY YEAR(order_date), MONTH(order_date)";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $filterStartStr, $filterEndStr);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $salesByMonth = [];
    while ($row = $result->fetch_assoc()) {
        $salesByMonth[$row['month']] = [
            'sales' => (float) $row['sales'],
            'orders' => (int) $row['orders'],
            'customers' => (int) $row['customers'],
        ];
    }
    foreach ($monthlySales as $index => $data) {
        if (isset($salesByMonth[$data['month']])) {
            $monthlySales[$index] = array_merge($monthlySales[$index], $salesByMonth[$data['month']]);
        }
    }
}
$stmt->close();

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
$stmt->bind_param('ss', $filterStartStr, $filterEndStr);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $shortDay = substr($row['day'], 0, 3);
        if (isset($weeklySales[$shortDay])) {
            $weeklySales[$shortDay] = (float) $row['sales'];
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
        JOIN customer_orders o ON oi.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ?
        GROUP BY p.category
        ORDER BY revenue DESC
        LIMIT 4";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $filterStartStr, $filterEndStr);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $totalCategoryRevenue = 0;
    while ($row = $result->fetch_assoc()) {
        $totalCategoryRevenue += (float) $row['revenue'];
        $categories[] = [
            'name' => $row['name'],
            'value' => (float) $row['revenue'],
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
$stmt->close();

$topProducts = [];
$sql = "SELECT oi.product_name AS name, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unit_price) AS revenue
        FROM order_items oi
        JOIN customer_orders o ON oi.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ?
        GROUP BY oi.product_name
        ORDER BY revenue DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $filterStartStr, $filterEndStr);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topProducts[] = [
            'name' => $row['name'],
            'units' => (int) $row['units'],
            'revenue' => (float) $row['revenue'],
        ];
    }
}
$stmt->close();

$totalSales = array_sum(array_column($monthlySales, 'sales'));
$totalOrders = array_sum(array_column($monthlySales, 'orders'));

$activeCustomers = 0;
$stmt = $conn->prepare("SELECT COUNT(DISTINCT customer_id) AS count FROM customer_orders WHERE order_date BETWEEN ? AND ?");
$stmt->bind_param('ss', $filterStartStr, $filterEndStr);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $activeCustomers = (int) ($row['count'] ?? 0);
}
$stmt->close();

$pendingApprovals = 0;
$result = $conn->query("SELECT COUNT(*) AS count FROM product_submissions WHERE status = 'Pending'");
if ($result) {
    $row = $result->fetch_assoc();
    $pendingApprovals = (int) $row['count'];
}
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
                    <p>Detailed insights into your store performance.</p>
                    <!-- <p>Showing data from <?php echo htmlspecialchars($filterStart->format('d/m/Y')); ?> to <?php echo htmlspecialchars($filterEnd->format('d/m/Y')); ?>.</p> -->
                </div>
                <div class="analytics-header-actions">
                    <button id="downloadCsv" class="btn-outline">
                        <span class="btn-icon">&#11015;</span>
                        Export CSV
                    </button>
                    <button id="downloadPdf" class="btn-solid">
                        <span class="btn-icon">&#11015;</span>
                        Export PDF
                    </button>
                </div>
            </div>
        </section>

        <section class="filter-card">
            <form method="get" action="analytics.php" class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label" for="preset-select">Quick Select</label>
                    <select id="preset-select" name="preset" class="filter-select">
                        <option value="this-month"<?php echo $selectedPreset === 'this-month' ? ' selected' : ''; ?>>This Month</option>
                        <option value="this-week"<?php echo $selectedPreset === 'this-week' ? ' selected' : ''; ?>>This Week</option>
                        <option value="today"<?php echo $selectedPreset === 'today' ? ' selected' : ''; ?>>Today</option>
                        <option value="this-quarter"<?php echo $selectedPreset === 'this-quarter' ? ' selected' : ''; ?>>This Quarter</option>
                        <option value="this-year"<?php echo $selectedPreset === 'this-year' ? ' selected' : ''; ?>>This Year</option>
                        <option value="custom"<?php echo $selectedPreset === 'custom' ? ' selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="start-date">Start Date</label>
                    <div class="date-input-wrapper">
                        <span class="calendar-icon">&#128197;</span>
                        <input id="start-date" name="start" type="date" class="filter-input" value="<?php echo htmlspecialchars($filterStartStr); ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="end-date">End Date</label>
                    <div class="date-input-wrapper">
                        <span class="calendar-icon">&#128197;</span>
                        <input id="end-date" name="end" type="date" class="filter-input" value="<?php echo htmlspecialchars($filterEndStr); ?>">
                    </div>
                </div>
                <button id="apply-filter" type="submit" class="filter-apply-btn">Apply Filter</button>
            </form>
        </section>

        <section class="analytics-metric-grid">
            <div class="metric-card">
                <div class="metric-icon metric-icon-primary">$</div>
                <div class="metric-main">
                    <div class="metric-value">RM <?php echo number_format($totalSales, 2); ?></div>
                    <div class="metric-label">Total Revenue</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon metric-icon-blue">&#128722;</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($totalOrders, 0); ?></div>
                    <div class="metric-label">Total Orders</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon metric-icon-green">&#128101;</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($activeCustomers, 0); ?></div>
                    <div class="metric-label">Active Customers</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon metric-icon-purple">&#9989;</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($pendingApprovals, 0); ?></div>
                    <div class="metric-label">Total Pending Approvals</div>
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
                <button class="chart-type-btn active" data-type="bar">&#128202; Bar</button>
                <button class="chart-type-btn" data-type="line">&#128200; Line</button>
            </div>
        </section>

        <section class="analytics-main-row">
            <div class="card analytics-card" data-panel="sales">
                <div class="card-header">
                    <h2 class="analytics-card-title">Monthly Sales</h2>
                    <p class="analytics-card-desc">Revenue performance for the selected date range.</p>
                </div>
                <div class="chart-card-body">
                    <canvas id="monthlySalesChart"></canvas>
                </div>
            </div>

            <div class="card analytics-card" data-panel="sales">
                <div class="card-header">
                    <h2 class="analytics-card-title">Daily Sales</h2>
                    <p class="analytics-card-desc">Sales grouped by weekday for the selected date range.</p>
                </div>
                <div class="chart-card-body">
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>

            <div class="card analytics-card" data-panel="categories" hidden>
                <div class="card-header">
                    <h2 class="analytics-card-title">Sales by Category</h2>
                    <p class="analytics-card-desc">Distribution of sales across product categories.</p>
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
                    <p class="analytics-card-desc">Detailed breakdown by category.</p>
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
                    <p class="analytics-card-desc">Products with highest sales volume in the selected range.</p>
                </div>
                <div class="table-wrapper">
                    <table class="top-products-table">
                        <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product Name</th>
                            <th class="text-right">Units Sold</th>
                            <th class="text-right">Revenue</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $rank = 1; foreach ($topProducts as $product): ?>
                            <tr>
                                <td>#<?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="text-right"><?php echo number_format($product['units'], 0); ?></td>
                                <td class="text-right">RM <?php echo number_format($product['revenue'], 2); ?></td>
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
                end.setDate(new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate());
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
        const printWindow = window.open('', '_blank', 'width=900,height=700');
        if (!printWindow) {
            alert('Please allow pop-ups to export the PDF.');
            return;
        }

        const monthlyRows = monthlySales.map(item => `
            <tr>
                <td>${item.month}</td>
                <td>RM ${Number(item.sales).toFixed(2)}</td>
                <td>${item.orders}</td>
                <td>${item.customers}</td>
            </tr>
        `).join('');

        const reportHtml = `
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Analytics Report</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 32px; color: #0f172a; }
                    h1 { margin: 0 0 8px; font-size: 28px; }
                    p { margin: 0 0 18px; color: #475569; }
                    .summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 24px 0 28px; }
                    .summary-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; }
                    .summary-label { font-size: 13px; color: #64748b; margin-bottom: 6px; }
                    .summary-value { font-size: 20px; font-weight: 700; }
                    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
                    th, td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: left; font-size: 14px; }
                    th { background: #f8fafc; }
                    @media print { body { margin: 20px; } }
                </style>
            </head>
            <body>
                <h1>Analytics Report</h1>
                <p>Generated on ${new Date().toLocaleString()}</p>
                <p>Showing data from <?php echo htmlspecialchars($filterStart->format('d/m/Y')); ?> to <?php echo htmlspecialchars($filterEnd->format('d/m/Y')); ?>.</p>
                <div class="summary">
                    <div class="summary-card">
                        <div class="summary-label">Total Revenue</div>
                        <div class="summary-value">RM <?php echo number_format($totalSales, 2); ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Total Orders</div>
                        <div class="summary-value"><?php echo number_format($totalOrders, 0); ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Active Customers</div>
                        <div class="summary-value"><?php echo number_format($activeCustomers, 0); ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Total Pending Approvals</div>
                        <div class="summary-value"><?php echo number_format($pendingApprovals, 0); ?></div>
                    </div>
                </div>
                <h2>Monthly Sales</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Sales</th>
                            <th>Orders</th>
                            <th>Customers</th>
                        </tr>
                    </thead>
                    <tbody>${monthlyRows}</tbody>
                </table>
            </body>
            </html>
        `;

        printWindow.document.open();
        printWindow.document.write(reportHtml);
        printWindow.document.close();
        printWindow.focus();
        printWindow.onload = function () {
            printWindow.print();
        };
    });
</script>
</body>
</html>
