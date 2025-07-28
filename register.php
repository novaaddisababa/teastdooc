<?php
session_start();
require_once 'db_connect.php';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $errors = [];

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if (empty($errors)) {
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, balance) VALUES (?, ?, 0)");
            if ($stmt->execute([$username, $hashed_password])) {
                $_SESSION['success'] = "Registration successful! You can now <a href='index.php'>login</a>.";
                header("Location: register.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .register-form { background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); max-width: 400px; margin: 40px auto; }
        .form-group { margin-bottom: 16px; }
        label { font-weight: bold; display: block; margin-bottom: 6px; }
        input[type=text], input[type=password] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #4CAF50; color: #fff; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; }
        button:hover { background: #388e3c; }
        .error { color: #d32f2f; background: #fde8e8; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .success { color: #388e3c; background: #e8f5e9; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .login-link { text-align: center; margin-top: 16px; }
        .login-link a { color: #2196F3; text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Register</h2>
        <div class="login-link"><a href="index.php">Back to Login</a></div>
    </div>
    <div class="register-form">
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error) echo htmlspecialchars($error) . '<br>'; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Register</button>
        </form>
    </div>
</body>
</html>
