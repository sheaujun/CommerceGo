<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$createContactsSql = <<<SQL
CREATE TABLE IF NOT EXISTS `support_chat` (
  `chat_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `sender` enum('admin','customer') NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`chat_id`),
  KEY `idx_support_customer` (`customer_id`),
  CONSTRAINT `fk_support_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
$conn->query($createContactsSql);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatRelativeTime(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return intval($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        $hours = intval($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '');
    }

    return date('M j', $timestamp);
}

function formatTime(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }

    return date('g:i A', $timestamp);
}

$aiSuggestions = [
    [
        'question' => 'How can I check my order status?',
        'answer' => 'I can check your latest order progress here. Select this question, then choose an order or type an order number such as ORD001.',
        'action' => 'orders',
    ],
    [
        'question' => 'Can I ask about medicine usage?',
        'answer' => 'You can ask general product questions here. For dosage, side effects, allergies, pregnancy, or urgent symptoms, please consult our pharmacist or a doctor before taking medicine.',
    ],
    [
        'question' => 'What if a product is out of stock?',
        'answer' => 'Message support with the product name. Our team can confirm availability, suggest a suitable alternative, or let you know when the item may be restocked.',
    ],
    [
        'question' => 'How do I change or cancel an order?',
        'answer' => 'Contact support as soon as possible with your order details. If the order has not been processed yet, the admin team can help review cancellation or change options.',
    ],
    [
        'question' => 'I have a payment problem',
        'answer' => 'If payment failed or you were charged but the order did not update, keep your payment reference ready and message support. The admin team will verify the transaction and order status.',
    ],
];

$userId = intval($_SESSION['userID']);
$userEmail = $_SESSION['email'] ?? '';

$customerId = null;

// Try to find the customer profile by user_id first, then by email.
$customerStmt = $conn->prepare('SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
if ($customerStmt) {
    $customerStmt->bind_param('i', $userId);
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    if ($row = $customerResult->fetch_assoc()) {
        $customerId = intval($row['customer_id']);
    }
    $customerStmt->close();
}

if ($customerId === null && $userEmail !== '') {
    $customerStmt = $conn->prepare('SELECT customer_id FROM customers WHERE email = ? LIMIT 1');
    if ($customerStmt) {
        $customerStmt->bind_param('s', $userEmail);
        $customerStmt->execute();
        $customerResult = $customerStmt->get_result();
        if ($row = $customerResult->fetch_assoc()) {
            $customerId = intval($row['customer_id']);
        }
        $customerStmt->close();
    }
}

if ($customerId === null) {
    // If the customer profile does not yet exist, build it from the users table.
    $userStmt = $conn->prepare('SELECT firstName, lastName, email, phoneNo FROM users WHERE userID = ? LIMIT 1');
    if ($userStmt) {
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userRow = $userResult->fetch_assoc()) {
            $fullName = trim(($userRow['firstName'] ?? '') . ' ' . ($userRow['lastName'] ?? ''));
            $customerCode = 'CUST' . str_pad($userId, 3, '0', STR_PAD_LEFT);
            $insertStmt = $conn->prepare(
                'INSERT INTO customers (user_id, customer_code, name, email, phone, status)
                 VALUES (?, ?, ?, ?, ?, "active")'
            );
            if ($insertStmt) {
                $insertStmt->bind_param('issss', $userId, $customerCode, $fullName, $userRow['email'], $userRow['phoneNo']);
                $insertStmt->execute();
                $customerId = intval($conn->insert_id);
                $insertStmt->close();
            }
        }
        $userStmt->close();
    }
}

if ($customerId === null) {
    header('Location: dashboard.php');
    exit;
}

