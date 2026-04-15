<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$errors = [];
$success = '';

function formatDate($dateString)
{
    if (!$dateString) {
        return '-';
    }
    $dt = new DateTime($dateString);
    return $dt->format('M d, Y');
}

function formatDateTime($dateString)
{
    if (!$dateString) {
        return '-';
    }
    $dt = new DateTime($dateString);
    return $dt->format('M d, Y h:i A');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submissionId = (int)($_POST['submission_id'] ?? 0);

    if ($submissionId <= 0) {
        $errors[] = 'Invalid submission selected.';
    }

    if (empty($errors)) {
        if ($action === 'approve') {
            $stmt = $conn->prepare(
                'SELECT productName, productDescription, category, price, stockQuantity, imagePath, expiryDate
                 FROM product_submissions
                 WHERE submissionID = ? AND status = "Pending"'
            );
            $stmt->bind_param('i', $submissionId);
            $stmt->execute();
            $result = $stmt->get_result();
            $submission = $result->fetch_assoc();
            $stmt->close();

            if (!$submission) {
                $errors[] = 'Submission not found or already processed.';
            } else {
                $conn->begin_transaction();
                try {
                    $insert = $conn->prepare(
                        'INSERT INTO products
                         (productName, productDescription, category, price, stockQuantity, complianceStatus, status, imagePath, expiryDate)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $approvedStatus = 'Approved';
                    $activeStatus = 'Active';
                    $insert->bind_param(
                        'sssisssss',
                        $submission['productName'],
                        $submission['productDescription'],
                        $submission['category'],
                        $submission['price'],
                        $submission['stockQuantity'],
                        $approvedStatus,
                        $activeStatus,
                        $submission['imagePath'],
                        $submission['expiryDate']
                    );
                    $insert->execute();
                    $insert->close();

                    $update = $conn->prepare(
                        'UPDATE product_submissions SET status = "Approved" WHERE submissionID = ?'
                    );
                    $update->bind_param('i', $submissionId);
                    $update->execute();
                    $update->close();

                    $conn->commit();
                    $success = 'Product submission has been approved and added to inventory.';
                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = 'Unable to approve submission. Please try again.';
                }
            }
        }

        if ($action === 'reject') {
            $rejectReason = trim($_POST['reject_reason'] ?? '');
            $stmt = $conn->prepare(
                'UPDATE product_submissions
                 SET status = "Rejected", rejectionReason = ?
                 WHERE submissionID = ? AND status = "Pending"'
            );
            $stmt->bind_param('si', $rejectReason, $submissionId);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $success = 'Product submission has been rejected.';
            } else {
                $errors[] = 'Unable to reject submission. It may have been processed already.';
            }
            $stmt->close();
        }
    }
}

$search = trim($_GET['q'] ?? '');
$categoryF = trim($_GET['cat'] ?? '');
$where = 'status = "Pending"';
$params = [];
$types = '';

if ($search !== '') {
    $where .= ' AND (productName LIKE ? OR productDescription LIKE ? OR category LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($categoryF !== '' && $categoryF !== 'All') {
    $where .= ' AND category = ?';
    $params[] = $categoryF;
    $types .= 's';
}

$sql = "SELECT submissionID, userID, productName, productDescription, category, price, stockQuantity, imagePath, expiryDate, status, rejectionReason, created_at
        FROM product_submissions
        WHERE $where
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$submissions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$catResult = $conn->query('SELECT DISTINCT category FROM product_submissions ORDER BY category ASC');
$categories = $catResult ? $catResult->fetch_all(MYSQLI_ASSOC) : [];

$pendingCount = 0;
$approvedToday = 0;
$rejectedToday = 0;

