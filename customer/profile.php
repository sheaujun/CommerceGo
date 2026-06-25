<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login.php');
    exit;
}

function h($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$userId = $_SESSION['userID'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['profile_update'])) {
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phoneNo = trim($_POST['phoneNo'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($firstName === '' || $lastName === '') {
            $errors[] = 'First and last name are required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Failed to upload image. Please try again.';
            } else {
                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                ];
                $fileType = mime_content_type($_FILES['avatar']['tmp_name']);
                if (!isset($allowedTypes[$fileType])) {
                    $errors[] = 'Only JPG, PNG, and GIF images are allowed.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/avatars';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                        $errors[] = 'Unable to create upload folder.';
                    } else {
                        $extension = $allowedTypes[$fileType];
                        $fileName = $userId . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
                        $destination = $uploadDir . '/' . $fileName;
                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                            $avatarPath = 'uploads/avatars/' . $fileName;
                        } else {
                            $errors[] = 'Unable to save uploaded image.';
                        }
                    }
                }
            }
        }

        if (empty($errors)) {
            $fullName = trim($firstName . ' ' . $lastName);
            $sql = 'UPDATE users SET firstName = ?, lastName = ?, email = ?, phoneNo = ?, address = ?, updated_at = NOW() WHERE userID = ?';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssssi', $firstName, $lastName, $email, $phoneNo, $address, $userId);
            if ($stmt->execute()) {
                $stmt->close();
                $customerStmt = $conn->prepare('SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
                if ($customerStmt) {
                    $customerStmt->bind_param('i', $userId);
                    $customerStmt->execute();
                    $customerResult = $customerStmt->get_result();
                    $customerRow = $customerResult->fetch_assoc();
                    $customerStmt->close();

                    if ($customerRow) {
                        $updateCustStmt = $conn->prepare('UPDATE customers SET name = ?, email = ?, phone = ?, address = ? WHERE customer_id = ?');
                        if ($updateCustStmt) {
                            $updateCustStmt->bind_param('ssssi', $fullName, $email, $phoneNo, $address, $customerRow['customer_id']);
                            $updateCustStmt->execute();
                            $updateCustStmt->close();
                        }
                    }
                }

                if ($avatarPath !== null) {
                    $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE userID = ?');
                    $stmt->bind_param('si', $avatarPath, $userId);
                    $stmt->execute();
                    $stmt->close();
                }

                $success = 'Profile updated successfully.';
            } else {
                $errors[] = 'Unable to update your profile at this time.';
                $stmt->close();
            }
        }
    }

    if (isset($_POST['security_update'])) {
        $currentPassword = $_POST['currentPassword'] ?? '';
        $newPassword = $_POST['newPassword'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';

        $stmt = $conn->prepare('SELECT password FROM users WHERE userID = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($currentHash);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($currentPassword, $currentHash)) {
            $errors[] = 'Current password is incorrect.';
        }
        if ($newPassword === '' || strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE userID = ?');
            $stmt->bind_param('si', $newHash, $userId);
            if ($stmt->execute()) {
                $success = 'Password changed successfully.';
            } else {
                $errors[] = 'Unable to update your password.';
            }
            $stmt->close();
        }
    }
}

$user = [
    'userID' => $userId,
    'userName' => $_SESSION['userName'] ?? '',
    'firstName' => '',
    'lastName' => '',
    'email' => '',
    'phoneNo' => '',
    'address' => '',
    'avatar' => '',
    'created_at' => '',
];

