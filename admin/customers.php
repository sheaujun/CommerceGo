<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$sql = "SELECT c.customer_id, c.customer_code, c.name, c.email, c.phone, c.address, c.join_date, c.status,
               COUNT(o.order_id) AS total_orders,
               COALESCE(SUM(o.total), 0.00) AS total_spent
        FROM customers c
        LEFT JOIN customer_orders o ON c.customer_id = o.customer_id
        GROUP BY c.customer_id
        ORDER BY c.customer_id ASC";

$result = $conn->query($sql);
$customers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['total_orders'] = (int)$row['total_orders'];
        $row['total_spent'] = (float)$row['total_spent'];
        $customers[] = $row;
    }
}

$orderSql = "SELECT order_id, order_code, customer_id, order_date, total, items, status
             FROM customer_orders
             ORDER BY order_date DESC";
$orderResult = $conn->query($orderSql);
$customerOrders = [];
if ($orderResult) {
    while ($order = $orderResult->fetch_assoc()) {
        $customerOrders[$order['customer_id']][] = $order;
    }
}

$totalCustomers = count($customers);
$activeCustomers = count(array_filter($customers, fn($customer) => $customer['status'] === 'active'));
$totalRevenue = array_reduce($customers, fn($sum, $customer) => $sum + $customer['total_spent'], 0.0);
$totalOrders = array_reduce($customers, fn($sum, $customer) => $sum + $customer['total_orders'], 0);
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0.0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Customers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/admin-customers.css">
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
            <a href="products.php" class="nav-item">
                <span class="nav-icon">💊</span>
                <span class="nav-label">Products</span>
            </a>
            <a href="approvals.php" class="nav-item">
                <span class="nav-icon">✅</span>
                <span class="nav-label">Approvals</span>
            </a>
            <a href="customers.php" class="nav-item active">
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
        <header class="main-header">
            <div>
                <h1>Customers</h1>
                <p>View and manage customer information</p>
            </div>
        </header>

        <section class="metric-grid customers-metrics">
            <div class="metric-card">
                <div class="metric-icon">👥</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($totalCustomers); ?></div>
                    <div class="metric-label">Total Customers</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">✅</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo number_format($activeCustomers); ?></div>
                    <div class="metric-label">Active Customers</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">💲</div>
                <div class="metric-main">
                    <div class="metric-value">RM <?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="metric-label">Total Revenue</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">📈</div>
                <div class="metric-main">
                    <div class="metric-value">RM <?php echo number_format($avgOrderValue, 2); ?></div>
                    <div class="metric-label">Avg Order Value</div>
                </div>
            </div>
        </section>

        <section class="table-card">
            <div class="table-card-header">
                <div>
                    <h2>Customer List</h2>
                </div>
                <div class="search-wrapper">
                    <span class="search-icon">🔍</span>
                    <input id="customer-search" type="text" placeholder="Search customers...">
                </div>
            </div>

            <div class="table-scroll">
                <table class="customers-table">
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Total Orders</th>
                            <th>Total Spent</th>
                            <th>Status</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customer-table-body">
                        <?php foreach ($customers as $customer): ?>
                            <tr data-search="<?php echo htmlspecialchars(strtolower($customer['customer_code'] . ' ' . $customer['name'] . ' ' . $customer['email'])); ?>">
                                <td class="font-medium"><?php echo htmlspecialchars($customer['customer_code']); ?></td>
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                <td><?php echo (int)$customer['total_orders']; ?></td>
                                <td>RM <?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td>
                                    <span class="status-pill <?php echo $customer['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo htmlspecialchars($customer['status']); ?>
                                    </span>
                                </td>
                                <td class="actions-column">
                                    <button type="button" class="view-button" data-customer-id="<?php echo (int)$customer['customer_id']; ?>">
                                        View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<div id="customer-modal" class="modal-overlay hidden">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <h2>Customer Details</h2>
                <p class="modal-subtitle" id="modal-customer-code"></p>
            </div>
            <button type="button" class="modal-close" id="modal-close">×</button>
        </div>

        <div class="modal-body">
            <div class="modal-grid">
                <div class="info-card">
                    <h3>Personal Information</h3>
                    <div class="info-row"><span class="info-label">Name</span><span id="modal-name"></span></div>
                    <div class="info-row"><span class="info-label">Email</span><span id="modal-email"></span></div>
                    <div class="info-row"><span class="info-label">Phone</span><span id="modal-phone"></span></div>
                    <div class="info-row"><span class="info-label">Address</span><span id="modal-address"></span></div>
                    <div class="info-row"><span class="info-label">Member since</span><span id="modal-join-date"></span></div>
                </div>
                <div class="info-card">
                    <h3>Order Summary</h3>
                    <div class="info-row"><span class="info-label">Total Orders</span><span id="modal-total-orders"></span></div>
                    <div class="info-row"><span class="info-label">Total Spent</span><span id="modal-total-spent"></span></div>
                    <div class="info-row"><span class="info-label">Avg Order Value</span><span id="modal-avg-order"></span></div>
                    <div class="info-row"><span class="info-label">Status</span><span id="modal-status"></span></div>
                </div>
            </div>

            <div class="orders-card">
                <h3>Recent Orders</h3>
                <div class="orders-table-wrap">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="orders-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const customers = <?php echo json_encode($customers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const customerOrders = <?php echo json_encode($customerOrders, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

