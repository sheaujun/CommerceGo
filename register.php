<?php
session_start();
require_once __DIR__ . '/db.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName'] ?? '');
    $userName  = trim($_POST['userName'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phoneNo   = trim($_POST['phoneNo'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($firstName === '') $errors[] = 'First name is required.';
    if ($lastName === '') $errors[] = 'Last name is required.';
    if ($userName === '') $errors[] = 'Username is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT userID FROM users WHERE email = ? OR userName = ?');
        $stmt->bind_param('ss', $email, $userName);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'Email or Username already exists.';
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $role = 'customer';

        // Start transaction
        $conn->begin_transaction();

        try {
            // Insert into users table
            $stmt = $conn->prepare(
                'INSERT INTO users (userName, firstName, lastName, email, password, role, phoneNo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssssss', $userName, $firstName, $lastName, $email, $hashedPassword, $role, $phoneNo);
            $stmt->execute();
            $userId = $conn->insert_id;
            $stmt->close();

            // Generate customer code
            $customerCode = 'CUST' . str_pad($userId, 3, '0', STR_PAD_LEFT);

            // Insert into customers table
            $fullName = $firstName . ' ' . $lastName;
            $customerStmt = $conn->prepare(
                'INSERT INTO customers (user_id, customer_code, name, email, phone, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $customerStmt->bind_param('isssss', $userId, $customerCode, $fullName, $email, $phoneNo, $role);
            $customerStmt->execute();
            $customerStmt->close();

            $conn->commit();
            $success = 'Registration successful. You can now log in.';
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CommerceGo - Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/register.css">
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

        <div class="register-header">
            <h2>Register - CommerceGo</h2>
            <p>Create your Essen Pharmacy account</p>
        </div>

        <div class="messages">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
        </div>

        <form method="post" action="register.php" class="login-form register-form" novalidate>
            <div class="field-row">
                <div class="field-group">
                    <label for="firstName" class="field-label">First Name</label>
                    <input
                        type="text"
                        id="firstName"
                        name="firstName"
                        class="field-input"
                        value="<?php echo isset($firstName) ? htmlspecialchars($firstName) : ''; ?>"
                        required
                    >
                </div>
                <div class="field-group">
                    <label for="lastName" class="field-label">Last Name</label>
                    <input
                        type="text"
                        id="lastName"
                        name="lastName"
                        class="field-input"
                        value="<?php echo isset($lastName) ? htmlspecialchars($lastName) : ''; ?>"
                        required
                    >
                </div>
            </div>

            <div class="field-group">
                <label for="userName" class="field-label">Username</label>
                <input
                    type="text"
                    id="userName"
                    name="userName"
                    class="field-input"
                    value="<?php echo isset($userName) ? htmlspecialchars($userName) : ''; ?>"
                    required
                >
            </div>

            <div class="field-group">
                <label for="email" class="field-label">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="field-input"
                    value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                    required
                >
            </div>

            <div class="field-group">
                <label for="phoneNo" class="field-label">Phone Number</label>
                <input
                    type="text"
                    id="phoneNo"
                    name="phoneNo"
                    class="field-input"
                    value="<?php echo isset($phoneNo) ? htmlspecialchars($phoneNo) : ''; ?>"
                >
            </div>

            <div class="field-group">
                <label for="password" class="field-label">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="field-input"
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
                    required
                >
            </div>

            <button type="submit" class="btn-primary">Create Account</button>
        </form>

        <div class="auth-footer">
            <span>Already have an account?</span>
            <a href="login.php">Log in</a>
        </div>
    </div>
</div>
</body>
</html>

