<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

function ensurePosSchema(mysqli $conn): void
{
    $columns = [
        'cashier_user_id' => "ALTER TABLE customer_orders ADD COLUMN cashier_user_id INT(10) UNSIGNED NULL AFTER customer_id",
        'transaction_datetime' => "ALTER TABLE customer_orders ADD COLUMN transaction_datetime DATETIME NULL AFTER order_date",
        'amount_paid' => "ALTER TABLE customer_orders ADD COLUMN amount_paid DECIMAL(12,2) NULL AFTER paymentMethod",
        'change_amount' => "ALTER TABLE customer_orders ADD COLUMN change_amount DECIMAL(12,2) NULL AFTER amount_paid",
        'order_source' => "ALTER TABLE customer_orders ADD COLUMN order_source VARCHAR(30) NOT NULL DEFAULT 'Online' AFTER change_amount",
    ];

    foreach ($columns as $column => $sql) {
        $exists = $conn->query("SHOW COLUMNS FROM customer_orders LIKE '{$column}'");
        if ($exists && $exists->num_rows === 0) {
            $conn->query($sql);
        }
    }
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

ensurePosSchema($conn);

$orderId = (int) ($_GET['order_id'] ?? 0);
$stmt = $conn->prepare(
    "SELECT co.*, u.firstName, u.lastName, u.userName
     FROM customer_orders co
     LEFT JOIN users u ON co.cashier_user_id = u.userID
     WHERE co.order_id = ? AND co.order_source = 'POS'
     LIMIT 1"
);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo 'POS receipt not found.';
    exit;
}

$itemStmt = $conn->prepare('SELECT product_name, quantity, unit_price FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC');
$itemStmt->bind_param('i', $orderId);
$itemStmt->execute();
$items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

$cashierName = trim(($order['firstName'] ?? '') . ' ' . ($order['lastName'] ?? ''));
if ($cashierName === '') {
    $cashierName = $order['userName'] ?? 'Staff Cashier';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Receipt <?php echo h($order['order_code']); ?></title>
    <link rel="stylesheet" href="css/pos.css">
</head>
<body class="receipt-body">
<main class="receipt-card">
    <header class="receipt-header">
        <img src="../logo-transparent.png" alt="Essen Pharmacy">
        <h1>Essen Pharmacy</h1>
        <p>Point of Sale Receipt</p>
    </header>

    <section class="receipt-meta">
        <div><span>Transaction ID</span><strong><?php echo h($order['order_code']); ?></strong></div>
        <div><span>Date</span><strong><?php echo h($order['transaction_datetime'] ?: $order['created_at']); ?></strong></div>
        <div><span>Cashier</span><strong><?php echo h($cashierName); ?></strong></div>
        <div><span>Payment</span><strong><?php echo h($order['paymentMethod']); ?></strong></div>
    </section>

    <table class="receipt-table">
        <thead>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo h($item['product_name']); ?></td>
                <td><?php echo (int) $item['quantity']; ?></td>
                <td>RM <?php echo number_format((float) $item['unit_price'], 2); ?></td>
                <td>RM <?php echo number_format((float) $item['unit_price'] * (int) $item['quantity'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <section class="receipt-total">
        <div><span>Total</span><strong>RM <?php echo number_format((float) $order['total'], 2); ?></strong></div>
        <div><span>Paid</span><strong>RM <?php echo number_format((float) ($order['amount_paid'] ?? $order['total']), 2); ?></strong></div>
        <div><span>Change</span><strong>RM <?php echo number_format((float) ($order['change_amount'] ?? 0), 2); ?></strong></div>
    </section>

    <div class="receipt-actions">
        <button type="button" onclick="window.print()">Print Receipt</button>
        <a href="dashboard.php">New Sale</a>
    </div>
</main>
</body>
</html>
