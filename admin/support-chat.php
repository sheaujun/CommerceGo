<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
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

$searchQuery = trim($_GET['search'] ?? '');
$selectedContactId = intval($_GET['contact_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedContactId = intval($_POST['contact_id'] ?? 0);
    $searchQuery = trim($_POST['search'] ?? '');
    $newMessage = trim($_POST['message'] ?? '');

    if ($selectedContactId > 0 && $newMessage !== '') {
        $insertStmt = $conn->prepare(
            'INSERT INTO support_chat (customer_id, sender, message, created_at, is_read) VALUES (?, "admin", ?, NOW(), 1)'
        );
        if ($insertStmt) {
            $insertStmt->bind_param('is', $selectedContactId, $newMessage);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }

    $redirect = 'support-chat.php?contact_id=' . $selectedContactId;
    if ($searchQuery !== '') {
        $redirect .= '&search=' . urlencode($searchQuery);
    }
    header('Location: ' . $redirect);
    exit;
}

$contacts = [];
$contactQuery = "SELECT c.customer_id, c.name,
    (SELECT message FROM support_chat WHERE customer_id = c.customer_id ORDER BY created_at DESC LIMIT 1) AS last_message,
    (SELECT created_at FROM support_chat WHERE customer_id = c.customer_id ORDER BY created_at DESC LIMIT 1) AS last_active,
    (SELECT COUNT(*) FROM support_chat WHERE customer_id = c.customer_id AND sender = 'customer' AND is_read = 0) AS unread_count
    FROM customers c
    WHERE EXISTS (SELECT 1 FROM support_chat s WHERE s.customer_id = c.customer_id)";

if ($searchQuery !== '') {
    $contactQuery .= ' AND c.name LIKE ?';
}
$contactQuery .= ' ORDER BY last_active DESC';

$stmt = $conn->prepare($contactQuery);
if ($stmt) {
    if ($searchQuery !== '') {
        $like = '%' . $searchQuery . '%';
        $stmt->bind_param('s', $like);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $contacts[] = $row;
    }
    $stmt->close();
}

$selectedContact = null;
if ($selectedContactId > 0) {
    foreach ($contacts as $contact) {
        if ($contact['customer_id'] === $selectedContactId) {
            $selectedContact = $contact;
            break;
        }
    }
}

if ($selectedContact === null && count($contacts) > 0) {
    $selectedContact = $contacts[0];
    $selectedContactId = intval($contacts[0]['customer_id']);
}

$messages = [];
if ($selectedContact !== null) {
    $readStmt = $conn->prepare(
        'UPDATE support_chat SET is_read = 1 WHERE customer_id = ? AND sender = "customer" AND is_read = 0'
    );
    if ($readStmt) {
        $readStmt->bind_param('i', $selectedContactId);
        $readStmt->execute();
        $readStmt->close();
    }

    $messageStmt = $conn->prepare(
        'SELECT chat_id, sender, message, created_at FROM support_chat WHERE customer_id = ? ORDER BY created_at ASC'
    );
    if ($messageStmt) {
        $messageStmt->bind_param('i', $selectedContactId);
        $messageStmt->execute();
        $messageResult = $messageStmt->get_result();
        while ($row = $messageResult->fetch_assoc()) {
            $messages[] = $row;
        }
        $messageStmt->close();
    }
}

function isContactOnline(string $lastActive): bool
{
    $timestamp = strtotime($lastActive);
    if ($timestamp === false) {
        return false;
    }
    return (time() - $timestamp) < 600;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Support Chat</title>
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
    <link rel="stylesheet" href="css/admin-chat.css">
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
            <a href="products.php" class="nav-item">
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
            <a href="support-chat.php" class="nav-item active">
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
                <h1>Support Chat</h1>
                <p>Manage customer conversations and inquiries.</p>
            </div>
        </header>

        <section class="support-chat-page">
            <div class="chat-shell">
                <div class="contact-panel">
                    <div class="search-bar">
                        <form method="get" action="support-chat.php" class="search-form">
                            <input
                                type="text"
                                name="search"
                                value="<?php echo escape($searchQuery); ?>"
                                placeholder="Search contacts..."
                                aria-label="Search contacts"
                            />
                        </form>
                    </div>

                    <div class="contacts-list">
                        <?php if (count($contacts) === 0): ?>
                            <div class="empty-state">
                                No conversations found.
                            </div>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                                <?php $isSelected = $selectedContact && intval($contact['customer_id']) === intval($selectedContact['customer_id']); ?>
                                <a
                                    href="support-chat.php?contact_id=<?php echo intval($contact['customer_id']); ?>&search=<?php echo urlencode($searchQuery); ?>"
                                    class="contact-item<?php echo $isSelected ? ' selected' : ''; ?>"
                                >
                                    <div class="avatar-pill">
                                        <?php echo implode('', array_map(fn($namePart) => $namePart[0], explode(' ', $contact['name']))); ?>
                                        <?php if (isContactOnline($contact['last_active'])): ?>
                                            <span class="online-dot"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="contact-summary">
                                        <div class="contact-meta-row">
                                            <span class="contact-name"><?php echo escape($contact['name']); ?></span>
                                            <span class="contact-time"><?php echo escape(formatRelativeTime($contact['last_active'] ?: '')); ?></span>
                                        </div>
                                        <p class="contact-preview"><?php echo escape($contact['last_message'] ?? 'No messages yet'); ?></p>
                                    </div>
                                    <?php if (intval($contact['unread_count']) > 0): ?>
                                        <span class="badge"><?php echo intval($contact['unread_count']); ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chat-panel">
                    <?php if ($selectedContact !== null): ?>
                        <div class="chat-header">
                            <div class="avatar-pill large">
                                <?php echo implode('', array_map(fn($namePart) => $namePart[0], explode(' ', $selectedContact['name']))); ?>
                            </div>
                            <div>
                                <div class="chat-title"><?php echo escape($selectedContact['name']); ?></div>
                                <div class="chat-subtitle"><?php echo isContactOnline($selectedContact['last_active']) ? 'Online' : 'Offline'; ?></div>
                            </div>
                        </div>

                        <div class="chat-body">
                            <?php if (count($messages) === 0): ?>
                                <div class="empty-state">
                                    No messages yet. Send the first response.
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <?php $fromContact = $message['sender'] === 'customer'; ?>
                                    <div class="message-row <?php echo $fromContact ? 'contact' : 'admin'; ?>">
                                        <div class="msg-bubble <?php echo $fromContact ? 'contact' : 'admin'; ?>">
                                            <p><?php echo escape($message['message']); ?></p>
                                            <div class="message-time"><?php echo escape(formatTime($message['created_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <form method="post" class="composer">
                            <input type="hidden" name="contact_id" value="<?php echo intval($selectedContactId); ?>">
                            <input type="hidden" name="search" value="<?php echo escape($searchQuery); ?>">
                            <input type="text" name="message" placeholder="Type a message..." autocomplete="off" />
                            <button type="submit">Send</button>
                        </form>
                    <?php else: ?>
                        <div class="empty-state full-height">
                            Select a conversation to start chatting.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
