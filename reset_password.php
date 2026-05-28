<?php
session_start();
require_once __DIR__ . '/db.php';

$errors  = [];
$message = '';
$token   = $_GET['token'] ?? '';

if ($token === '') {
    $errors[] = 'Invalid password reset link.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password === '' || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // Look up valid token
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            'SELECT email FROM password_resets
             WHERE token = ? AND used = 0 AND expires_at > ? LIMIT 1'
        );
        $stmt->bind_param('ss', $token, $now);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $email = $row['email'];

            // Update user password
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $updateUser = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
            $updateUser->bind_param('ss', $hashed, $email);
            $updateUser->execute();
            $updateUser->close();

            // Mark token as used
            $updateToken = $conn->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
            $updateToken->bind_param('s', $token);
            $updateToken->execute();
            $updateToken->close();

            $message = 'Your password has been reset successfully. You can now log in.';
        } else {
            $errors[] = 'This reset link is invalid or has expired.';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CommerceGo - Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-circle">
                <img src="logo.png" alt="Essen Pharmacy" class="logo-image">
            </div>
            <h1 class="app-title">Essen Pharmacy</h1>
            <p class="app-subtitle">CommerceGo Management Platform</p>
        </div>

        <div class="login-header">
            <h2>Reset your password</h2>
            <p>Choose a new password for your account.</p>
        </div>

        <div class="messages">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
        </div>

        <?php if (!$message): ?>
            <form method="post" action="reset_password.php" class="login-form" novalidate>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="field-group">
                    <label for="password" class="field-label">New Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="field-input"
                        placeholder="Enter a new password"
                        required
                    >
                </div>

                <div class="field-group">
                    <label for="confirm_password" class="field-label">Confirm Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="field-input"
                        placeholder="Re-enter your new password"
                        required
                    >
                </div>

                <button type="submit" class="btn-primary">Update Password</button>
            </form>
        <?php endif; ?>

        <div class="auth-footer">
            <span>Return to</span>
            <a href="login.php">login</a>
        </div>
    </div>
</div>
</body>
</html>

