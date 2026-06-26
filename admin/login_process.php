<?php
// login_process.php - Process Admin Login

session_start();

// Include database configuration
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header('Location: login.html?error=invalid');
        exit;
    }

    try {
        // Fetch admin by username using prepared statement
        $stmt = $conn->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        // Verify password
        if ($admin && password_verify($password, $admin['password'])) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            // Log activity
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, details) VALUES ('Admin', :user_id, 'Login', 'Admin logged in successfully.')");
            $log_stmt->execute([':user_id' => $admin['id']]);

            header('Location: dashboard.php');
            exit;
        } else {
            // Log failed attempt
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, action, details) VALUES ('Admin', 'Failed Login', :details)");
            $log_stmt->execute([':details' => "Failed login attempt for username: $username"]);
            
            header('Location: login.html?error=invalid');
            exit;
        }
    } catch (PDOException $e) {
        // Log SQL errors to a log file instead of displaying them (security best practice)
        error_log("Database Error during admin login: " . $e->getMessage());
        header('Location: login.html?error=error');
        exit;
    }
} else {
    // If not a POST request, redirect to login page
    header('Location: login.html');
    exit;
}
?>
