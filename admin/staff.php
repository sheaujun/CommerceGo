<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$errors = [];
$success = '';
$generatedPassword = '';
$createdUserName = '';

// Helper to create unique username from email
function generate_username(mysqli $conn, string $email): string {
    $base = strstr($email, '@', true) ?: 'staff';
    $username = $base;
    $suffix = 1;

    $stmt = $conn->prepare('SELECT userID FROM users WHERE userName = ? LIMIT 1');
    while (true) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            return $username;
        }
        $suffix++;
        $username = $base . $suffix;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_staff') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $status   = $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';
    $joinDate = $_POST['join_date'] ?? '';

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if ($position === '') {
        $errors[] = 'Role is required.';
    }
    if ($joinDate === '') {
        $errors[] = 'Join date is required.';
    }

    if (empty($errors)) {
        // Check for existing email
        $stmt = $conn->prepare('SELECT userID FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'A user with this email already exists.';
        }
        $stmt->close();
    }

    if (empty($errors)) {
        // Split full name
        $parts = preg_split('/\s+/', $fullName);
        $firstName = $parts[0] ?? '';
        $lastName  = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';

        // Generate username and default password for new staff
        $userName = generate_username($conn, $email);
        $generatedPassword = 'Staff123';
        $hashedPassword = password_hash($generatedPassword, PASSWORD_BCRYPT);
        $role = 'staff';

        // Insert into users
        $stmt = $conn->prepare(
            'INSERT INTO users (userName, firstName, lastName, email, password, role, phoneNo)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssss', $userName, $firstName, $lastName, $email, $hashedPassword, $role, $phone);

        if ($stmt->execute()) {
            $newUserId = $stmt->insert_id;
            $stmt->close();

            // Insert staff details
            $staffStmt = $conn->prepare(
                'INSERT INTO staff_details (userID, position, status, join_date)
                 VALUES (?, ?, ?, ?)'
            );
            $staffStmt->bind_param('isss', $newUserId, $position, $status, $joinDate);
            $staffStmt->execute();
            $staffStmt->close();

            $success = 'Staff account created successfully. Share the credentials below with the staff member.';
            $createdUserName = $userName;
        } else {
            $errors[] = 'Failed to create staff account. Please try again.';
        }
    }
}

// Handle edit staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_staff') {
    $userId   = (int)($_POST['user_id'] ?? 0);
    $fullName = trim($_POST['edit_full_name'] ?? '');
    $email    = trim($_POST['edit_email'] ?? '');
    $phone    = trim($_POST['edit_phone'] ?? '');
    $position = trim($_POST['edit_position'] ?? '');
    $status   = $_POST['edit_status'] === 'Inactive' ? 'Inactive' : 'Active';
    $joinDate = $_POST['edit_join_date'] ?? '';

    if ($userId <= 0) {
        $errors[] = 'Invalid staff selected.';
    } else {
        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if ($position === '') {
            $errors[] = 'Role is required.';
        }
        if ($joinDate === '') {
            $errors[] = 'Join date is required.';
        }
    }

    if (empty($errors) && $userId > 0) {
        // Ensure email is unique for other users
        $stmt = $conn->prepare('SELECT userID FROM users WHERE email = ? AND userID <> ? LIMIT 1');
        $stmt->bind_param('si', $email, $userId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Another user with this email already exists.';
        }
        $stmt->close();
    }

    if (empty($errors) && $userId > 0) {
        $parts = preg_split('/\s+/', $fullName);
        $firstName = $parts[0] ?? '';
        $lastName  = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';

        // Update users
        $stmt = $conn->prepare(
            'UPDATE users
             SET firstName = ?, lastName = ?, email = ?, phoneNo = ?
             WHERE userID = ? AND role = "staff"'
        );
        $stmt->bind_param('ssssi', $firstName, $lastName, $email, $phone, $userId);

        if ($stmt->execute()) {
            $stmt->close();

            // Update staff details
            $staffStmt = $conn->prepare(
                'UPDATE staff_details
                 SET position = ?, status = ?, join_date = ?
                 WHERE userID = ?'
            );
            $staffStmt->bind_param('sssi', $position, $status, $joinDate, $userId);
            $staffStmt->execute();
            $staffStmt->close();

            $success = 'Staff details updated successfully.';
        } else {
            $errors[] = 'Failed to update staff details. Please try again.';
        }
    }
}

// Handle delete staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_staff') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0) {
        $stmt = $conn->prepare('DELETE FROM users WHERE userID = ? AND role = "staff"');
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $success = 'Staff deleted successfully.';
        } else {
            $errors[] = 'Failed to delete staff. Please try again.';
        }
        $stmt->close();
    } else {
        $errors[] = 'Invalid staff selected for deletion.';
    }
}

