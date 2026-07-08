<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/product-import.php';
require_once __DIR__ . '/../includes/product-expiry.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

disableExpiredProducts($conn);
ensureProductBarcodeSchema($conn);

$errors = $_SESSION['flash_errors'] ?? [];
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_errors'], $_SESSION['flash_success']);

function redirectWithImportMessage(string $success = '', array $errors = [], array $query = []): void
{
    $_SESSION['flash_success'] = $success;
    $_SESSION['flash_errors'] = $errors;
    $target = 'products.php';
    if (!empty($query)) {
        $target .= '?' . http_build_query($query);
    }
    header('Location: ' . $target);
    exit;
}

// Handle product spreadsheet import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_products') {
    $importStarted = false;
    try {
        $productsToImport = productsFromUploadedSpreadsheet($_FILES['product_import_file'] ?? []);
        $conn->begin_transaction();
        $importStarted = true;
        $importedCount = insertProducts($conn, $productsToImport);
        $conn->commit();
        $success = $importedCount . ' product' . ($importedCount === 1 ? '' : 's') . ' imported successfully.';
        redirectWithImportMessage($success, [], ['added' => 'recent']);
    } catch (Throwable $e) {
        if ($importStarted) {
            $conn->rollback();
        }
        $errors[] = $e->getMessage();
        redirectWithImportMessage('', $errors);
    }
}

// Handle add product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_product') {
    $name        = trim($_POST['product_name'] ?? '');
    $barcode     = normalizeBarcode($_POST['barcode'] ?? '');
    $imagePath   = normalizeProductImagePath($_POST['image_path'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $productType = 'Both';
    $status      = $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';
    $compliance  = $_POST['compliance'] ?? 'Pending';
    $expiryDate  = $_POST['expiry_date'] ?? null;

    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Failed to upload image. Please try again.';
        } else {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
            ];
            $fileType = mime_content_type($_FILES['image_file']['tmp_name']);
            if (!isset($allowedTypes[$fileType])) {
                $errors[] = 'Only JPG, PNG, and GIF images are allowed.';
            } else {
                $uploadDir = __DIR__ . '/uploads/products';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $errors[] = 'Unable to create upload folder.';
                } else {
                    $extension = $allowedTypes[$fileType];
                    $fileName = 'product_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
                    $destination = $uploadDir . '/' . $fileName;
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $destination)) {
                        $imagePath = normalizeProductImagePath('uploads/products/' . $fileName);
                    } else {
                        $errors[] = 'Unable to save uploaded image.';
                    }
                }
            }
        }
    }

    if ($name === '') {
        $errors[] = 'Product name is required.';
    }
    if ($barcode === '') {
        $errors[] = 'Barcode is required for POS scanning.';
    } elseif (productBarcodeExists($conn, $barcode)) {
        $errors[] = 'This barcode is already assigned to another product.';
    }
    if ($category === '') {
        $errors[] = 'Category is required.';
    }
    if ($price < 0) {
        $errors[] = 'Price cannot be negative.';
    }
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative.';
    }

    if (empty($errors)) {
        try {
            $barcodeImagePath = saveBarcodeImage($barcode);
        } catch (Throwable $e) {
            $errors[] = 'Unable to generate barcode image. Please try again.';
        }
    }

    if (empty($errors)) {
        $physicalStock = $stock;
        $onlineStock = $stock;
        $stmt = $conn->prepare(
            'INSERT INTO products (productName, barcode, barcodeImagePath, productDescription, category, price, stockQuantity, physicalStock, onlineStock, productType, complianceStatus, status, imagePath, expiryDate)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'sssssdiiisssss',
            $name,
            $barcode,
            $barcodeImagePath,
            $description,
            $category,
            $price,
            $stock,
            $physicalStock,
            $onlineStock,
            $productType,
            $compliance,
            $status,
            $imagePath,
            $expiryDate
        );

        if ($stmt->execute()) {
            $success = 'Product added successfully.';
        } else {
            $errors[] = 'Failed to add product. Please try again.';
        }
        $stmt->close();
    }
}