$stmt = $conn->prepare('SELECT userName, firstName, lastName, email, phoneNo, address, avatar, created_at FROM users WHERE userID = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($userName, $firstName, $lastName, $email, $phoneNo, $address, $avatar, $createdAt);
if ($stmt->fetch()) {
    $user['userName'] = $userName;
    $user['firstName'] = $firstName;
    $user['lastName'] = $lastName;
    $user['email'] = $email;
    $user['phoneNo'] = $phoneNo;
    $user['address'] = $address;
    $user['avatar'] = $avatar;
    $user['created_at'] = $createdAt;
}
$stmt->close();

$fullName = trim($user['firstName'] . ' ' . $user['lastName']);
$joinDate = $user['created_at'] ? date('Y-m-d', strtotime($user['created_at'])) : '';

$customerId = null;
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

$orderStats = [
    'total_orders' => 0,
    'delivered' => 0,
    'in_progress' => 0,
    'total_spent' => 0.0,
];

if ($customerId !== null) {
    $orderStmt = $conn->prepare('SELECT status, total FROM customer_orders WHERE customer_id = ?');
    if ($orderStmt) {
        $orderStmt->bind_param('i', $customerId);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        while ($orderRow = $orderResult->fetch_assoc()) {
            $orderStats['total_orders']++;
            $status = $orderRow['status'];
            $total = floatval($orderRow['total']);
            if ($status === 'Delivered') {
                $orderStats['delivered']++;
            }
            if (in_array($status, ['Pending', 'Processing', 'Shipped'], true)) {
                $orderStats['in_progress']++;
            }
            if ($status !== 'Cancelled') {
                $orderStats['total_spent'] += $total;
            }
        }
        $orderStmt->close();
    }
}

if ($orderStats['total_spent'] >= 500) {
    $memberLabel = 'Gold Member';
    $memberDescription = 'Enjoy 5% off all purchases';
} elseif ($orderStats['total_spent'] >= 250) {
    $memberLabel = 'Silver Member';
    $memberDescription = 'Earn 3% cashback on orders';
} elseif ($orderStats['total_spent'] > 0) {
    $memberLabel = 'Bronze Member';
    $memberDescription = 'Earn points on every purchase';
} else {
    $memberLabel = 'New Member';
    $memberDescription = 'Start shopping to unlock member rewards';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/customer-dashboard.css">
    <link rel="stylesheet" href="css/customer-profile.css">
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
                <a href="find-us.php" class="nav-item">
                    <span class="nav-icon">&#128205;</span>
                    <span>Find Us</span>
                </a>
                <a href="support-chat.php" class="nav-item">
                    <span class="nav-icon">💬</span>
                    <span>Support Chat</span>
                </a>
                <a href="profile.php" class="nav-item active" aria-current="page">
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

    <main class="main-panel">
        <header class="page-header">
            <div>
                <p class="eyebrow">Profile</p>
                <h2>Manage your profile and account settings.</h2>
            </div>
            <button type="button" id="sidebar-toggle" class="sidebar-toggle" aria-label="Toggle sidebar" aria-pressed="false">
                <span class="toggle-icon">☰</span>
            </button>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="form-alert form-alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo h($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="form-alert form-alert-success">
                <p><?php echo h($success); ?></p>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <div class="profile-left">
                <section class="profile-card">
                    <div class="profile-avatar">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo h($user['avatar']); ?>" alt="Profile avatar">
                        <?php else: ?>
                            <span><?php
                                $initials = '';
                                foreach (explode(' ', trim($fullName)) as $part) {
                                    if ($part !== '') {
                                        $initials .= strtoupper($part[0]);
                                    }
                                }
                                echo h($initials ?: 'C');
                            ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-details">
                        <h2><?php echo h($fullName ?: $user['userName']); ?></h2>
                        <p class="profile-email"><?php echo h($user['email']); ?></p>
                        <div class="profile-badge">Customer</div>
                    </div>

                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <span class="profile-info-label">Username</span>
                            <span><?php echo h($user['userName']); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">Phone</span>
                            <span><?php echo h($user['phoneNo'] ?: '-'); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">Joined</span>
                            <span><?php echo h($joinDate ?: '-'); ?></span>
                        </div>
                    </div>
                </section>

                <section class="summary-card">
                    <div class="summary-card-header">
                        <h3>Account Summary</h3>
                    </div>
                    <div class="summary-total">
                        <span class="summary-value"><?php echo intval($orderStats['total_orders']); ?></span>
                        <span class="summary-label">Total Orders</span>
                    </div>
                    <div class="summary-grid">
                        <div class="summary-item summary-item-success">
                            <span><?php echo intval($orderStats['delivered']); ?></span>
                            <span>Delivered</span>
                        </div>
                        <div class="summary-item summary-item-info">
                            <span><?php echo intval($orderStats['in_progress']); ?></span>
                            <span>In Progress</span>
                        </div>
                    </div>
                    <div class="summary-footer">
                        <span>Total Spent</span>
                        <strong>RM <?php echo number_format($orderStats['total_spent'], 2); ?></strong>
                    </div>
                </section>

                <section class="member-card">
                    <div class="member-card-header">
                        <span class="member-avatar"><?php echo h(strtoupper($memberLabel[0])); ?></span>
                        <div>
                            <h3><?php echo h($memberLabel); ?></h3>
                            <p><?php echo h($memberDescription); ?></p>
                        </div>
                    </div>
                </section>
            </div>

            <section class="account-card">
                <div class="account-card-header">
                    <h2>Account Settings</h2>
                    <p>Update your profile and password securely.</p>
                </div>

                <div class="tabs">
                    <button type="button" class="tab-trigger active" data-tab="profile-tab">Profile</button>
                    <button type="button" class="tab-trigger" data-tab="security-tab">Security</button>
                </div>

                <div id="profile-tab" class="tab-panel active">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="profile_update" value="1">
                        <div class="field-row">
                            <div class="field-group">
                                <label for="firstName">First Name</label>
                                <input id="firstName" name="firstName" type="text" value="<?php echo h($user['firstName']); ?>" required>
                            </div>
                            <div class="field-group">
                                <label for="lastName">Last Name</label>
                                <input id="lastName" name="lastName" type="text" value="<?php echo h($user['lastName']); ?>" required>
                            </div>
                        </div>
                        <div class="field-row">
                            <div class="field-group">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" value="<?php echo h($user['email']); ?>" required>
                            </div>
                            <div class="field-group">
                                <label for="phoneNo">Phone</label>
                                <input id="phoneNo" name="phoneNo" type="text" value="<?php echo h($user['phoneNo']); ?>">
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="address">Address</label>
                            <input id="address" name="address" type="text" value="<?php echo h($user['address']); ?>">
                        </div>
                        <div class="field-group">
                            <label for="avatar">Profile Photo</label>
                            <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/gif">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>

                <div id="security-tab" class="tab-panel">
                    <form method="post">
                        <input type="hidden" name="security_update" value="1">
                        <div class="field-group">
                            <label for="currentPassword">Current Password</label>
                            <input id="currentPassword" name="currentPassword" type="password" required>
                        </div>
                        <div class="field-group">
                            <label for="newPassword">New Password</label>
                            <input id="newPassword" name="newPassword" type="password" required>
                        </div>
                        <div class="field-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input id="confirmPassword" name="confirmPassword" type="password" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Change password</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>
</div>
<script>
    document.querySelectorAll('.tab-trigger').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('.tab-trigger').forEach(function (btn) {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-panel').forEach(function (panel) {
                panel.classList.remove('active');
            });
            button.classList.add('active');
            document.getElementById(button.dataset.tab).classList.add('active');
        });
    });
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const customerLayout = document.querySelector('.customer-layout');
    if (sidebarToggle && customerLayout) {
        sidebarToggle.addEventListener('click', function () {
            const collapsed = customerLayout.classList.toggle('collapsed');
            sidebarToggle.setAttribute('aria-pressed', collapsed.toString());
        });
    }
</script>
</body>
</html>
