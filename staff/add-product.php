<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/product-import.php';
require_once __DIR__ . '/../includes/product-schema.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: login.php');
    exit;
}

$errors = $_SESSION['flash_errors'] ?? [];
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_errors'], $_SESSION['flash_success']);

function redirectWithImportMessage(string $success = '', array $errors = []): void
{
    $_SESSION['flash_success'] = $success;
    $_SESSION['flash_errors'] = $errors;
    header('Location: add-product.php');
    exit;
}

$userId = (int) $_SESSION['userID'];
ensureProductBarcodeSchema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_product_submissions') {
    $importStarted = false;
    try {
        $productsToImport = productsFromUploadedSpreadsheet($_FILES['product_import_file'] ?? []);
        $conn->begin_transaction();
        $importStarted = true;
        $importedCount = insertProductSubmissions($conn, $productsToImport, $userId);
        $conn->commit();
        $success = $importedCount . ' product submission' . ($importedCount === 1 ? '' : 's') . ' imported for admin approval.';
        redirectWithImportMessage($success);
    } catch (Throwable $e) {
        if ($importStarted) {
            $conn->rollback();
        }
        $errors[] = $e->getMessage();
        redirectWithImportMessage('', $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'import_product_submissions') {
    $productName = trim($_POST['product_name'] ?? '');
    $barcode = normalizeBarcode($_POST['barcode'] ?? '');
    $productDescription = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'Medication');
    $price = (float)($_POST['price'] ?? 0);
    $stockQuantity = (int)($_POST['stock'] ?? 0);
    $expiryDate = trim($_POST['expiry_date'] ?? '');
    $imagePath = '';

    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Product image is required.';
    } else {
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
                $uploadDir = __DIR__ . '/../admin/uploads/products';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $errors[] = 'Unable to create upload folder.';
                } else {
                    $extension = $allowedTypes[$fileType];
                    $fileName = 'product_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
                    $destination = $uploadDir . '/' . $fileName;
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $destination)) {
                        $imagePath = 'uploads/products/' . $fileName;
                    } else {
                        $errors[] = 'Unable to save uploaded image.';
                    }
                }
            }
        }
    }

    if ($productName === '') {
        $errors[] = 'Product name is required.';
    }
    if ($barcode === '') {
        $errors[] = 'Barcode is required for POS scanning.';
    } elseif (productBarcodeExists($conn, $barcode) || pendingSubmissionBarcodeExists($conn, $barcode)) {
        $errors[] = 'This barcode is already assigned to another product or pending submission.';
    }
    if ($price <= 0) {
        $errors[] = 'Price must be greater than zero.';
    }
    if ($expiryDate === '') {
        $errors[] = 'Expiry date is required.';
    }

    if (empty($errors)) {
        try {
            $barcodeImagePath = saveBarcodeImage($barcode);
        } catch (Throwable $e) {
            $errors[] = 'Unable to generate barcode image. Please try again.';
        }
    }

    if (empty($errors)) {
        $nextId = 1;
        $maxResult = $conn->query('SELECT COALESCE(MAX(submissionID), 0) + 1 AS nextID FROM product_submissions');
        if ($maxResult) {
            $row = $maxResult->fetch_assoc();
            $nextId = (int)($row['nextID'] ?? 1);
            $maxResult->free();
        }

        $stmt = $conn->prepare(
            'INSERT INTO product_submissions
             (submissionID, userID, productName, barcode, barcodeImagePath, productDescription, category, price, stockQuantity, imagePath, expiryDate, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' 
        );

        $status = 'Pending';
        $stmt->bind_param(
            'iisssssdisss',
            $nextId,
            $userId,
            $productName,
            $barcode,
            $barcodeImagePath,
            $productDescription,
            $category,
            $price,
            $stockQuantity,
            $imagePath,
            $expiryDate,
            $status
        );

        if ($stmt->execute()) {
            $success = 'Product submitted for admin approval.';
            $productName = $barcode = $productDescription = $imagePath = '';
            $category = 'Medication';
            $price = 0;
            $stockQuantity = 0;
            $expiryDate = '';
        } else {
            $errors[] = 'Unable to submit product. Please try again.';
        }
        $stmt->close();
    }
}

$submissionsStmt = $conn->prepare(
    'SELECT submissionID, productName, barcode, category, price, stockQuantity, imagePath, expiryDate, status, rejectionReason, created_at
     FROM product_submissions
     WHERE userID = ?
     ORDER BY created_at DESC'
);
$submissionsStmt->bind_param('i', $userId);
$submissionsStmt->execute();
$submissionsResult = $submissionsStmt->get_result();
$submissions = $submissionsResult->fetch_all(MYSQLI_ASSOC);
$submissionsStmt->close();

