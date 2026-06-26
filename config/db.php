<?php
// db.php - Database connection using PDO

// Database configurations
$db_host = 'localhost:3307';
$db_user = 'root';
$db_pass = '';
$db_name = 'student_outpass';

try {
    // Establish PDO connection
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // In case of error, show a neat error message (ensure it does not leak sensitive information in production)
    die("Database Connection Failed: " . $e->getMessage());
}
?>