$customerOrders = [];
$orderStmt = $conn->prepare(
    'SELECT o.order_id, o.order_code, o.order_date, o.total, o.items, o.status, o.paymentMethod, o.updated_at
     FROM customer_orders o
     WHERE o.customer_id = ? AND o.status NOT IN ("Delivered", "Cancelled")
     ORDER BY o.created_at DESC, o.order_id DESC
     LIMIT 8'
);
if ($orderStmt) {
    $orderStmt->bind_param('i', $customerId);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    while ($order = $orderResult->fetch_assoc()) {
        $orderId = (int) $order['order_id'];
        $items = [];
        $itemStmt = $conn->prepare(
            'SELECT product_name, quantity, unit_price
             FROM order_items
             WHERE order_id = ?
             ORDER BY order_item_id ASC'
        );
        if ($itemStmt) {
            $itemStmt->bind_param('i', $orderId);
            $itemStmt->execute();
            $itemResult = $itemStmt->get_result();
            while ($item = $itemResult->fetch_assoc()) {
                $items[] = [
                    'name' => (string) $item['product_name'],
                    'quantity' => (int) $item['quantity'],
                    'unitPrice' => (float) $item['unit_price'],
                ];
            }
            $itemStmt->close();
        }

        $customerOrders[] = [
            'id' => $orderId,
            'code' => (string) $order['order_code'],
            'date' => (string) $order['order_date'],
            'total' => (float) $order['total'],
            'itemsCount' => (int) $order['items'],
            'status' => (string) $order['status'],
            'paymentMethod' => (string) $order['paymentMethod'],
            'updatedAt' => (string) $order['updated_at'],
            'items' => $items,
        ];
    }
    $orderStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newMessage = trim($_POST['message'] ?? '');

    if ($newMessage !== '') {
        $insertStmt = $conn->prepare(
            'INSERT INTO support_chat (customer_id, sender, message, created_at, is_read) VALUES (?, "customer", ?, NOW(), 0)'
        );
        if ($insertStmt) {
            $insertStmt->bind_param('is', $customerId, $newMessage);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }

    header('Location: support-chat.php');
    exit;
}

$messages = [];
$messageStmt = $conn->prepare(
    'SELECT chat_id, sender, message, created_at FROM support_chat WHERE customer_id = ? ORDER BY created_at ASC'
);
if ($messageStmt) {
    $messageStmt->bind_param('i', $customerId);
    $messageStmt->execute();
    $messageResult = $messageStmt->get_result();
    while ($row = $messageResult->fetch_assoc()) {
        $messages[] = $row;
    }
    $messageStmt->close();
}

// Mark customer messages as read (though they are their own messages)
$readStmt = $conn->prepare(
    'UPDATE support_chat SET is_read = 1 WHERE customer_id = ? AND sender = "admin" AND is_read = 0'
);
if ($readStmt) {
    $readStmt->bind_param('i', $customerId);
    $readStmt->execute();
    $readStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Support Chat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/customer-dashboard.css">
    <link rel="stylesheet" href="css/customer-chat.css">
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
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon">📦</span>
                    <span>Products</span>
                </a>
                <a href="cart.php" class="nav-item">
                    <span class="nav-icon">🛒</span>
                    <span>My Cart</span>
                </a>
                <a href="order-history.php" class="nav-item">
                    <span class="nav-icon">📜</span>
                    <span>Order History</span>
                </a>
                <a href="support-chat.php" class="nav-item active" aria-current="page">
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
            <a href="tel:18001234567" class="support-link">1-800-PHARMACY</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <div>
                <h1>Support Chat</h1>
                <p>Get help from our support team.</p>
            </div>
        </header>

        <section class="support-chat-page">
            <div class="chat-container">
                <div class="chat-header">
                    <div class="avatar-pill large">
                        <img src="../logo.png" alt="Essen Pharmacy" class="logo-image">
                    </div>
                    <div>
                        <div class="chat-title">Essen Pharmacy Support</div>
                        <div class="chat-subtitle">We're here to help</div>
                    </div>
                </div>

                <div class="chat-body">
                    <div class="ai-helper" id="aiHelper">
                        <div class="ai-helper-header">
                            <div class="ai-avatar">AI</div>
                            <div>
                                <div class="ai-title">AI Quick Help</div>
                                <p>Pick a question for an instant answer before chatting with admin.</p>
                            </div>
                        </div>
                        <div class="ai-question-list">
                            <?php foreach ($aiSuggestions as $index => $suggestion): ?>
                                <button
                                    type="button"
                                    class="ai-question"
                                    data-ai-question="<?php echo $index; ?>"
                                    data-ai-action="<?php echo escape($suggestion['action'] ?? 'answer'); ?>"
                                >
                                    <?php echo escape($suggestion['question']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (count($messages) === 0): ?>
                        <div class="empty-state">
                            <div class="empty-icon">💬</div>
                            <h3>Start a conversation</h3>
                            <p>Send us a message and we'll get back to you soon.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <?php $fromCustomer = $message['sender'] === 'customer'; ?>
                            <div class="message-row <?php echo $fromCustomer ? 'customer' : 'support'; ?>">
                                <div class="msg-bubble <?php echo $fromCustomer ? 'customer' : 'support'; ?>">
                                    <p><?php echo escape($message['message']); ?></p>
                                    <div class="message-time"><?php echo escape(formatTime($message['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="post" class="composer">
                    <input type="text" name="message" id="adminMessageInput" placeholder="Type your message to admin..." autocomplete="off" required />
                    <button type="submit">Send</button>
                </form>
            </div>
        </section>
    </main>
</div>
<script>
    const aiSuggestions = <?php echo json_encode($aiSuggestions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const customerOrders = <?php echo json_encode($customerOrders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const chatBody = document.querySelector('.chat-body');
    const adminMessageInput = document.getElementById('adminMessageInput');

    function currentTimeLabel() {
        return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function appendAiMessage(text, side, label) {
        if (!chatBody) return;

        const row = document.createElement('div');
        row.className = `message-row ${side}`;

        const bubble = document.createElement('div');
        bubble.className = `msg-bubble ${side === 'customer' ? 'customer' : 'support ai'}`;

        const paragraph = document.createElement('p');
        paragraph.textContent = text;

        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = `${label} - ${currentTimeLabel()}`;

        bubble.append(paragraph, time);
        row.appendChild(bubble);
        chatBody.appendChild(row);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function appendOrderChoices(orders) {
        if (!chatBody) return;

        const row = document.createElement('div');
        row.className = 'message-row support';

        const bubble = document.createElement('div');
        bubble.className = 'msg-bubble support ai order-choice-bubble';

        const title = document.createElement('p');
        title.textContent = 'Which active order would you like me to check?';

        const list = document.createElement('div');
        list.className = 'ai-order-choice-list';

        orders.forEach((order) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ai-order-choice';
            button.dataset.orderId = String(order.id);
            button.innerHTML = `<span>${order.code}</span><small>${order.status} · RM ${Number(order.total).toFixed(2)}</small>`;
            button.addEventListener('click', () => {
                appendAiMessage(`Check ${order.code}`, 'customer', 'You');
                window.setTimeout(() => appendAiMessage(formatOrderAnswer(order), 'support', 'AI Assistant'), 250);
            });
            list.appendChild(button);
        });

        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = `AI Assistant - ${currentTimeLabel()}`;

        bubble.append(title, list, time);
        row.appendChild(bubble);
        chatBody.appendChild(row);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function paymentLabel(order) {
        return order.paymentMethod && order.paymentMethod.toLowerCase().includes('cash on delivery') ? 'Payment pending on delivery' : 'Payment recorded';
    }

    function progressLabel(status) {
        const steps = ['Pending', 'Processing', 'Shipped', 'Delivered'];
        if (status === 'Cancelled') {
            return 'This order has been cancelled.';
        }

        const index = steps.indexOf(status);
        if (index === -1) {
            return `Current progress: ${status}.`;
        }

        const next = steps[index + 1];
        return next ? `Current progress: ${status}. Next step: ${next}.` : 'This order has been delivered.';
    }

    function formatOrderAnswer(order) {
        const itemText = order.items && order.items.length
            ? order.items.slice(0, 4).map((item) => `${item.quantity} x ${item.name}`).join(', ')
            : `${order.itemsCount} item(s)`;

        return [
            `Order ${order.code}`,
            `Status: ${order.status}`,
            progressLabel(order.status),
            `Order date: ${order.date}`,
            `Total: RM ${Number(order.total).toFixed(2)}`,
            `Payment: ${paymentLabel(order)} (${order.paymentMethod || 'Unknown'})`,
            `Items: ${itemText}`,
        ].join('\n');
    }

    function showOrderList() {
        if (!customerOrders.length) {
            appendAiMessage('I cannot find any active orders in your account right now. Completed and cancelled orders are not shown here. You can still check all past orders in Order History or message admin if something looks wrong.', 'support', 'AI Assistant');
            return;
        }

        if (customerOrders.length === 1) {
            appendAiMessage(formatOrderAnswer(customerOrders[0]), 'support', 'AI Assistant');
            return;
        }

        appendOrderChoices(customerOrders);
    }

    document.querySelectorAll('[data-ai-question]').forEach((button) => {
        button.addEventListener('click', () => {
            const suggestion = aiSuggestions[Number(button.dataset.aiQuestion)];
            if (!suggestion) return;

            appendAiMessage(suggestion.question, 'customer', 'You');
            window.setTimeout(() => {
                appendAiMessage(suggestion.answer, 'support', 'AI Assistant');
                if (button.dataset.aiAction === 'orders') {
                    showOrderList();
                }
            }, 250);
        });
    });

    document.querySelectorAll('.ai-question').forEach((button) => {
        button.addEventListener('dblclick', () => {
            if (adminMessageInput) {
                adminMessageInput.value = button.textContent.trim();
                adminMessageInput.focus();
            }
        });
    });
</script>
</body>
</html>
