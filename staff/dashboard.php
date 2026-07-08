<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/product-expiry.php';

// Guard for staff users only
if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: login.php');
    exit;
}

disableExpiredProducts($conn);

$errors = [];
$success = '';
$message = '';
$searchQuery = trim($_GET['search'] ?? '');
$selectedCategory = trim($_GET['category'] ?? 'All');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = '1=1';
$params = [];
$types = '';
if ($searchQuery !== '') {
    $where .= ' AND (productName LIKE ? OR category LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($selectedCategory !== '' && $selectedCategory !== 'All') {
    $where .= ' AND category = ?';
    $params[] = $selectedCategory;
    $types .= 's';
}

$countSql = "SELECT COUNT(*) AS total FROM products WHERE $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params) && $countStmt) {
    $countStmt->bind_param($types, ...$params);
}
$totalSKUs = 0;
if ($countStmt) {
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalSKUs = (int)($countResult->fetch_assoc()['total'] ?? 0);
    $countStmt->close();
}
$totalPages = max(1, (int)ceil($totalSKUs / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = "SELECT productID,
               productName,
               category,
               complianceStatus,
               status,
               imagePath,
               expiryDate,
               stockQuantity,
               COALESCE(productType, 'Both') AS productType
        FROM products
        WHERE $where
        ORDER BY
            CASE
                WHEN expiryDate IS NULL THEN 2
                WHEN expiryDate < CURDATE() THEN 0
                ELSE 1
            END ASC,
            CASE
                WHEN expiryDate >= CURDATE() THEN expiryDate
                ELSE NULL
            END ASC,
            CASE
                WHEN expiryDate < CURDATE() THEN expiryDate
                ELSE NULL
            END DESC,
            productName ASC
        LIMIT ? OFFSET ?";

$categoryResult = $conn->query("SELECT DISTINCT category FROM products ORDER BY category ASC");
$categories = $categoryResult ? $categoryResult->fetch_all(MYSQLI_ASSOC) : [];

$stmt = $conn->prepare($sql);
if ($stmt) {
    $queryParams = array_merge($params, [$perPage, $offset]);
    $queryTypes = $types . 'ii';
    $stmt->bind_param($queryTypes, ...$queryParams);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $filteredInventory = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $filteredInventory = [];
    $errors[] = 'Unable to load inventory from the database.';
}

$pendingCompliance = count(array_filter($filteredInventory, fn($item) => $item['complianceStatus'] !== 'Approved'));
$lowStockItems = count(array_filter($filteredInventory, fn($item) => $item['stockQuantity'] < 100));

$pageBaseParams = [];
if ($searchQuery !== '') {
    $pageBaseParams['search'] = $searchQuery;
}
if ($selectedCategory !== '' && $selectedCategory !== 'All') {
    $pageBaseParams['category'] = $selectedCategory;
}

function isExpiringSoon($dateStr) {
    $expiryDate = new DateTime($dateStr);
    $today = new DateTime('today');
    $diff = $today->diff($expiryDate);
    return $expiryDate > $today && $diff->days <= 30;
}

function isExpired($dateStr) {
    return new DateTime($dateStr) < new DateTime('today');
}

function formatDate($dateStr) {
    return (new DateTime($dateStr))->format('M d, Y');
}

function resolveProductImageUrl(string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (strpos($path, '://') !== false || str_starts_with($path, '/')) {
        return $path;
    }
    $rootPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/../admin/' . ltrim($path, '/');
    return preg_replace('#/+#', '/', $rootPath);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Staff Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/staff-dashboard.css?v=2">
</head>
<body>
<div class="staff-layout">
    <aside class="staff-sidebar">
        <div class="sidebar-header">
            <button type="button" id="sidebarToggle" class="sidebar-toggle" aria-pressed="false" aria-label="Toggle sidebar">
                <span class="toggle-icon">☰</span>
            </button>
            <div class="logo-circle">
                <img src="../logo-transparent.png" alt="Essen Pharmacy" class="logo-image">
            </div>
            <div class="sidebar-brand">
                <div class="brand-title">Essen Pharmacy</div>
                <div class="brand-subtitle">Staff Portal</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="add-product.php" class="nav-item">
                <span class="nav-icon">➕</span>
                <span class="nav-label">Add Product</span>
            </a>
            <a href="products.php" class="nav-item">
                <span class="nav-icon">💊</span>
                <span class="nav-label">Products</span>
            </a>
            <a href="../pos/dashboard.php" class="nav-item">
                <span class="nav-icon">🧾</span>
                <span class="nav-label">POS</span>
            </a>
            <a href="profile.php" class="nav-item">
                <span class="nav-icon">👤</span>
                <span class="nav-label">Profile</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-item logout-item">
                <span class="nav-icon">↩</span>
                <span class="nav-label">Sign Out</span>
            </a>
        </div>
    </aside>
    <main class="main-content">
        <div class="staff-page">
    <header class="page-header">
        <div>
            <!-- <nav class="breadcrumb">
                <span>Dashboard</span>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-current">Dashboard</span>
            </nav> -->
            <h1>Dashboard</h1>
        </div>

        <div class="toolbar">
            <form method="get" action="dashboard.php" class="toolbar-filter-form">
                <input type="hidden" name="page" value="1">
                <label class="search-field">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 3A7.5 7.5 0 1 1 3 10.5 7.509 7.509 0 0 1 10.5 3zm8.432 15.695-3.847-3.846a8.164 8.164 0 0 0 1.497-4.662A8.163 8.163 0 0 0 8.152 1.018 8.163 8.163 0 0 0 .5 9.611a8.163 8.163 0 0 0 8.152 8.592 8.161 8.161 0 0 0 4.661-1.498l3.846 3.846a.75.75 0 0 0 1.06-1.06z"></path></svg>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search medications...">
                </label>
                <label class="filter-field">
                    <span class="filter-label">Category</span>
                    <select name="category" class="filter-select" onchange="this.form.submit()">
                        <option value="All"<?php echo $selectedCategory === 'All' ? ' selected' : ''; ?>>All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['category']); ?>"<?php echo $selectedCategory === $category['category'] ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="metric-cards">
        <article class="metric-card">
            <div class="metric-icon">📦</div>
            <div>
                <p class="metric-label">Number of Products</p>
                <p class="metric-value"><?php echo $totalSKUs; ?></p>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-icon">⚠️</div>
            <div>
                <p class="metric-label">Pending Compliance</p>
                <p class="metric-value"><?php echo $pendingCompliance; ?></p>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-icon">🚨</div>
            <div>
                <p class="metric-label">Low Stock Alerts</p>
                <p class="metric-value"><?php echo $lowStockItems; ?></p>
            </div>
        </article>
    </section>

    <section class="inventory-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="no-col">No</th>
                        <th>Product Name</th>
                        <th>Stock</th>
                        <th>Compliance</th>
                        <th>Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filteredInventory as $index => $item): ?>
                        <tr>
                            <td class="no-col"><?php echo $offset + $index + 1; ?></td>
                            <td class="product-cell">
                                <?php $productImageUrl = resolveProductImageUrl($item['imagePath'] ?? ''); ?>
                                <?php if ($productImageUrl !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($productImageUrl); ?>" alt="<?php echo htmlspecialchars($item['productName']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-badge">📦</div>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($item['productName']); ?></span>
                            </td>
                            <td>
                                <span class="stock-display"><?php echo (int)$item['stockQuantity']; ?></span>
                            </td>
                            <td>
                                <span class="compliance-toggle <?php echo ($item['complianceStatus'] === 'Approved') ? 'compliant' : 'pending'; ?>">
                                    <span><?php echo ($item['complianceStatus'] === 'Approved') ? 'Verified' : 'Pending'; ?></span>
                                </span>
                            </td>
                            <td class="expiry-cell <?php echo isExpired($item['expiryDate']) ? 'expired' : (isExpiringSoon($item['expiryDate']) ? 'expiring-soon' : ''); ?>">
                                <?php echo formatDate($item['expiryDate']); ?>
                                <?php if (isExpiringSoon($item['expiryDate']) && !isExpired($item['expiryDate'])): ?>
                                    <span class="tag">Expiring Soon</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($filteredInventory)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <p>No products found.</p>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div class="dashboard-pagination">
                    <?php
                    $prevParams = array_merge($pageBaseParams, ['page' => max(1, $page - 1)]);
                    $nextParams = array_merge($pageBaseParams, ['page' => min($totalPages, $page + 1)]);
                    ?>
                    <a class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($prevParams)); ?>">Previous</a>
                    <div class="page-number-group">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php $pageParams = array_merge($pageBaseParams, ['page' => $i]); ?>
                            <a class="page-number <?php echo $i === $page ? 'active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <a class="page-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($nextParams)); ?>">Next</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
    </main>
</div>
<script>
    const sidebarToggle = document.getElementById('sidebarToggle');
    const staffLayout = document.querySelector('.staff-layout');

    if (sidebarToggle && staffLayout) {
        sidebarToggle.addEventListener('click', () => {
            const collapsed = staffLayout.classList.toggle('collapsed');
            sidebarToggle.setAttribute('aria-pressed', collapsed.toString());
        });
    }
</script>
</body>
</html>