const searchInput = document.getElementById('customer-search');
const tableBody = document.getElementById('customer-table-body');
const modalOverlay = document.getElementById('customer-modal');
const modalClose = document.getElementById('modal-close');

const modalFields = {
    code: document.getElementById('modal-customer-code'),
    name: document.getElementById('modal-name'),
    email: document.getElementById('modal-email'),
    phone: document.getElementById('modal-phone'),
    address: document.getElementById('modal-address'),
    joinDate: document.getElementById('modal-join-date'),
    totalOrders: document.getElementById('modal-total-orders'),
    totalSpent: document.getElementById('modal-total-spent'),
    avgOrder: document.getElementById('modal-avg-order'),
    status: document.getElementById('modal-status'),
};

searchInput.addEventListener('input', () => {
    const query = searchInput.value.trim().toLowerCase();
    Array.from(tableBody.querySelectorAll('tr')).forEach(row => {
        const text = row.dataset.search || '';
        row.style.display = text.includes(query) ? '' : 'none';
    });
});

const openCustomerModal = (customerId) => {
    const customer = customers.find(c => Number(c.customer_id) === Number(customerId));
    if (!customer) return;

    modalFields.code.textContent = customer.customer_code;
    modalFields.name.textContent = customer.name;
    modalFields.email.textContent = customer.email;
    modalFields.phone.textContent = customer.phone;
    modalFields.address.textContent = customer.address || '-';
    modalFields.joinDate.textContent = customer.join_date;
    modalFields.totalOrders.textContent = customer.total_orders;
    modalFields.totalSpent.textContent = `RM ${customer.total_spent.toFixed(2)}`;
    const avg = customer.total_orders > 0 ? customer.total_spent / customer.total_orders : 0;
    modalFields.avgOrder.textContent = `RM ${avg.toFixed(2)}`;
    modalFields.status.textContent = customer.status;
    modalFields.status.className = customer.status === 'active' ? 'status-pill status-active' : 'status-pill status-inactive';

    const orders = customerOrders[customerId] || [];
    const ordersTbody = document.getElementById('orders-tbody');
    ordersTbody.innerHTML = '';
    if (orders.length === 0) {
        ordersTbody.innerHTML = '<tr><td colspan="5" class="empty-row">No orders found.</td></tr>';
    } else {
        orders.forEach(order => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${order.order_code}</td>
                <td>${order.order_date}</td>
                <td>${order.items}</td>
                <td>RM ${parseFloat(order.total).toFixed(2)}</td>
                <td><span class="order-status ${order.status.toLowerCase()}">${order.status}</span></td>
            `;
            ordersTbody.appendChild(row);
        });
    }

    modalOverlay.classList.remove('hidden');
};

modalClose.addEventListener('click', () => modalOverlay.classList.add('hidden'));
modalOverlay.addEventListener('click', event => {
    if (event.target === modalOverlay) {
        modalOverlay.classList.add('hidden');
    }
});

document.querySelectorAll('.view-button').forEach(button => {
    button.addEventListener('click', () => openCustomerModal(button.dataset.customerId));
});
</script>
</body>
</html>
