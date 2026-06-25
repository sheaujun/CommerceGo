<?php
session_start();
require_once __DIR__ . '/db.php';

$message = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        // Check if user exists
        $stmt = $conn->prepare('SELECT userID FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Create reset token (valid for 1 hour)
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            // Insert token
            $insert = $conn->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)');
            $insert->bind_param('sss', $email, $token, $expiresAt);
            $insert->execute();
            $insert->close();

            // Build reset link
            $resetLink = sprintf(
                '%s://%s%s/reset_password.php?token=%s',
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
                $_SERVER['HTTP_HOST'],
                rtrim(dirname($_SERVER['PHP_SELF']), '/\\'),
                urlencode($token)
            );

            // Send email (requires mail() configuration in XAMPP / PHP.ini)
            $subject = 'CommerceGo Password Reset';
            $body    = "Hello,\n\nWe received a request to reset your CommerceGo account password.\n\n"
                     . "Click the link below to reset your password:\n$resetLink\n\n"
                     . "If you did not request this, you can ignore this email.\n\n"
                     . "Regards,\nEssen Pharmacy - CommerceGo";
            $headers = 'From: no-reply@commercego.local';

            @mail($email, $subject, $body, $headers);
        }

        // Always show success message (do not reveal if email exists)
        $message = 'If that email address exists in our system, we have sent a password reset link.';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CommerceGo - Forgot Password</title>
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
            <h2>Forgot your password?</h2>
            <p>Enter your email address and we'll send you a reset link.</p>
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

        <form method="post" action="forgot_password.php" class="login-form" novalidate>
            <div class="field-group">
                <label for="email" class="field-label">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="field-input"
                    placeholder="you@example.com"
                    required
                >
            </div>

            <button type="submit" class="btn-primary">Send Reset Link</button>
        </form>

        <div class="auth-footer">
            <span>Remembered your password?</span>
            <a href="login.php">Back to login</a>
        </div>
    </div>
</div>
</body>
</html>

