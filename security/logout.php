<?php
// logout.php - Security Logout handler

session_start();

require_once '../config/db.php';

// Log logout action if security session exists
if (isset($_SESSION['security_logged_in']) && isset($_SESSION['security_id'])) {
    try {
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, details) VALUES ('Security', :user_id, 'Logout', 'Security logged out successfully.')");
        $log_stmt->execute([':user_id' => $_SESSION['security_id']]);
    } catch (PDOException $e) {
        // Suppress database logging errors during logout
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login page with logged out status
header("Location: login.html?error=loggedout");
exit;
?>
