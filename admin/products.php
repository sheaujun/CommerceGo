<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$errors = [];
$success = '';

// Handle add product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_product') {
    $name        = trim($_POST['product_name'] ?? '');
    $imagePath   = trim($_POST['image_path'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $status      = $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';
    $compliance  = $_POST['compliance'] ?? 'Pending';
    $expiryDate  = $_POST['expiry_date'] ?? null;

    if ($name === '') {
        $errors[] = 'Product name is required.';
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
        $stmt = $conn->prepare(
            'INSERT INTO products (productName, productDescription, category, price, stockQuantity, complianceStatus, status, imagePath, expiryDate)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'sssisssss',
            $name,
            $description,
            $category,
            $price,
            $stock,
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
    $imagePath   = trim($_POST['edit_image_path'] ?? '');
    $description = trim($_POST['edit_description'] ?? '');
    $category    = trim($_POST['edit_category'] ?? '');
    $price       = (float)($_POST['edit_price'] ?? 0);
    $stock       = (int)($_POST['edit_stock'] ?? 0);
    $status      = $_POST['edit_status'] === 'Inactive' ? 'Inactive' : 'Active';
    $compliance  = $_POST['edit_compliance'] ?? 'Pending';
    $expiryDate  = $_POST['edit_expiry_date'] ?? null;

    if ($productId <= 0) {
        $errors[] = 'Invalid product selected.';
    } else {
        if ($name === '') {
            $errors[] = 'Product name is required.';
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
        $stmt = $conn->prepare(
            'UPDATE products
             SET productName = ?, productDescription = ?, category = ?, price = ?, stockQuantity = ?,
                 complianceStatus = ?, status = ?, imagePath = ?, expiryDate = ?
             WHERE productID = ?'
        );
        $stmt->bind_param(
            'sssisssssi',
            $name,
            $description,
            $category,
            $price,
            $stock,
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
$where     = '1=1';
$params    = [];
$types     = '';

if ($search !== '') {
    $where .= ' AND (productName LIKE ? OR productDescription LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($categoryF !== '' && $categoryF !== 'All') {
    $where .= ' AND category = ?';
    $params[] = $categoryF;
    $types .= 's';
}

$sql = "SELECT productID, productName, productDescription, category, price, stockQuantity,
               complianceStatus, status, imagePath, expiryDate
        FROM products
        WHERE $where
        ORDER BY productName ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// For category filter options
$catResult = $conn->query('SELECT DISTINCT category FROM products ORDER BY category ASC');
$categories = $catResult ? $catResult->fetch_all(MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Product Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/admin-products.css">
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
            <a href="#" class="nav-item">
                <span class="nav-icon">✅</span>
                <span class="nav-label">Approvals</span>
            </a>
            <a href="#" class="nav-item">
                <span class="nav-icon">🧾</span>
                <span class="nav-label">Customers</span>
            </a>
            <a href="#" class="nav-item">
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

        <div class="product-search-row">
            <div class="product-search-card">
                <form method="get" action="products.php">
                    <input
                        type="text"
                        name="q"
                        class="product-search-input"
                        placeholder="Search products..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </form>
            </div>
            <div class="product-filter-card">
                <form method="get" action="products.php">
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
        </div>

        <div class="product-list-card">
            <div class="product-list-header">
                <h2>All Products</h2>
                <div class="product-count">(<?php echo count($products); ?>)</div>
            </div>

            <table class="product-table">
                <thead>
                <tr>
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
                        <td colspan="7">No products found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="product-main">
                                    <div class="product-thumb">
                                        <?php if (!empty($p['imagePath'])): ?>
                                            <img src="<?php echo htmlspecialchars($p['imagePath']); ?>" alt="">
                                        <?php else: ?>
                                            <span>💊</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="product-name"><?php echo htmlspecialchars($p['productName']); ?></div>
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
                                <span class="product-price">$<?php echo number_format($p['price'], 2); ?></span>
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
                                     data-image="<?php echo htmlspecialchars($p['imagePath'] ?? ''); ?>"
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
            <form method="post" action="products.php">
                <input type="hidden" name="action" value="add_product">

                <div class="product-modal-field">
                    <label class="product-modal-label" for="image_path">Product Image URL</label>
                    <input type="text" id="image_path" name="image_path" class="product-modal-input">
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" class="product-modal-input" required>
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="description">Description</label>
                    <textarea id="description" name="description" class="product-modal-textarea"></textarea>
                </div>

                <div class="product-modal-grid">
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="category">Category</label>
                        <select id="category" name="category" class="product-modal-select" required>
                            <option value="">Select category</option>
                            <option value="Medication">Medication</option>
                            <option value="Supplements">Supplements</option>
                            <option value="Personal Care">Personal Care</option>
                            <option value="Equipment">Equipment</option>
                        </select>
                    </div>
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="price">Price ($)</label>
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
            <form method="post" action="products.php">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="product_id" id="edit_product_id">

                <div class="product-modal-field">
                    <label class="product-modal-label" for="edit_image_path">Product Image URL</label>
                    <input type="text" id="edit_image_path" name="edit_image_path" class="product-modal-input">
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="edit_product_name">Product Name</label>
                    <input type="text" id="edit_product_name" name="edit_product_name" class="product-modal-input" required>
                </div>

                <div class="product-modal-field">
                    <label class="product-modal-label" for="edit_description">Description</label>
                    <textarea id="edit_description" name="edit_description" class="product-modal-textarea"></textarea>
                </div>

                <div class="product-modal-grid">
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="edit_category">Category</label>
                        <select id="edit_category" name="edit_category" class="product-modal-select" required>
                            <option value="Medication">Medication</option>
                            <option value="Supplements">Supplements</option>
                            <option value="Personal Care">Personal Care</option>
                            <option value="Equipment">Equipment</option>
                        </select>
                    </div>
                    <div class="product-modal-field">
                        <label class="product-modal-label" for="edit_price">Price ($)</label>
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

    function openAddProductModal() {
        if (addProductModal) addProductModal.classList.add('show');
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
    const editImagePath = document.getElementById('edit_image_path');
    const editProductName = document.getElementById('edit_product_name');
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
            editProductName.value = container.getAttribute('data-name') || '';
            editDescription.value = container.getAttribute('data-desc') || '';
            editCategory.value = container.getAttribute('data-category') || 'Medication';
            editPrice.value = container.getAttribute('data-price') || '0';
            editStock.value = container.getAttribute('data-stock') || '0';
            editExpiryDate.value = container.getAttribute('data-expiry') || '';
            editStatus.value = container.getAttribute('data-status') || 'Active';
            editCompliance.value = container.getAttribute('data-compliance') || 'Approved';

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