$countStmt = $conn->prepare('SELECT
    SUM(status = "Pending") AS pending_count,
    SUM(status = "Approved" AND DATE(created_at) = CURDATE()) AS approved_today,
    SUM(status = "Rejected" AND DATE(created_at) = CURDATE()) AS rejected_today
    FROM product_submissions'
);
$countStmt->execute();
$countResult = $countStmt->get_result();
if ($countResult) {
    $counts = $countResult->fetch_assoc();
    $pendingCount = (int)$counts['pending_count'];
    $approvedToday = (int)$counts['approved_today'];
    $rejectedToday = (int)$counts['rejected_today'];
}
$countStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Essen Pharmacy - Product Approvals</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin-dashboard.css">
    <link rel="stylesheet" href="css/admin-approvals.css">
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
            <a href="approvals.php" class="nav-item active">
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
        <div class="approval-header">
            <div class="approval-header-title">
                <h1>Product Approvals</h1>
                <p>Review and approve products submitted by staff members.</p>
            </div>
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

        <div class="approval-metrics">
            <div class="metric-card approval-card-metric">
                <div class="metric-icon warning">⏱</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo $pendingCount; ?></div>
                    <div class="metric-label">Pending Approval</div>
                </div>
            </div>
            <div class="metric-card approval-card-metric">
                <div class="metric-icon success">✔</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo $approvedToday; ?></div>
                    <div class="metric-label">Approved Today</div>
                </div>
            </div>
            <div class="metric-card approval-card-metric">
                <div class="metric-icon destructive">✖</div>
                <div class="metric-main">
                    <div class="metric-value"><?php echo $rejectedToday; ?></div>
                    <div class="metric-label">Rejected Today</div>
                </div>
            </div>
        </div>

        <div class="approval-search-row">
            <form method="get" action="approvals.php" class="approval-search-card">
                <input
                    type="text"
                    name="q"
                    class="approval-search-input"
                    placeholder="Search products..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >
            </form>
            <div class="approval-filter-card">
                <form method="get" action="approvals.php">
                    <select name="cat" class="approval-filter-select" onchange="this.form.submit()">
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

        <?php if (empty($submissions)): ?>
            <div class="approval-empty-card">
                <div class="approval-empty-icon">✅</div>
                <div>
                    <p class="approval-empty-title">No products pending approval.</p>
                    <p class="approval-empty-text">All submitted products have been processed or there are no pending requests.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="approval-list">
                <?php foreach ($submissions as $submission): ?>
                    <div class="approval-card">
                        <div class="approval-card-top">
                            <div class="approval-card-media">
                                <span class="approval-card-icon">📦</span>
                            </div>
                            <div class="approval-card-info">
                                <div class="approval-card-title-row">
                                    <div>
                                        <h2><?php echo htmlspecialchars($submission['productName']); ?></h2>
                                        <p><?php echo htmlspecialchars($submission['productDescription']); ?></p>
                                    </div>
                                    <span class="badge-category"><?php echo htmlspecialchars($submission['category']); ?></span>
                                </div>
                                <div class="approval-card-meta">
                                    <span>$<?php echo number_format($submission['price'], 2); ?></span>
                                    <span><?php echo (int)$submission['stockQuantity']; ?> units</span>
                                    <span>Exp: <?php echo htmlspecialchars(formatDate($submission['expiryDate'])); ?></span>
                                    <span>Submitted: <?php echo htmlspecialchars(formatDateTime($submission['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <details class="approval-details">
                            <summary>View details</summary>
                            <div class="approval-details-body">
                                <div>
                                    <strong>Category</strong>
                                    <p><?php echo htmlspecialchars($submission['category']); ?></p>
                                </div>
                                <div>
                                    <strong>Submitted By</strong>
                                    <p>User ID <?php echo (int)$submission['userID']; ?></p>
                                </div>
                                <?php if ($submission['rejectionReason']): ?>
                                    <div>
                                        <strong>Rejection Reason</strong>
                                        <p><?php echo htmlspecialchars($submission['rejectionReason']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>

                        <div class="approval-actions">
                            <form method="post" class="approval-action-form">
                                <input type="hidden" name="submission_id" value="<?php echo (int)$submission['submissionID']; ?>">
                                <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn-reject" onclick="return setRejectReason(this)">Reject</button>
                                <input type="hidden" name="reject_reason" class="reject-reason" value="">
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
function setRejectReason(button) {
    var form = button.closest('form');
    var reason = prompt('Rejection reason (optional):');
    if (reason === null) {
        return false;
    }
    form.querySelector('.reject-reason').value = reason;
    return confirm('Are you sure you want to reject this submission?');
}
</script>
</body>
</html>
