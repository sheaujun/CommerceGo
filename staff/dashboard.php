<?php
session_start();
require_once __DIR__ . '/../db.php';

// Guard for staff users only
if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: login.php');
    exit;
}

$initialInventory = [
    [
        'id' => 1,
        'name' => 'Amoxicillin 500mg',
        'batchId' => 'BN-9902',
        'physicalStock' => 245,
        'onlineStock' => 240,
        'compliant' => true,
        'expiryDate' => '2026-03-15',
    ],
    [
        'id' => 2,
        'name' => 'Ibuprofen 400mg',
        'batchId' => 'BN-8871',
        'physicalStock' => 120,
        'onlineStock' => 125,
        'compliant' => true,
        'expiryDate' => '2026-02-10',
    ],
    [
        'id' => 3,
        'name' => 'Paracetamol 500mg',
        'batchId' => 'BN-7723',
        'physicalStock' => 85,
        'onlineStock' => 90,
        'compliant' => false,
        'expiryDate' => '2026-04-22',
    ],
    [
        'id' => 4,
        'name' => 'Vitamin D3 1000IU',
        'batchId' => 'BN-6654',
        'physicalStock' => 312,
        'onlineStock' => 310,
        'compliant' => true,
        'expiryDate' => '2027-01-30',
    ],
    [
        'id' => 5,
        'name' => 'Omeprazole 20mg',
        'batchId' => 'BN-5589',
        'physicalStock' => 45,
        'onlineStock' => 50,
        'compliant' => true,
        'expiryDate' => '2026-02-25',
    ],
    [
        'id' => 6,
        'name' => 'Metformin 850mg',
        'batchId' => 'BN-4421',
        'physicalStock' => 178,
        'onlineStock' => 175,
        'compliant' => true,
        'expiryDate' => '2026-06-18',
    ],
    [
        'id' => 7,
        'name' => 'Cetirizine 10mg',
        'batchId' => 'BN-3310',
        'physicalStock' => 92,
        'onlineStock' => 88,
        'compliant' => false,
        'expiryDate' => '2026-02-05',
    ],
    [
        'id' => 8,
        'name' => 'Lisinopril 10mg',
        'batchId' => 'BN-2298',
        'physicalStock' => 156,
        'onlineStock' => 160,
        'compliant' => true,
        'expiryDate' => '2026-09-12',
    ],
];

if (!isset($_SESSION['staff_inventory'])) {
    $_SESSION['staff_inventory'] = $initialInventory;
}

$inventory = &$_SESSION['staff_inventory'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'sync_all') {
        foreach ($inventory as &$item) {
            $item['onlineStock'] = $item['physicalStock'];
        }
        unset($item);
        $message = 'All inventory has been synchronized successfully.';
    }

    if ($action === 'save_row' && isset($_POST['item_id'])) {
        $itemId = (int) $_POST['item_id'];
        $stockValue = isset($_POST['physical_stock']) ? (int) $_POST['physical_stock'] : 0;

        foreach ($inventory as &$item) {
            if ($item['id'] === $itemId) {
                $item['physicalStock'] = $stockValue;
                break;
            }
        }
        unset($item);
        $message = 'Stock record saved.';
    }

    if ($action === 'toggle_compliance' && isset($_POST['item_id'])) {
        $itemId = (int) $_POST['item_id'];
        foreach ($inventory as &$item) {
            if ($item['id'] === $itemId) {
                $item['compliant'] = !$item['compliant'];
                break;
            }
        }
        unset($item);
        $message = 'Compliance status updated.';
    }
}

$searchQuery = trim($_GET['search'] ?? '');
$filteredInventory = [];
foreach ($inventory as $item) {
    if ($searchQuery === '' || stripos($item['name'], $searchQuery) !== false || stripos($item['batchId'], $searchQuery) !== false) {
        $filteredInventory[] = $item;
    }
}

