<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/product-expiry.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: login.php');
    exit;
}

disableExpiredProducts($conn);

$userID = $_SESSION['userID'];

// Get cart count
$cartCountResult = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $userID");
$cartCount = $cartCountResult ? $cartCountResult->fetch_assoc()['total'] ?? 0 : 0;

$searchQuery = trim($_GET['q'] ?? '');
$selectedCategory = trim($_GET['cat'] ?? 'All');

$where = "status = 'Active' AND complianceStatus = 'Approved' AND (expiryDate IS NULL OR expiryDate >= CURDATE())";
$params = [];
$types = '';

if ($searchQuery !== '') {
    $where .= ' AND (productName LIKE ? OR productDescription LIKE ?)';
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

$sql = "SELECT productID, productName, productDescription, category, price,
               stockQuantity AS availableStock, imagePath
        FROM products
        WHERE {$where}
        ORDER BY productName ASC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Database query error: ' . $conn->error);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$categoryResult = $conn->query("SELECT DISTINCT category FROM products WHERE status = 'Active' AND complianceStatus = 'Approved' AND (expiryDate IS NULL OR expiryDate >= CURDATE()) ORDER BY category ASC");
$categories = $categoryResult ? $categoryResult->fetch_all(MYSQLI_ASSOC) : [];

function resolveImageUrl($imagePath) {
    $imagePath = trim($imagePath);
    if ($imagePath === '') {
        return null;
    }
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }

    $imagePath = ltrim($imagePath, '/');

    if (str_starts_with($imagePath, 'admin/')) {
        return '../' . $imagePath;
    }

    if (str_starts_with($imagePath, 'uploads/')) {
        return '../admin/' . $imagePath;
    }

    return '../admin/' . $imagePath;
}

function badgeLabel($stockQuantity) {
    if ($stockQuantity <= 12) {
        return 'Low Stock';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Essen Pharmacy - Customer Dashboard</title>
    <link rel="stylesheet" href="css/customer-dashboard.css">
</head>
<body>
<div class="customer-layout">
    <aside class="sidebar">
        <div>
            <div class="brand">
                <img src="../logo.png" alt="Essen Pharmacy" class="brand-inline-logo" width="22" height="22" style="width:22px;height:22px;object-fit:contain;flex:0 0 22px;display:block;">
                <div>
                    <h1>Essen Pharmacy</h1>
                    <p>Customer Portal</p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <span class="nav-icon">📦</span>
                    <span>Products</span>
                </a>
                <a href="cart.php" class="nav-item">
                    <span class="nav-icon">🛒</span>
                    <span>My Cart<?php if ($cartCount > 0): ?> (<?php echo $cartCount; ?>)<?php endif; ?></span>
                </a>
                <a href="order-history.php" class="nav-item">
                    <span class="nav-icon">📜</span>
                    <span>Order History</span>
                </a>
                <a href="find-us.php" class="nav-item">
                    <span class="nav-icon">&#128205;</span>
                    <span>Find Us</span>
                </a>
                <a href="support-chat.php" class="nav-item">
                    <span class="nav-icon">💬</span>
                    <span>Support Chat</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <span class="nav-icon">👤</span>
                    <span>Profile</span>
                </a>
                <a href="../logout.php" class="nav-item">
                    <span class="nav-icon">↩</span>
                    <span>Sign Out</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-footer">
            <p class="support-title">Need help?</p>
            <p class="support-copy">Contact our pharmacist</p>
            <a href="support-chat.php" class="support-link">Support Chat</a>
        </div>
    </aside>

    <main class="main-panel">
        <header class="page-header">
            <div>
                <p class="eyebrow">Products</p>
                <h2>Browse our wide selection of pharmacy products.</h2>
            </div>
        </header>

        <?php if (isset($_SESSION['cart_message'])): ?>
            <div class="message-banner success">
                <?php echo htmlspecialchars($_SESSION['cart_message']); ?>
                <button type="button" class="message-close" onclick="this.parentElement.style.display='none'">×</button>
            </div>
            <?php unset($_SESSION['cart_message']); ?>
        <?php endif; ?>

        <!-- Cart Success Modal -->
        <div id="cartModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="success-icon">✓</div>
                    <h3 id="modalMessage">Item added to cart!</h3>
                    <p>Continue shopping or view your cart</p>
                </div>
                <div class="modal-footer">
                    <button class="modal-btn secondary" onclick="closeModal()">Continue Shopping</button>
                    <a href="cart.php" class="modal-btn primary">View Cart</a>
                </div>
            </div>
        </div>

        <form method="get" action="dashboard.php" class="controls-row">
            <div class="search-form">
                <label for="search" class="visually-hidden">Search products</label>
                <input
                    id="search"
                    name="q"
                    value="<?php echo htmlspecialchars($searchQuery); ?>"
                    placeholder="Search products..."
                    class="search-input"
                    autocomplete="off"
                >
            </div>

            <div class="filter-group">
                <span class="filter-icon">⚙️</span>
                <label for="category" class="visually-hidden">Category</label>
                <select id="category" name="cat" class="filter-select" onchange="this.form.submit()">
                    <option value="All"<?php echo $selectedCategory === 'All' ? ' selected' : ''; ?>>All</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"<?php echo $selectedCategory === $cat['category'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($cat['category']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if (empty($products)): ?>
            <div class="empty-state">
                <h3>No products found</h3>
                <p>Try adjusting your search or filter criteria.</p>
                <a href="dashboard.php" class="secondary-button">Clear filters</a>
            </div>
        <?php else: ?>
            <section class="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php
                        $imageUrl = resolveImageUrl($product['imagePath']);
                        $badge = badgeLabel((int)$product['availableStock']);
                    ?>
                    <article class="product-card">
                        <div class="product-card-top">
                            <?php if ($badge !== ''): ?>
                                <span class="status-badge low-stock"><?php echo htmlspecialchars($badge); ?></span>
                            <?php endif; ?>
                            <div class="product-media">
                                <?php if ($imageUrl): ?>
                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($product['productName']); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="product-icon">💊</div>
                                <?php endif; ?>
                            </div>
                            <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                        </div>
                        <div class="product-card-body">
                            <h3><?php echo htmlspecialchars($product['productName']); ?></h3>
                            <p><?php echo htmlspecialchars($product['productDescription'] ?: 'No description available.'); ?></p>
                        </div>
                        <div class="product-card-footer">
                            <div>
                                <div class="price">RM <?php echo number_format($product['price'], 2); ?></div>
                            </div>
                            <button type="button" class="add-button" onclick="addToCart(<?php echo $product['productID']; ?>, 1)">Add to Cart</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
<script>
function addToCart(productId, quantity) {
    // Disable the button temporarily
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Adding...';
    button.disabled = true;

    // Create form data
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);

    // Send AJAX request
    fetch('cart.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Show modal for success, banner for errors
        if (data.success) {
            showModal(data.message);
            updateCartCount();
        } else {
            showMessage(data.message, 'error');
        }
        
        // Re-enable button
        button.textContent = originalText;
        button.disabled = false;
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Failed to add item to cart. Please try again.', 'error');
        
        // Re-enable button
        button.textContent = originalText;
        button.disabled = false;
    });
}

