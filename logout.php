<?php
require_once 'security.php';

// Initialize secure session
Security::secureSessionStart();

// Verify CSRF token if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }
}

// Log the logout action if user is logged in
if (isset($_SESSION['user_id'])) {
    require_once 'db_connect.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'logout', $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>