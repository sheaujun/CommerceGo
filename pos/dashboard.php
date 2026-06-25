<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/product-expiry.php';
require_once __DIR__ . '/../includes/product-schema.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

disableExpiredProducts($conn);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function resolveProductImageUrl(?string $imagePath): string
{
    $imagePath = trim((string) $imagePath);
    if ($imagePath === '') {
        return '';
    }
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }
    $imagePath = ltrim($imagePath, '/');
    return str_starts_with($imagePath, 'admin/') ? '../' . $imagePath : '../admin/' . $imagePath;
}

ensureProductBarcodeSchema($conn);

$search = trim($_GET['q'] ?? '');
$where = "status = 'Active' AND complianceStatus = 'Approved' AND stockQuantity > 0 AND (expiryDate IS NULL OR expiryDate >= CURDATE())";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (productName LIKE ? OR category LIKE ? OR barcode LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
}

$sql = "SELECT productID, productName, category, price, stockQuantity, imagePath, barcode
        FROM products
        WHERE $where
        ORDER BY productName ASC
        LIMIT 36";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$cashierName = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));
if ($cashierName === '') {
    $cashierName = $_SESSION['userName'] ?? 'Staff Cashier';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Essen Pharmacy POS</title>
    <link rel="stylesheet" href="css/pos.css">
</head>
<body>
<div class="pos-shell">
    <header class="pos-topbar">
        <div class="pos-brand">
            <img src="../logo-transparent.png" alt="Essen Pharmacy">
            <div>
                <h1>Pharmacy POS</h1>
                <p>Cashier: <?php echo h($cashierName); ?></p>
            </div>
        </div>
        <nav class="pos-nav">
            <a href="../staff/dashboard.php">Inventory</a>
            <a href="../staff/products.php">Products</a>
            <a href="../logout.php">Sign Out</a>
        </nav>
    </header>

    <main class="pos-layout">
        <section class="pos-products">
            <div class="scan-panel">
                <label for="barcodeInput">Barcode Scanner</label>
                <div class="scan-row">
                    <input id="barcodeInput" type="text" inputmode="text" autocomplete="off" placeholder="Scan product barcode">
                    <button type="button" id="scanButton">Add</button>
                </div>
                <p class="scan-hint">Barcode scanners work like keyboards. Scan and press Enter to add.</p>
                <div id="scanResult" class="scan-result" hidden aria-live="polite"></div>
            </div>

            <form method="get" action="dashboard.php" class="search-panel">
                <input type="text" name="q" value="<?php echo h($search); ?>" placeholder="Search medicine by name, category, or barcode">
                <button type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="dashboard.php">Clear</a>
                <?php endif; ?>
            </form>

            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php $imageUrl = resolveProductImageUrl($product['imagePath'] ?? ''); ?>
                    <article class="product-card">
                        <button type="button" class="product-add" data-product-id="<?php echo (int) $product['productID']; ?>">
                            <div class="product-media">
                                <?php if ($imageUrl !== ''): ?>
                                    <img src="<?php echo h($imageUrl); ?>" alt="<?php echo h($product['productName']); ?>">
                                <?php else: ?>
                                    <span>RX</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h2><?php echo h($product['productName']); ?></h2>
                                <p><?php echo h($product['category']); ?></p>
                                <div class="product-meta">
                                    <strong>RM <?php echo number_format((float) $product['price'], 2); ?></strong>
                                    <span><?php echo (int) $product['stockQuantity']; ?> stock</span>
                                </div>
                            </div>
                        </button>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <div class="empty-products">No available products found.</div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="pos-cart">
            <div class="cart-header">
                <div>
                    <h2>Current Sale</h2>
                    <p id="cartCount">0 items</p>
                </div>
                <button type="button" id="clearCart" class="ghost-button">Clear</button>
            </div>

            <div id="messageBox" class="message-box" hidden></div>
            <div id="cartItems" class="cart-items"></div>

            <section class="payment-card">
                <div class="total-row">
                    <span>Total</span>
                    <strong id="cartTotal">RM 0.00</strong>
                </div>
                <label for="paymentMethod">Payment</label>
                <select id="paymentMethod">
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                </select>
                <label for="amountPaid">Amount Paid</label>
                <input id="amountPaid" type="number" min="0" step="0.01" placeholder="0.00">
                <div class="balance-row">
                    <span>Change</span>
                    <strong id="changeDue">RM 0.00</strong>
                </div>
                <button type="button" id="checkoutButton" class="checkout-button">Complete Sale</button>
            </section>
        </aside>
    </main>
</div>

<script>
const money = new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' });
let currentTotal = 0;

function showMessage(text, ok = true) {
    const box = document.getElementById('messageBox');
    box.hidden = false;
    box.className = ok ? 'message-box success' : 'message-box error';
    box.textContent = text;
    window.clearTimeout(showMessage.timer);
    showMessage.timer = window.setTimeout(() => box.hidden = true, 3000);
}

async function postForm(url, data) {
    const body = new URLSearchParams(data);
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    });
    return response.json();
}