// Handle edit product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_product') {
    $productId   = (int)($_POST['product_id'] ?? 0);
    $name        = trim($_POST['edit_product_name'] ?? '');
    $barcode     = normalizeBarcode($_POST['edit_barcode'] ?? '');
    $imagePath   = normalizeProductImagePath($_POST['edit_image_path'] ?? '');
    $description = trim($_POST['edit_description'] ?? '');
    $category    = trim($_POST['edit_category'] ?? '');
    $price       = (float)($_POST['edit_price'] ?? 0);
    $stock       = (int)($_POST['edit_stock'] ?? 0);
    $productType = 'Both';
    $status      = $_POST['edit_status'] === 'Inactive' ? 'Inactive' : 'Active';
    $compliance  = $_POST['edit_compliance'] ?? 'Pending';
    $expiryDate  = $_POST['edit_expiry_date'] ?? null;

    if (isset($_FILES['edit_image_file']) && $_FILES['edit_image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['edit_image_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Failed to upload image. Please try again.';
        } else {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
            ];
            $fileType = mime_content_type($_FILES['edit_image_file']['tmp_name']);
            if (!isset($allowedTypes[$fileType])) {
                $errors[] = 'Only JPG, PNG, and GIF images are allowed.';
            } else {
                $uploadDir = __DIR__ . '/uploads/products';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $errors[] = 'Unable to create upload folder.';
                } else {
                    $extension = $allowedTypes[$fileType];
                    $fileName = 'product_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
                    $destination = $uploadDir . '/' . $fileName;
                    if (move_uploaded_file($_FILES['edit_image_file']['tmp_name'], $destination)) {
                        $imagePath = normalizeProductImagePath('uploads/products/' . $fileName);
                    } else {
                        $errors[] = 'Unable to save uploaded image.';
                    }
                }
            }
        }
    }

    if ($productId <= 0) {
        $errors[] = 'Invalid product selected.';
    } else {
        if ($name === '') {
            $errors[] = 'Product name is required.';
        }
        if ($barcode === '') {
            $errors[] = 'Barcode is required for POS scanning.';
        } elseif (productBarcodeExists($conn, $barcode, $productId)) {
            $errors[] = 'This barcode is already assigned to another product.';
        }
        if ($category === '') {
            $errors[] = 'Category is required.';
        }
        if ($price < 0) {
            $errors[] = 'Price cannot be negative.';
        }
        if ($stock < 0) {
            $errors[] = 'Stock cannot be negative.';
        }
    }

    if (empty($errors) && $productId > 0) {
        try {
            $barcodeImagePath = saveBarcodeImage($barcode);
        } catch (Throwable $e) {
            $errors[] = 'Unable to generate barcode image. Please try again.';
        }
    }

    if (empty($errors) && $productId > 0) {
        $physicalStock = $stock;
        $onlineStock = $stock;
        $stmt = $conn->prepare(
            'UPDATE products
             SET productName = ?, barcode = ?, barcodeImagePath = ?, productDescription = ?, category = ?, price = ?, stockQuantity = ?,
                 physicalStock = ?, onlineStock = ?, productType = ?,
                 complianceStatus = ?, status = ?, imagePath = ?, expiryDate = ?
             WHERE productID = ?'
        );
        $stmt->bind_param(
            'sssssdiiisssssi',
            $name,
            $barcode,
            $barcodeImagePath,
            $description,
            $category,
            $price,
            $stock,
            $physicalStock,
            $onlineStock,
            $productType,
            $compliance,
            $status,
            $imagePath,
            $expiryDate,
            $productId
        );

        if ($stmt->execute()) {
            $success = 'Product updated successfully.';
        } else {
            $errors[] = 'Failed to update product. Please try again.';
        }
        $stmt->close();
    }
}

// Handle delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId > 0) {
        $stmt = $conn->prepare('DELETE FROM products WHERE productID = ?');
        $stmt->bind_param('i', $productId);
        if ($stmt->execute()) {
            $success = 'Product deleted successfully.';
        } else {
            $errors[] = 'Failed to delete product. Please try again.';
        }
        $stmt->close();
    } else {
        $errors[] = 'Invalid product selected for deletion.';
    }
}

// List and filters
$search    = trim($_GET['q'] ?? '');
$categoryF = trim($_GET['cat'] ?? '');
$addedF = trim($_GET['added'] ?? 'All');
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$where     = '1=1';
$params    = [];
$types     = '';

if ($search !== '') {
    $where .= ' AND (productName LIKE ? OR productDescription LIKE ? OR barcode LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($categoryF !== '' && $categoryF !== 'All') {
    $where .= ' AND category = ?';
    $params[] = $categoryF;
    $types .= 's';
}

if ($addedF === 'recent') {
    $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
}