// A submitted category is retained in the suggestions, even while it is waiting for approval.
$categoryNames = ['Medication', 'Supplements', 'Personal Care', 'Equipment'];
$categoryResult = $conn->query('SELECT category FROM products UNION SELECT category FROM product_submissions ORDER BY category ASC');
if ($categoryResult) {
    while ($categoryRow = $categoryResult->fetch_assoc()) {
        $categoryName = trim((string) ($categoryRow['category'] ?? ''));
        if ($categoryName !== '' && !in_array(strtolower($categoryName), array_map('strtolower', $categoryNames), true)) {
            $categoryNames[] = $categoryName;
        }
    }
}
natcasesort($categoryNames);
$categoryNames = array_values($categoryNames);

function formatDateTime($dateTimeString)
{
    if (!$dateTimeString) {
        return '-';
    }
    $dt = new DateTime($dateTimeString);
    return $dt->format('M d, Y h:i A');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Submit Product</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/staff-dashboard.css">
    <link rel="stylesheet" href="css/staff-add-product.css">
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
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="add-product.php" class="nav-item active">
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
                    <h1>Add New Product</h1>
                    <p>Submit products for admin approval before they go live.</p>
                </div>
            </header>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php echo htmlspecialchars(implode(' ', $errors)); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <section class="info-banner">
                <div class="info-icon">⚠️</div>
                <div>
                    <h2>Admin Approval Required</h2>
                    <p>All products submitted by staff members require admin approval before being added to the store inventory. You will be notified once your submission is reviewed.</p>
                </div>
            </section>

            <section class="card import-card">
                <div>
                    <h2>Import Product File</h2>
                    <p>Upload .xlsx or .csv with columns: productName, barcode, description, category, price, stock, imagePath, expiryDate.</p>
                </div>
                <form method="post" action="add-product.php" enctype="multipart/form-data" class="import-form">
                    <input type="hidden" name="action" value="import_product_submissions">
                    <input type="file" name="product_import_file" accept=".xlsx,.csv" required>
                    <button type="submit" class="btn-primary">Import for Approval</button>
                </form>
            </section>

            <div class="add-product-grid">
                <section class="card form-card">
                    <div class="card-title">Product Information</div>
                    <form method="post" action="add-product.php" enctype="multipart/form-data">
                        <div class="field-group">
                            <label for="image_file">Product Image <span class="required">*</span></label>
                            <input type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/gif" required>
                        </div>
                        <div class="field-group">
                            <label for="product_name">Product Name <span class="required">*</span></label>
                            <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($productName ?? ''); ?>" placeholder="Enter product name" required>
                        </div>
                        <div class="field-group">
                            <label for="barcode">Barcode <span class="required">*</span></label>
                            <input type="text" id="barcode" name="barcode" value="<?php echo htmlspecialchars($barcode ?? ''); ?>" placeholder="Scan or type the product barcode" autocomplete="off" required>
                        </div>
                        <div class="field-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4" placeholder="Enter product description"><?php echo htmlspecialchars($productDescription ?? ''); ?></textarea>
                        </div>
                        <div class="grid-two">
                            <div class="field-group">
                                <label for="category">Category</label>
                                <input type="text" id="category" name="category" list="category-options" value="<?php echo htmlspecialchars($category ?? ''); ?>" placeholder="Select or type a new category" required>
                                <datalist id="category-options">
                                    <?php foreach ($categoryNames as $categoryName): ?>
                                        <option value="<?php echo htmlspecialchars($categoryName); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="field-group">
                                <label for="price">Price (RM) <span class="required">*</span></label>
                                <input type="number" step="0.01" min="0" id="price" name="price" value="<?php echo htmlspecialchars(number_format($price ?? 0, 2, '.', '')); ?>" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="grid-two">
                            <div class="field-group">
                                <label for="stock">Stock Quantity</label>
                                <input type="number" min="0" id="stock" name="stock" value="<?php echo htmlspecialchars($stockQuantity ?? 0); ?>" placeholder="0">
                            </div>
                            <div class="field-group">
                                <label for="expiry_date">Expiry Date <span class="required">*</span></label>
                                <input type="date" id="expiry_date" name="expiry_date" value="<?php echo htmlspecialchars($expiryDate ?? ''); ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary submit-full">Submit for Approval</button>
                    </form>
                </section>

                <section class="card submissions-card">
                    <div class="card-title">Your Submissions</div>
                    <?php if (empty($submissions)): ?>
                        <div class="empty-state">
                            <p>No submissions yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="submission-table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $submission): ?>
                                        <tr>
                                            <td>
                                                <div class="submission-product">
                                                    <span class="submission-name"><?php echo htmlspecialchars($submission['productName']); ?></span>
                                                    <span class="submission-meta">Barcode: <?php echo htmlspecialchars($submission['barcode'] ?: '-'); ?> | RM<?php echo number_format($submission['price'], 2); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($submission['status']); ?>"><?php echo htmlspecialchars($submission['status']); ?></span>
                                                <?php if ($submission['status'] === 'Rejected' && !empty($submission['rejectionReason'])): ?>
                                                    <div class="rejection-reason"><?php echo htmlspecialchars($submission['rejectionReason']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(formatDateTime($submission['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
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