function renderCart(payload) {
    renderScanResult(payload.scannedProduct || null);
    if (!payload.success) {
        showMessage(payload.message || 'POS cart error.', false);
        return;
    }
    if (payload.message) {
        showMessage(payload.message, true);
    }

    currentTotal = Number(payload.total || 0);
    document.getElementById('cartCount').textContent = `${payload.count || 0} item${payload.count === 1 ? '' : 's'}`;
    document.getElementById('cartTotal').textContent = money.format(currentTotal);
    updateChange();

    const target = document.getElementById('cartItems');
    if (!payload.items || payload.items.length === 0) {
        target.innerHTML = '<div class="empty-cart">Scan or tap a product to start a sale.</div>';
        return;
    }

    target.innerHTML = payload.items.map(item => `
        <div class="cart-line">
            <div>
                <h3>${escapeHtml(item.name)}</h3>
                <p>${money.format(Number(item.price))} each</p>
            </div>
            <div class="qty-control">
                <button type="button" data-update="${item.productID}" data-qty="${Math.max(0, item.quantity - 1)}">-</button>
                <input type="number" min="1" max="${item.stock}" value="${item.quantity}" data-qty-input="${item.productID}">
                <button type="button" data-update="${item.productID}" data-qty="${item.quantity + 1}">+</button>
            </div>
            <strong>${money.format(Number(item.lineTotal))}</strong>
            <button type="button" class="remove-line" data-remove="${item.productID}">Remove</button>
        </div>
    `).join('');
}

function renderScanResult(product) {
    const target = document.getElementById('scanResult');
    if (!product) {
        target.hidden = true;
        target.innerHTML = '';
        return;
    }

    const image = product.imageUrl
        ? `<img src="${escapeHtml(product.imageUrl)}" alt="${escapeHtml(product.name)}">`
        : '<span class="scan-product-placeholder">RX</span>';
    const availability = product.available
        ? '<span class="scan-availability available">Available and added to sale</span>'
        : `<span class="scan-availability unavailable">${escapeHtml(product.availabilityMessage)}</span>`;

    target.innerHTML = `
        <div class="scan-product-image">${image}</div>
        <div class="scan-product-info">
            <strong>${escapeHtml(product.name)}</strong>
            <span>${escapeHtml(product.category)} · ${escapeHtml(product.barcode)}</span>
            <span>${money.format(Number(product.price))} · ${Number(product.stock)} in stock</span>
            ${product.description ? `<small>${escapeHtml(product.description)}</small>` : ''}
            ${availability}
        </div>`;
    target.hidden = false;
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));
}

async function loadCart() {
    renderCart(await fetch('cart.php?action=summary').then(r => r.json()));
}

async function addBarcode() {
    const input = document.getElementById('barcodeInput');
    const barcode = input.value.trim();
    if (!barcode) return;
    renderCart(await postForm('cart.php', { action: 'add_barcode', barcode }));
    input.value = '';
    input.focus();
}

function updateChange() {
    const paid = Number(document.getElementById('amountPaid').value || 0);
    const change = Math.max(0, paid - currentTotal);
    document.getElementById('changeDue').textContent = money.format(change);
}

document.getElementById('barcodeInput').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        addBarcode();
    }
});
document.getElementById('scanButton').addEventListener('click', addBarcode);
document.getElementById('amountPaid').addEventListener('input', updateChange);

document.querySelectorAll('.product-add').forEach(button => {
    button.addEventListener('click', async () => {
        renderCart(await postForm('cart.php', { action: 'add', product_id: button.dataset.productId }));
    });
});

document.getElementById('cartItems').addEventListener('click', async (event) => {
    const updateButton = event.target.closest('[data-update]');
    const removeButton = event.target.closest('[data-remove]');
    if (updateButton) {
        renderCart(await postForm('cart.php', {
            action: 'update',
            product_id: updateButton.dataset.update,
            quantity: updateButton.dataset.qty
        }));
    }
    if (removeButton) {
        renderCart(await postForm('cart.php', { action: 'remove', product_id: removeButton.dataset.remove }));
    }
});

document.getElementById('cartItems').addEventListener('change', async (event) => {
    const input = event.target.closest('[data-qty-input]');
    if (input) {
        renderCart(await postForm('cart.php', {
            action: 'update',
            product_id: input.dataset.qtyInput,
            quantity: input.value
        }));
    }
});

document.getElementById('clearCart').addEventListener('click', async () => {
    renderCart(await postForm('cart.php', { action: 'clear' }));
});

document.getElementById('checkoutButton').addEventListener('click', async () => {
    const response = await fetch('checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            payment_method: document.getElementById('paymentMethod').value,
            amount_paid: document.getElementById('amountPaid').value
        })
    });
    const payload = await response.json();
    if (!payload.success) {
        showMessage(payload.message || 'Unable to complete sale.', false);
        return;
    }
    window.location.href = `receipt.php?order_id=${payload.order_id}`;
});

loadCart();
document.getElementById('barcodeInput').focus();
</script>
</body>
</html>