// Search
$search = trim($_GET['q'] ?? '');
$where  = "u.role = 'staff'";
if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= " AND (CONCAT(u.firstName, ' ', u.lastName) LIKE ? OR u.email LIKE ? OR s.position LIKE ?)";
}

$sql = "SELECT u.userID, u.firstName, u.lastName, u.email, u.phoneNo,
               s.position, s.status, s.join_date
        FROM users u
        LEFT JOIN staff_details s ON s.userID = u.userID
        WHERE $where
        ORDER BY s.join_date DESC, u.firstName ASC";

if ($search !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $like, $like, $like);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
$staffRows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Staff Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/admin-staff.css">
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
            <a href="staff.php" class="nav-item active">
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
        <div class="staff-header">
            <div class="staff-header-title">
                <h1>Staff Management</h1>
                <p>Manage pharmacy staff members and their roles.</p>
            </div>
            <button type="button" class="btn-add-staff" id="openAddStaff">
                <span class="btn-add-staff-icon">＋</span>
                Add Staff
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
                <?php if ($generatedPassword): ?>
                    <br>
                    Username: <span class="generated-password"><?php echo htmlspecialchars($createdUserName ?? ''); ?></span> |
                    Default Password: <span class="generated-password"><?php echo htmlspecialchars($generatedPassword); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="staff-search-card">
            <form method="get" action="staff.php">
                <input
                    type="text"
                    name="q"
                    class="staff-search-input"
                    placeholder="Search by name, email, or role..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </form>
        </div>

        <div class="staff-list-card">
            <div class="staff-list-header">
                <h2>All Staff</h2>
                <div class="staff-count">(<?php echo count($staffRows); ?>)</div>
            </div>

            <table class="staff-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Join Date</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($staffRows)): ?>
                    <tr>
                        <td colspan="6">No staff found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staffRows as $row): ?>
                        <tr>
                            <td>
                                <div class="staff-name">
                                    <?php echo htmlspecialchars(trim($row['firstName'] . ' ' . $row['lastName'])); ?>
                                </div>
                            </td>
                            <td>
                                <div class="staff-contact">
                                    <?php echo htmlspecialchars($row['email']); ?><br>
                                    <?php echo htmlspecialchars($row['phoneNo'] ?? ''); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge-role">
                                    <?php echo htmlspecialchars($row['position'] ?? 'Staff'); ?>
                                </span>
                            </td>
                            <td>
                                <?php $statusVal = $row['status'] ?? 'Active'; ?>
                                <span class="badge-status <?php echo strtolower($statusVal) === 'inactive' ? 'inactive' : 'active'; ?>">
                                    <?php echo htmlspecialchars($statusVal); ?>
                                </span>
                            </td>
                            <td>
                                <span class="staff-join">
                                    <?php echo htmlspecialchars($row['join_date'] ?? '-'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="staff-actions" data-user-id="<?php echo (int)$row['userID']; ?>"
                                     data-full-name="<?php echo htmlspecialchars(trim($row['firstName'] . ' ' . $row['lastName'])); ?>"
                                     data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                     data-phone="<?php echo htmlspecialchars($row['phoneNo'] ?? ''); ?>"
                                     data-position="<?php echo htmlspecialchars($row['position'] ?? 'Staff'); ?>"
                                     data-status="<?php echo htmlspecialchars($row['status'] ?? 'Active'); ?>"
                                     data-join-date="<?php echo htmlspecialchars($row['join_date'] ?? ''); ?>">
                                    <button type="button" class="staff-actions-btn">⋯</button>
                                    <div class="staff-actions-menu">
                                        <button type="button" class="staff-edit-btn">Edit</button>
                                        <form method="post" action="staff.php" onsubmit="return confirm('Delete this staff member?');">
                                            <input type="hidden" name="action" value="delete_staff">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$row['userID']; ?>">
                                            <button type="submit" class="staff-delete-btn">Delete</button>
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

<div class="modal-backdrop" id="addStaffModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Add New Staff</div>
            <button type="button" class="modal-close" id="closeAddStaff">×</button>
        </div>
        <div class="modal-body">
            <form method="post" action="staff.php">
                <input type="hidden" name="action" value="add_staff">

                <div class="modal-field">
                    <label class="modal-label" for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="modal-input" required>
                </div>

                <div class="modal-field">
                    <label class="modal-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="modal-input" required>
                </div>

                <div class="modal-field">
                    <label class="modal-label" for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" class="modal-input">
                </div>

                <div class="modal-grid">
                    <div class="modal-field">
                        <label class="modal-label" for="position">Role</label>
                        <select id="position" name="position" class="modal-select" required>
                            <option value="">Select role</option>
                            <option value="Pharmacist">Pharmacist</option>
                            <option value="Technician">Technician</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label class="modal-label" for="status">Status</label>
                        <select id="status" name="status" class="modal-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-field">
                    <label class="modal-label" for="join_date">Join Date</label>
                    <input type="date" id="join_date" name="join_date" class="modal-input" required>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelAddStaff">Cancel</button>
                    <button type="submit" class="btn-primary">Add Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="editStaffModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Staff</div>
            <button type="button" class="modal-close" id="closeEditStaff">×</button>
        </div>
        <div class="modal-body">
            <form method="post" action="staff.php">
                <input type="hidden" name="action" value="edit_staff">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="modal-field">
                    <label class="modal-label" for="edit_full_name">Full Name</label>
                    <input type="text" id="edit_full_name" name="edit_full_name" class="modal-input" required>
                </div>

                <div class="modal-field">
                    <label class="modal-label" for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="edit_email" class="modal-input" required>
                </div>

                <div class="modal-field">
                    <label class="modal-label" for="edit_phone">Phone</label>
                    <input type="text" id="edit_phone" name="edit_phone" class="modal-input">
                </div>

                <div class="modal-grid">
                    <div class="modal-field">
                        <label class="modal-label" for="edit_position">Role</label>
                        <select id="edit_position" name="edit_position" class="modal-select" required>
                            <option value="Pharmacist">Pharmacist</option>
                            <option value="Technician">Technician</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label class="modal-label" for="edit_status">Status</label>
                        <select id="edit_status" name="edit_status" class="modal-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-field">
                    <label class="modal-label" for="edit_join_date">Join Date</label>
                    <input type="date" id="edit_join_date" name="edit_join_date" class="modal-input" required>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelEditStaff">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const openBtn = document.getElementById('openAddStaff');
    const closeBtn = document.getElementById('closeAddStaff');
    const cancelBtn = document.getElementById('cancelAddStaff');
    const addModal = document.getElementById('addStaffModal');

    function openAddModal() {
        if (addModal) {
            addModal.classList.add('show');
        }
    }
    function closeAddModal() {
        if (addModal) {
            addModal.classList.remove('show');
        }
    }

    if (openBtn) openBtn.addEventListener('click', openAddModal);
    if (closeBtn) closeBtn.addEventListener('click', closeAddModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeAddModal);

    window.addEventListener('click', function (e) {
        if (e.target === addModal) {
            closeAddModal();
        }
    });

    // Actions menu and edit modal
    const editModal = document.getElementById('editStaffModal');
    const closeEditBtn = document.getElementById('closeEditStaff');
    const cancelEditBtn = document.getElementById('cancelEditStaff');
    const editUserId = document.getElementById('edit_user_id');
    const editFullName = document.getElementById('edit_full_name');
    const editEmail = document.getElementById('edit_email');
    const editPhone = document.getElementById('edit_phone');
    const editPosition = document.getElementById('edit_position');
    const editStatus = document.getElementById('edit_status');
    const editJoinDate = document.getElementById('edit_join_date');

    function openEditModal() {
        if (editModal) {
            editModal.classList.add('show');
        }
    }
    function closeEditModal() {
        if (editModal) {
            editModal.classList.remove('show');
        }
    }

    document.querySelectorAll('.staff-actions-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const container = btn.parentElement;
            const menu = container.querySelector('.staff-actions-menu');
            document.querySelectorAll('.staff-actions-menu').forEach(function (m) {
                if (m !== menu) m.classList.remove('show');
            });
            if (menu) {
                menu.classList.toggle('show');
            }
        });
    });

    document.querySelectorAll('.staff-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const container = btn.closest('.staff-actions');
            if (!container) return;
            const menu = container.querySelector('.staff-actions-menu');
            if (menu) menu.classList.remove('show');

            if (!editModal) return;

            editUserId.value = container.getAttribute('data-user-id') || '';
            editFullName.value = container.getAttribute('data-full-name') || '';
            editEmail.value = container.getAttribute('data-email') || '';
            editPhone.value = container.getAttribute('data-phone') || '';
            editPosition.value = container.getAttribute('data-position') || 'Staff';
            editStatus.value = container.getAttribute('data-status') || 'Active';
            editJoinDate.value = container.getAttribute('data-join-date') || '';

            openEditModal();
        });
    });

    if (closeEditBtn) closeEditBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeEditModal();
    });
    if (cancelEditBtn) cancelEditBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeEditModal();
    });

    window.addEventListener('click', function (e) {
        if (e.target === editModal) {
            closeEditModal();
        }
    });

    window.addEventListener('click', function () {
        document.querySelectorAll('.staff-actions-menu').forEach(function (m) {
            m.classList.remove('show');
        });
    });
</script>
</body>
</html>