$totalSKUs = count($inventory);
$pendingCompliance = count(array_filter($inventory, fn($item) => !$item['compliant']));
$lowStockItems = count(array_filter($inventory, fn($item) => $item['physicalStock'] < 100));

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Staff Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/staff-dashboard.css">
</head>
<body>
<div class="staff-layout">
    <aside class="staff-sidebar">
        <div class="sidebar-header">
            <button type="button" id="sidebarToggle" class="sidebar-toggle" aria-pressed="false" aria-label="Toggle sidebar">
                <span class="toggle-icon">☰</span>
            </button>
            <div class="logo-circle">
                <span class="logo-icon">⧉</span>
            </div>
            <div class="sidebar-brand">
                <div class="brand-title">Essen Pharmacy</div>
                <div class="brand-subtitle">Staff Portal</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Inventory Sync</span>
            </a>
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon">📦</span>
                <span class="nav-label">Stock Control</span>
            </a>
            <a href="#" class="nav-item">
                <span class="nav-icon">⚠️</span>
                <span class="nav-label">Compliance</span>
            </a>
            <a href="#" class="nav-item">
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
            <nav class="breadcrumb">
                <span>Dashboard</span>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-current">Inventory Sync</span>
            </nav>
            <h1>Stock Synchronization</h1>
        </div>

        <div class="toolbar">
            <form method="get" action="dashboard.php" class="search-form">
                <label class="search-field">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 3A7.5 7.5 0 1 1 3 10.5 7.509 7.509 0 0 1 10.5 3zm8.432 15.695-3.847-3.846a8.164 8.164 0 0 0 1.497-4.662A8.163 8.163 0 0 0 8.152 1.018 8.163 8.163 0 0 0 .5 9.611a8.163 8.163 0 0 0 8.152 8.592 8.161 8.161 0 0 0 4.661-1.498l3.846 3.846a.75.75 0 0 0 1.06-1.06z"></path></svg>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search medications...">
                </label>
            </form>
            <div class="toolbar-actions">
                <form method="post" action="dashboard.php">
                    <input type="hidden" name="action" value="sync_all">
                    <button type="submit" class="btn-primary">Sync All</button>
                </form>
            </div>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="metric-cards">
        <article class="metric-card">
            <div class="metric-icon">📦</div>
            <div>
                <p class="metric-label">Total Online SKUs</p>
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
                        <th>Product Name</th>
                        <th>Batch ID</th>
                        <th>Physical Stock</th>
                        <th>Online Stock</th>
                        <th>Compliance</th>
                        <th>Expiry Date</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filteredInventory as $item): ?>
                        <tr>
                            <td class="product-cell">
                                <div class="product-badge">📦</div>
                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                            </td>
                            <td>#<?php echo htmlspecialchars($item['batchId']); ?></td>
                            <td>
                                <input type="number" name="physical_stock" value="<?php echo $item['physicalStock']; ?>" min="0" class="stock-input" form="save-form-<?php echo $item['id']; ?>">
                            </td>
                            <td><?php echo htmlspecialchars($item['onlineStock']); ?></td>
                            <td>
                                <button type="submit" class="compliance-toggle <?php echo $item['compliant'] ? 'compliant' : 'pending'; ?>" form="toggle-form-<?php echo $item['id']; ?>">
                                    <span><?php echo $item['compliant'] ? 'Verified' : 'Pending'; ?></span>
                                </button>
                            </td>
                            <td class="expiry-cell <?php echo isExpired($item['expiryDate']) ? 'expired' : (isExpiringSoon($item['expiryDate']) ? 'expiring-soon' : ''); ?>">
                                <?php echo formatDate($item['expiryDate']); ?>
                                <?php if (isExpiringSoon($item['expiryDate']) && !isExpired($item['expiryDate'])): ?>
                                    <span class="tag">Expiring Soon</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-col">
                                <button type="submit" class="btn-icon" form="save-form-<?php echo $item['id']; ?>">Save</button>
                            </td>
                        </tr>
                        <tr class="hidden-row">
                            <td colspan="7">
                                <form id="save-form-<?php echo $item['id']; ?>" method="post" action="dashboard.php" class="hidden-form">
                                    <input type="hidden" name="action" value="save_row">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                </form>
                                <form id="toggle-form-<?php echo $item['id']; ?>" method="post" action="dashboard.php" class="hidden-form">
                                    <input type="hidden" name="action" value="toggle_compliance">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                </form>
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
