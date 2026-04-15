<?php
session_start();
require_once __DIR__ . '/db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrUser = trim($_POST['email_or_username'] ?? '');
    $password    = $_POST['password'] ?? '';

    if ($emailOrUser === '') {
        $errors[] = 'Email or Username is required.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT userID, userName, email, password, role FROM users WHERE userID = ? OR email = ? OR userName = ? LIMIT 1');
        $stmt->bind_param('sss', $emailOrUser, $emailOrUser, $emailOrUser);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['userID']   = $row['userID'];
                $_SESSION['userName'] = $row['userName'];
                $_SESSION['email']    = $row['email'];
                $_SESSION['role']     = $row['role'];

                if ($row['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } elseif ($row['role'] === 'staff') {
                    header('Location: staff/index.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            } else {
                $errors[] = 'Invalid credentials.';
            }
        } else {
            $errors[] = 'Invalid credentials.';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CommerceGo - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-circle">
                <span class="logo-icon">⧉</span>
            </div>
            <h1 class="app-title">Essen Pharmacy</h1>
            <p class="app-subtitle">CommerceGo Management Platform</p>
        </div>

        <div class="login-header">
            <h2>Sign in to your account</h2>
            <p>Select your role and enter your credentials</p>
        </div>

        <div class="messages">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form method="post" action="login.php" class="login-form" novalidate>
            <div class="field-group">
                <label class="field-label">Select Role</label>
                <div class="role-grid">
                    <label class="role-card">
                        <input type="radio" name="role" value="admin" checked>
                        <div class="role-body">
                            <div class="role-title">Administrator</div>
                            <div class="role-desc">Analytics, reports &amp; team management</div>
                        </div>
                    </label>
                    <label class="role-card">
                        <input type="radio" name="role" value="staff">
                        <div class="role-body">
                            <div class="role-title">Pharmacy Staff</div>
                            <div class="role-desc">Inventory, compliance &amp; stock sync</div>
                        </div>
                    </label>
                    <label class="role-card">
                        <input type="radio" name="role" value="customer">
                        <div class="role-body">
                            <div class="role-title">Customer</div>
                            <div class="role-desc">Browse products &amp; place orders</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="field-group">
                <label for="email_or_username" class="field-label">Email Address or User ID</label>
                <input
                    type="text"
                    id="email_or_username"
                    name="email_or_username"
                    class="field-input"
                    placeholder="you@example.com or 123"
                    value="<?php echo isset($emailOrUser) ? htmlspecialchars($emailOrUser) : ''; ?>"
                    required
                >
            </div>

            <div class="field-group">
                <div class="field-label-row">
                    <label for="password" class="field-label">Password</label>
                </div>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="field-input"
                    placeholder="Enter your password"
                    required
                >
            </div>

            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div class="auth-footer">
            <span>New to CommerceGo?</span>
            <a href="register.php">Create an account</a>
            <br>
            <a href="forgot_password.php">Forgot your password?</a>
        </div>
    </div>
</div>
</body>
</html>