$countSql = "SELECT COUNT(*) AS total FROM products WHERE $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalProducts = (int)($countResult->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$orderBy = $addedF === 'recent' ? 'created_at DESC, productID DESC' : 'productName ASC';
$sql = "SELECT productID, productName, barcode, barcodeImagePath, productDescription, category, price, stockQuantity, productType,
               complianceStatus, status, imagePath, expiryDate, created_at
        FROM products
        WHERE $where
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$queryParams = array_merge($params, [$perPage, $offset]);
$queryTypes = $types . 'ii';
$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageBaseParams = [];
if ($search !== '') {
    $pageBaseParams['q'] = $search;
}
if ($categoryF !== '' && $categoryF !== 'All') {
    $pageBaseParams['cat'] = $categoryF;
}
if ($addedF !== '' && $addedF !== 'All') {
    $pageBaseParams['added'] = $addedF;
}

// For category filter options
$catResult = $conn->query('SELECT DISTINCT category FROM products ORDER BY category ASC');
$categories = $catResult ? $catResult->fetch_all(MYSQLI_ASSOC) : [];
$categoryNames = ['Medication', 'Supplements', 'Personal Care', 'Equipment'];
foreach ($categories as $categoryRow) {
    $categoryName = trim((string) ($categoryRow['category'] ?? ''));
    if ($categoryName !== '' && !in_array(strtolower($categoryName), array_map('strtolower', $categoryNames), true)) {
        $categoryNames[] = $categoryName;
    }
}
natcasesort($categoryNames);
$categoryNames = array_values($categoryNames);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Product Management</title>
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
    <link rel="stylesheet" href="css/admin-products.css?v=4">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <button type="button" id="sidebarToggle" class="sidebar-toggle" aria-pressed="false" aria-label="Toggle sidebar">☰</button>
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
            <a href="analytics.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Analytics</span>
            </a>
            <a href="staff.php" class="nav-item">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Staff Management</span>
            </a>
            <a href="products.php" class="nav-item active">
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
        <div class="product-header">
            <div class="product-header-title">
                <h1>Product Management</h1>
                <p>Manage pharmacy products and inventory.</p>
            </div>
            <button type="button" class="btn-add-product" id="openAddProduct">
                <span class="btn-add-product-icon">＋</span>
                Add Product
            </button>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <?php echo htmlspecialchars(implode(' ', $errors)); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <section class="product-import-card">
            <div>
                <h2>Import Products</h2>
                <p>Upload .xlsx or .csv with columns: productName, barcode, description, category, price, stock, imagePath, expiryDate, status, compliance. Leave imagePath blank to auto-create a product thumbnail, or use a valid image URL/existing server path.</p>
            </div>
            <form method="post" action="products.php" enctype="multipart/form-data" class="product-import-form">
                <input type="hidden" name="action" value="import_products">
                <input type="file" name="product_import_file" accept=".xlsx,.csv" required>
                <button type="submit" class="btn-primary">Import File</button>
            </form>
        </section>

        <div class="product-search-row">
            <div class="product-search-card">
                <form method="get" action="products.php">
                    <?php if ($categoryF !== '' && $categoryF !== 'All'): ?>
                        <input type="hidden" name="cat" value="<?php echo htmlspecialchars($categoryF); ?>">
                    <?php endif; ?>
                    <?php if ($addedF !== '' && $addedF !== 'All'): ?>
                        <input type="hidden" name="added" value="<?php echo htmlspecialchars($addedF); ?>">
                    <?php endif; ?>
                    <input
                        type="text"
                        name="q"
                        class="product-search-input"
                        placeholder="Search products or barcodes..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </form>
            </div>
            <div class="product-filter-card">
                <form method="get" action="products.php">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>">
                    <?php endif; ?>
                    <?php if ($addedF !== '' && $addedF !== 'All'): ?>
                        <input type="hidden" name="added" value="<?php echo htmlspecialchars($addedF); ?>">
                    <?php endif; ?>
                    <select name="cat" class="product-filter-select" onchange="this.form.submit()">
                        <option value="All">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <?php $cName = $cat['category']; ?>
                            <option value="<?php echo htmlspecialchars($cName); ?>" <?php echo $categoryF === $cName ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="product-filter-card">
                <form method="get" action="products.php">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>">
                    <?php endif; ?>
                    <?php if ($categoryF !== '' && $categoryF !== 'All'): ?>
                        <input type="hidden" name="cat" value="<?php echo htmlspecialchars($categoryF); ?>">
                    <?php endif; ?>
                    <select name="added" class="product-filter-select" onchange="this.form.submit()">
                        <option value="All" <?php echo $addedF === 'All' ? 'selected' : ''; ?>>All Added</option>
                        <option value="recent" <?php echo $addedF === 'recent' ? 'selected' : ''; ?>>Recently Added</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="product-list-card">
            <div class="product-list-header">
                <h2><?php echo $addedF === 'recent' ? 'Recently Added Products' : 'All Products'; ?></h2>
                <div class="product-count">(<?php echo $totalProducts; ?>)</div>
            </div>

            <table class="product-table">
                <thead>
                <tr>
                    <th class="product-number-col">No.</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Expiry</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8">No products found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $index => $p): ?>
                        <tr>
                            <td class="product-number"><?php echo $offset + $index + 1; ?></td>
                            <td>
                                <div class="product-main">
                                    <div class="product-thumb">
                                        <?php if (!empty($p['imagePath'])): ?>
                                            <img src="<?php echo htmlspecialchars(resolveProductImageUrl($p['imagePath'], 'admin')); ?>" alt="" onerror="this.style.display='none'; this.parentElement.classList.add('missing-image'); this.insertAdjacentHTML('afterend', '<span class=&quot;product-thumb-fallback&quot;>Rx</span>');">
                                        <?php else: ?>
                                            <span>💊</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="product-name"><?php echo htmlspecialchars($p['productName']); ?></div>
                                        <div class="product-desc">Barcode: <?php echo htmlspecialchars($p['barcode'] ?: '-'); ?></div>
                                        <?php if (!empty($p['barcodeImagePath'])): ?>
                                            <div class="product-barcode-preview">
                                                <img src="<?php echo htmlspecialchars(resolveProductImageUrl($p['barcodeImagePath'], 'admin')); ?>" alt="Barcode for <?php echo htmlspecialchars($p['productName']); ?>">
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($p['productDescription'])): ?>
                                            <div class="product-desc"><?php echo htmlspecialchars($p['productDescription']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-category"><?php echo htmlspecialchars($p['category']); ?></span>
                            </td>
                            <td>
                                <span class="product-price">RM <?php echo number_format($p['price'], 2); ?></span>
                            </td>
                            <td>
                                <?php if ($p['stockQuantity'] <= 50): ?>
                                    <span class="product-stock-low"><?php echo (int)$p['stockQuantity']; ?></span>
                                <?php else: ?>
                                    <?php echo (int)$p['stockQuantity']; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status = $p['status'];
                                $badgeClass = strtolower($status) === 'inactive' ? 'inactive' : 'approved';
                                if ($p['complianceStatus'] === 'Pending') {
                                    $badgeClass = 'pending';
                                }
                                ?>
                                <span class="badge-status <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($status === 'Active' ? $p['complianceStatus'] : $status); ?>
                                </span>
                            </td>
                            <td>
                                <span class="product-expiry">
                                    <?php echo htmlspecialchars($p['expiryDate'] ?? '-'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="product-actions"
                                     data-product-id="<?php echo (int)$p['productID']; ?>"
                                     data-name="<?php echo htmlspecialchars($p['productName']); ?>"
                                     data-barcode="<?php echo htmlspecialchars($p['barcode'] ?? ''); ?>"
                                     data-image="<?php echo htmlspecialchars(resolveProductImageUrl($p['imagePath'] ?? '', 'admin')); ?>"
                                     data-desc="<?php echo htmlspecialchars($p['productDescription'] ?? ''); ?>"
                                     data-category="<?php echo htmlspecialchars($p['category']); ?>"
                                     data-price="<?php echo htmlspecialchars($p['price']); ?>"
                                     data-stock="<?php echo htmlspecialchars($p['stockQuantity']); ?>"
                                     data-status="<?php echo htmlspecialchars($p['status']); ?>"
                                     data-compliance="<?php echo htmlspecialchars($p['complianceStatus']); ?>"
                                     data-expiry="<?php echo htmlspecialchars($p['expiryDate'] ?? ''); ?>">
                                    <button type="button" class="product-actions-btn">⋯</button>
                                    <div class="product-actions-menu">
                                        <button type="button" class="product-edit-btn">Edit</button>
                                        <form method="post" action="products.php" onsubmit="return confirm('Delete this product?');">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$p['productID']; ?>">
                                            <button type="submit" class="product-delete-btn">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $prevParams = array_merge($pageBaseParams, ['page' => max(1, $page - 1)]);
                    $nextParams = array_merge($pageBaseParams, ['page' => min($totalPages, $page + 1)]);
                    ?>
                    <a class="pagination-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($prevParams)); ?>">Previous</a>
                    <div class="pagination-pages">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php $pageParams = array_merge($pageBaseParams, ['page' => $i]); ?>
                            <a class="pagination-page <?php echo $i === $page ? 'active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <a class="pagination-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($nextParams)); ?>">Next</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div class="product-modal-backdrop" id="addProductModal">
    <div class="product-modal">
        <div class="product-modal-header">
            <div class="product-modal-title">Add New Product</div>
            <button type="button" class="product-modal-close" id="closeAddProduct">×</button>
        </div>
        <div class="product-modal-body">
            <form method="post" action="products.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">

                <div class="product-modal-field">
                    <label class="product-modal-label" for="image_file">Product Image</label>
                    <input type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/gif" class="product-modal-input">
                    <label class="product-modal-label" for="image_path">or Image URL (optional)</label>
                    <input type="text" id="image_path" name="image_path" class="product-modal-input" placeholder="https://example.com/image.jpg">
                    <div class="product-image-preview-container">
                        <img id="image_path_preview" class="product-image-preview" src="" alt="Product image preview">
                        <span id="image_path_preview_placeholder" class="product-image-preview-placeholder">Upload an image file or enter a valid image URL to preview.</span>
                    </div>
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" class="product-modal-input" required>
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="barcode">Barcode</label>
                    <input type="text" id="barcode" name="barcode" class="product-modal-input" placeholder="Scan or type barcode" autocomplete="off" required>
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="description">Description</label>
                    <textarea id="description" name="description" class="product-modal-textarea"></textarea>
                </div>

                <div class="product-modal-grid">
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="category">Category</label>
                        <input type="text" id="category" name="category" class="product-modal-input" list="category-options" placeholder="Select or type a new category" required>
                        <datalist id="category-options">
                            <?php foreach ($categoryNames as $categoryName): ?>
                                <option value="<?php echo htmlspecialchars($categoryName); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="price">Price (RM)</label>
                        <input type="number" step="0.01" min="0" id="price" name="price" class="product-modal-input" required>
                    </div>
                </div>

                <div class="product-modal-grid">
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="stock">Stock Quantity</label>
                        <input type="number" min="0" id="stock" name="stock" class="product-modal-input" required>
                    </div>
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="expiry_date">Expiry Date</label>
                        <input type="date" id="expiry_date" name="expiry_date" class="product-modal-input">
                    </div>
                </div>

                <div class="product-modal-grid">
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="status">Status</label>
                        <select id="status" name="status" class="product-modal-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="compliance">Compliance</label>
                        <select id="compliance" name="compliance" class="product-modal-select">
                            <option value="Approved">Approved</option>
                            <option value="Pending">Pending</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                </div>

                <div class="product-modal-footer">
                    <button type="button" class="btn-secondary" id="cancelAddProduct">Cancel</button>
                    <button type="submit" class="btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="product-modal-backdrop" id="editProductModal">
    <div class="product-modal">
        <div class="product-modal-header">
            <div class="product-modal-title">Edit Product</div>
            <button type="button" class="product-modal-close" id="closeEditProduct">×</button>
        </div>
        <div class="product-modal-body">
            <form method="post" action="products.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="product_id" id="edit_product_id">

                <div class="product-modal-field">
                    <label class="product-modal-label" for="edit_image_file">Product Image</label>
                    <input type="file" id="edit_image_file" name="edit_image_file" accept="image/jpeg,image/png,image/gif" class="product-modal-input">
                    <label class="product-modal-label" for="edit_image_path">or Image URL</label>
                    <input type="text" id="edit_image_path" name="edit_image_path" class="product-modal-input" placeholder="https://example.com/image.jpg">
                    <div class="product-image-preview-container">
                        <img id="edit_image_path_preview" class="product-image-preview" src="" alt="Product image preview">
                        <span id="edit_image_path_preview_placeholder" class="product-image-preview-placeholder">Upload an image file or enter a valid image URL to preview.</span>
                    </div>
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="edit_product_name">Product Name</label>
                    <input type="text" id="edit_product_name" name="edit_product_name" class="product-modal-input" required>
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="edit_barcode">Barcode</label>
                    <input type="text" id="edit_barcode" name="edit_barcode" class="product-modal-input" placeholder="Scan or type barcode" autocomplete="off" required>
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="edit_description">Description</label>
                    <textarea id="edit_description" name="edit_description" class="product-modal-textarea"></textarea>
                </div>

                <div class="product-modal-grid">
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="edit_category">Category</label>
                        <input type="text" id="edit_category" name="edit_category" class="product-modal-input" list="edit-category-options" placeholder="Select or type a new category" required>
                        <datalist id="edit-category-options">
                            <?php foreach ($categoryNames as $categoryName): ?>
                                <option value="<?php echo htmlspecialchars($categoryName); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="edit_price">Price (RM)</label>
                        <input type="number" step="0.01" min="0" id="edit_price" name="edit_price" class="product-modal-input" required>
                    </div>
                </div>

                <div class="product-modal-grid">
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="edit_stock">Stock Quantity</label>
                        <input type="number" min="0" id="edit_stock" name="edit_stock" class="product-modal-input" required>
                    </div>
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="edit_expiry_date">Expiry Date</label>
                        <input type="date" id="edit_expiry_date" name="edit_expiry_date" class="product-modal-input">
                    </div>
                </div>

                <div class="product-modal-grid">
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="edit_status">Status</label>
                        <select id="edit_status" name="edit_status" class="product-modal-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="edit_compliance">Compliance</label>
                        <select id="edit_compliance" name="edit_compliance" class="product-modal-select">
                            <option value="Approved">Approved</option>
                            <option value="Pending">Pending</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                </div>

                <div class="product-modal-footer">
                    <button type="button" class="btn-secondary" id="cancelEditProduct">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const openAddProductBtn = document.getElementById('openAddProduct');
    const closeAddProductBtn = document.getElementById('closeAddProduct');
    const cancelAddProductBtn = document.getElementById('cancelAddProduct');
    const addProductModal = document.getElementById('addProductModal');
    const imageFileInput = document.getElementById('image_file');
    const imagePathInput = document.getElementById('image_path');
    const imagePathPreview = document.getElementById('image_path_preview');
    const imagePathPreviewPlaceholder = document.getElementById('image_path_preview_placeholder');
    const editImageFileInput = document.getElementById('edit_image_file');
    const editImagePath = document.getElementById('edit_image_path');
    const editImagePathPreview = document.getElementById('edit_image_path_preview');
    const editImagePathPreviewPlaceholder = document.getElementById('edit_image_path_preview_placeholder');
    let currentPreviewUrl = null;

    function setPreviewImageFromSource(fileInput, urlInput, imageEl, placeholderEl) {
        if (fileInput && fileInput.files && fileInput.files[0]) {
            if (currentPreviewUrl) {
                URL.revokeObjectURL(currentPreviewUrl);
            }
            const file = fileInput.files[0];
            currentPreviewUrl = URL.createObjectURL(file);
            imageEl.src = currentPreviewUrl;
            placeholderEl.style.display = 'none';
            imageEl.style.display = 'block';
            return;
        }

        const url = urlInput.value.trim();
        if (!url) {
            imageEl.src = '';
            imageEl.style.display = 'none';
            placeholderEl.textContent = 'Upload an image file or enter a valid image URL to preview.';
            placeholderEl.style.display = 'block';
            return;
        }

        imageEl.src = url;
        placeholderEl.style.display = 'none';
        imageEl.style.display = 'block';
    }

    if (imagePathInput) {
        imagePathInput.addEventListener('input', function () {
            setPreviewImageFromSource(imageFileInput, imagePathInput, imagePathPreview, imagePathPreviewPlaceholder);
        });
    }

    if (imageFileInput) {
        imageFileInput.addEventListener('change', function () {
            setPreviewImageFromSource(imageFileInput, imagePathInput, imagePathPreview, imagePathPreviewPlaceholder);
        });
    }

    if (editImagePath) {
        editImagePath.addEventListener('input', function () {
            setPreviewImageFromSource(editImageFileInput, editImagePath, editImagePathPreview, editImagePathPreviewPlaceholder);
        });
    }

    if (editImageFileInput) {
        editImageFileInput.addEventListener('change', function () {
            setPreviewImageFromSource(editImageFileInput, editImagePath, editImagePathPreview, editImagePathPreviewPlaceholder);
        });
    }

    if (imagePathPreview) {
        imagePathPreview.addEventListener('error', function () {
            this.style.display = 'none';
            imagePathPreviewPlaceholder.textContent = 'Unable to preview image. Please check the file or URL.';
            imagePathPreviewPlaceholder.style.display = 'block';
        });
    }

    if (editImagePathPreview) {
        editImagePathPreview.addEventListener('error', function () {
            this.style.display = 'none';
            editImagePathPreviewPlaceholder.textContent = 'Unable to preview image. Please check the file or URL.';
            editImagePathPreviewPlaceholder.style.display = 'block';
        });
    }

    function openAddProductModal() {
        if (addProductModal) {
            if (imageFileInput) imageFileInput.value = '';
            if (imagePathInput) imagePathInput.value = '';
            setPreviewImageFromSource(imageFileInput, imagePathInput, imagePathPreview, imagePathPreviewPlaceholder);
            addProductModal.classList.add('show');
        }
    }
    function closeAddProductModal() {
        if (addProductModal) addProductModal.classList.remove('show');
    }

    if (openAddProductBtn) openAddProductBtn.addEventListener('click', openAddProductModal);
    if (closeAddProductBtn) closeAddProductBtn.addEventListener('click', closeAddProductModal);
    if (cancelAddProductBtn) cancelAddProductBtn.addEventListener('click', closeAddProductModal);

    window.addEventListener('click', function (e) {
        if (e.target === addProductModal) {
            closeAddProductModal();
        }
    });

    const editProductModal = document.getElementById('editProductModal');
    const closeEditProductBtn = document.getElementById('closeEditProduct');
    const cancelEditProductBtn = document.getElementById('cancelEditProduct');
    const editProductId = document.getElementById('edit_product_id');
    const editProductName = document.getElementById('edit_product_name');
    const editBarcode = document.getElementById('edit_barcode');
    const editDescription = document.getElementById('edit_description');
    const editCategory = document.getElementById('edit_category');
    const editPrice = document.getElementById('edit_price');
    const editStock = document.getElementById('edit_stock');
    const editExpiryDate = document.getElementById('edit_expiry_date');
    const editStatus = document.getElementById('edit_status');
    const editCompliance = document.getElementById('edit_compliance');

    function openEditProductModal() {
        if (editProductModal) editProductModal.classList.add('show');
    }
    function closeEditProductModal() {
        if (editProductModal) editProductModal.classList.remove('show');
    }

    document.querySelectorAll('.product-actions-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const container = btn.parentElement;
            const menu = container.querySelector('.product-actions-menu');
            document.querySelectorAll('.product-actions-menu').forEach(function (m) {
                if (m !== menu) m.classList.remove('show');
            });
            if (menu) menu.classList.toggle('show');
        });
    });

    document.querySelectorAll('.product-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const container = btn.closest('.product-actions');
            if (!container) return;
            const menu = container.querySelector('.product-actions-menu');
            if (menu) menu.classList.remove('show');

            editProductId.value = container.getAttribute('data-product-id') || '';
            editImagePath.value = container.getAttribute('data-image') || '';
            if (editImageFileInput) {
                editImageFileInput.value = '';
            }
            editProductName.value = container.getAttribute('data-name') || '';
            editBarcode.value = container.getAttribute('data-barcode') || '';
            editDescription.value = container.getAttribute('data-desc') || '';
            editCategory.value = container.getAttribute('data-category') || 'Medication';
            editPrice.value = container.getAttribute('data-price') || '0';
            editStock.value = container.getAttribute('data-stock') || '0';
            editExpiryDate.value = container.getAttribute('data-expiry') || '';
            editStatus.value = container.getAttribute('data-status') || 'Active';
            editCompliance.value = container.getAttribute('data-compliance') || 'Approved';
            setPreviewImageFromSource(editImageFileInput, editImagePath, editImagePathPreview, editImagePathPreviewPlaceholder);

            openEditProductModal();
        });
    });

    if (closeEditProductBtn) closeEditProductBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeEditProductModal();
    });
    if (cancelEditProductBtn) cancelEditProductBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeEditProductModal();
    });

    window.addEventListener('click', function (e) {
        if (e.target === editProductModal) {
            closeEditProductModal();
        }
    });

    window.addEventListener('click', function () {
        document.querySelectorAll('.product-actions-menu').forEach(function (m) {
            m.classList.remove('show');
        });
    });
</script>
</body>
</html>

