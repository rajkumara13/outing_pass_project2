<?php
// login_process.php - Process Security Login

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
        // Fetch security record by username
        $stmt = $conn->prepare("SELECT * FROM security WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $security = $stmt->fetch();

        // Verify password
        if ($security && password_verify($password, $security['password'])) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $_SESSION['security_logged_in'] = true;
            $_SESSION['security_id'] = $security['id'];
            $_SESSION['security_username'] = $security['username'];
            $_SESSION['security_name'] = $security['name'];

            // Log activity
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, details) VALUES ('Security', :user_id, 'Login', 'Security logged in successfully.')");
            $log_stmt->execute([':user_id' => $security['id']]);

            header('Location: dashboard.php');
            exit;
        } else {
            // Log failed attempt
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, action, details) VALUES ('Security', 'Failed Login', :details)");
            $log_stmt->execute([':details' => "Failed security login attempt for username: $username"]);
            
            header('Location: login.html?error=invalid');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database Error during security login: " . $e->getMessage());
        header('Location: login.html?error=error');
        exit;
    }
} else {
    header('Location: login.html');
    exit;
}
?>