function showMessage(message, type) {
    // Remove existing message
    const existingMessage = document.querySelector('.message-banner');
    if (existingMessage) {
        existingMessage.remove();
    }

    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `message-banner ${type}`;
    messageDiv.innerHTML = `
        ${message}
        <button type="button" class="message-close" onclick="this.parentElement.remove()">×</button>
    `;

    // Insert after page header
    const pageHeader = document.querySelector('.page-header');
    pageHeader.insertAdjacentElement('afterend', messageDiv);

    // Auto-hide after 3 seconds
    setTimeout(() => {
        if (messageDiv.parentElement) {
            messageDiv.remove();
        }
    }, 3000);
}

function updateCartCount() {
    // Fetch updated cart count
    fetch('cart.php?action=get_count')
        .then(response => response.text())
        .then(count => {
            const cartLinks = document.querySelectorAll('a[href*="cart.php"] span:last-child');
            cartLinks.forEach(link => {
                const baseText = link.textContent.replace(/\s*\(\d+\)$/, '');
                link.textContent = count > 0 ? `${baseText} (${count})` : baseText;
            });
        })
        .catch(error => console.error('Error updating cart count:', error));
}

function showModal(message) {
    const modal = document.getElementById('cartModal');
    const modalMessage = document.getElementById('modalMessage');
    modalMessage.textContent = message;
    modal.style.display = 'block';
    
    // Auto-close after 3 seconds
    setTimeout(() => {
        closeModal();
    }, 3000);
}

function closeModal() {
    const modal = document.getElementById('cartModal');
    modal.style.display = 'none';
}

// Modal event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Close modal when clicking the X
    const modalClose = document.querySelector('.modal-close');
    if (modalClose) {
        modalClose.onclick = closeModal;
    }

    // Close modal when clicking outside
    const modal = document.getElementById('cartModal');
    if (modal) {
        modal.onclick = function(event) {
            if (event.target === modal) {
                closeModal();
            }
        }
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
});
</script>
</body>
</html>
