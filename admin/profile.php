<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
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
        $firstName  = trim($_POST['firstName'] ?? '');
        $lastName   = trim($_POST['lastName'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phoneNo    = trim($_POST['phoneNo'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $department = trim($_POST['department'] ?? '');

        if ($firstName === '') {
            $errors[] = 'Full name is required.';
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
            $sql = 'UPDATE users SET firstName = ?, lastName = ?, email = ?, phoneNo = ?, address = ?, department = ?';
            $params = [$firstName, $lastName, $email, $phoneNo, $address, $department];
            $types = 'ssssss';

            if ($avatarPath !== null) {
                $sql .= ', avatar = ?';
                $params[] = $avatarPath;
                $types .= 's';
            }

            $sql .= ', updated_at = NOW() WHERE userID = ?';
            $params[] = $userId;
            $types .= 'i';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $success = 'Profile updated successfully.';
            } else {
                $errors[] = 'Unable to update your profile at this time.';
            }

            $stmt->close();
        }
    }

    if (isset($_POST['security_update'])) {
        $currentPassword = $_POST['currentPassword'] ?? '';
        $newPassword     = $_POST['newPassword'] ?? '';
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
    'department' => '',
    'role' => $_SESSION['role'] ?? 'admin',
    'created_at' => '',
    'avatar' => '',
];

$stmt = $conn->prepare('SELECT userName, firstName, lastName, email, phoneNo, address, department, avatar, created_at FROM users WHERE userID = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($userName, $firstName, $lastName, $email, $phoneNo, $address, $department, $avatar, $createdAt);
if ($stmt->fetch()) {
    $user['userName']   = $userName;
    $user['firstName']  = $firstName;
    $user['lastName']   = $lastName;
    $user['email']      = $email;
    $user['phoneNo']    = $phoneNo;
    $user['address']    = $address;
    $user['department'] = $department;
    $user['avatar']     = $avatar;
    $user['created_at'] = $createdAt;
}
$stmt->close();

$fullName = trim($user['firstName'] . ' ' . $user['lastName']);
$joinDate = $user['created_at'] ? date('Y-m-d', strtotime($user['created_at'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Profile Settings</title>
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
    <link rel="stylesheet" href="css/admin-profile.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <button type="button" id="sidebarToggle" class="sidebar-toggle" aria-pressed="false" aria-label="Toggle sidebar">☰</button>
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
            <a href="profile.php" class="nav-item active">
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
                <h1>Profile Settings</h1>
                <p>Manage your account information and preferences</p>
            </div>
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
                            echo h($initials ?: 'A');
                        ?></span>
                    <?php endif; ?>
                </div>
                <div class="profile-details">
                    <h2><?php echo h($fullName ?: 'Admin User'); ?></h2>
                    <p class="profile-email"><?php echo h($user['email']); ?></p>
                    <div class="profile-badge"><?php echo h(ucfirst($user['role'])); ?></div>
                </div>

                <div class="profile-info-list">
                    <div class="profile-info-item">
                        <span class="profile-info-label">Email</span>
                        <span><?php echo h($user['email']); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <span class="profile-info-label">Phone</span>
                        <span><?php echo h($user['phoneNo'] ?: '-'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <span class="profile-info-label">Address</span>
                        <span><?php echo h($user['address'] ?: '-'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <span class="profile-info-label">Department</span>
                        <span><?php echo h($user['department'] ?: '-'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <span class="profile-info-label">Joined</span>
                        <span><?php echo h($joinDate ?: '-'); ?></span>
                    </div>
                </div>
            </section>

            <section class="account-card">
                <div class="account-card-header">
                    <div>
                        <h2>Account Settings</h2>
                        <p>Update your personal information and security settings</p>
                    </div>
                </div>

                <div class="tabs">
                    <button type="button" class="tab-trigger active" data-tab="personal">Personal Info</button>
                    <button type="button" class="tab-trigger" data-tab="security">Security</button>
                </div>

                <div class="tab-panel active" id="personal">
                    <form method="post" action="profile.php" enctype="multipart/form-data">
                        <input type="hidden" name="profile_update" value="1">
                        <div class="field-row">
                            <div class="field-group">
                                <label for="firstName">Full Name</label>
                                <input type="text" id="firstName" name="firstName" value="<?php echo h($user['firstName']); ?>">
                            </div>
                            <div class="field-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo h($user['email']); ?>">
                            </div>
                        </div>
                        <div class="field-row">
                            <div class="field-group">
                                <label for="phoneNo">Phone Number</label>
                                <input type="text" id="phoneNo" name="phoneNo" value="<?php echo h($user['phoneNo']); ?>">
                            </div>
                            <div class="field-group">
                                <label for="department">Department</label>
                                <input type="text" id="department" name="department" value="<?php echo h($user['department']); ?>">
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" value="<?php echo h($user['address']); ?>">
                        </div>
                        <div class="field-group">
                            <label for="avatar">Upload Profile Photo</label>
                            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="field-group">
                            <div id="avatarPreviewContainer" class="avatar-preview-container" style="display:none;">
                                <label>Image Preview</label>
                                <img id="avatarPreview" class="avatar-preview-image" src="" alt="Avatar preview">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>

                <div class="tab-panel" id="security">
                    <form method="post" action="profile.php">
                        <input type="hidden" name="security_update" value="1">
                        <div class="field-group">
                            <label for="currentPassword">Current Password</label>
                            <input type="password" id="currentPassword" name="currentPassword">
                        </div>
                        <div class="field-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" id="newPassword" name="newPassword">
                        </div>
                        <div class="field-group">
                            <label for="confirmPassword">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
    document.querySelectorAll('.tab-trigger').forEach(function(button) {
        button.addEventListener('click', function() {
            document.querySelectorAll('.tab-trigger').forEach(function(item) {
                item.classList.remove('active');
            });
            document.querySelectorAll('.tab-panel').forEach(function(panel) {
                panel.classList.remove('active');
            });
            button.classList.add('active');
            document.getElementById(button.getAttribute('data-tab')).classList.add('active');
        });
    });

    const avatarInput = document.getElementById('avatar');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarPreviewContainer = document.getElementById('avatarPreviewContainer');
    const profileAvatar = document.querySelector('.profile-avatar');

    if (avatarInput) {
        avatarInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (!file) {
                avatarPreviewContainer.style.display = 'none';
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Only JPG, PNG and GIF images are allowed.');
                avatarInput.value = '';
                avatarPreviewContainer.style.display = 'none';
                return;
            }

            const previewUrl = URL.createObjectURL(file);
            avatarPreview.src = previewUrl;
            avatarPreviewContainer.style.display = 'block';

            if (profileAvatar) {
                const existingImg = profileAvatar.querySelector('img');
                const existingSpan = profileAvatar.querySelector('span');
                if (existingImg) {
                    existingImg.src = previewUrl;
                } else {
                    if (existingSpan) {
                        existingSpan.style.display = 'none';
                    }
                    const img = document.createElement('img');
                    img.src = previewUrl;
                    img.alt = 'Profile avatar preview';
                    profileAvatar.appendChild(img);
                }
            }
        });
    }
</script>
</body>
</html>
