<?php
// setup.php - Database and tables initialization script

$db_host = 'localhost:3307';
$db_user = 'root';
$db_pass = '';
$db_name = 'student_outpass';

$success = false;
$message = '';
$errors = [];

try {
    // 1. Connect to MySQL Server (without selecting database)
    $conn = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. Create database if not exists
    $conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. Connect to the created database
    $conn->exec("USE `$db_name`");
    
    // 4. Create Tables
    
    // Admin table
    $sql_admin = "CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";
    $conn->exec($sql_admin);
    
    // Security table
    $sql_security = "CREATE TABLE IF NOT EXISTS security (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(15) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";
    $conn->exec($sql_security);
    
    // Students table
    $sql_students = "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        roll_number VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        department VARCHAR(100) NOT NULL,
        year INT NOT NULL,
        room_number VARCHAR(20) NOT NULL,
        hostel_type VARCHAR(50) NOT NULL,
        phone VARCHAR(15) NOT NULL,
        email VARCHAR(100) NOT NULL,
        photo VARCHAR(255) DEFAULT NULL,
        qr_code VARCHAR(255) DEFAULT NULL,
        status ENUM('Inside', 'Outside') DEFAULT 'Inside',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";
    $conn->exec($sql_students);
    
    // Outing table
    $sql_outing = "CREATE TABLE IF NOT EXISTS outing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        reason VARCHAR(100) NOT NULL,
        exit_time TIMESTAMP NULL DEFAULT NULL,
        entry_time TIMESTAMP NULL DEFAULT NULL,
        status ENUM('Outside', 'Returned') DEFAULT 'Outside',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    $conn->exec($sql_outing);
    
    // Homepass table
    $sql_homepass = "CREATE TABLE IF NOT EXISTS homepass (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        leave_date DATE NOT NULL,
        return_date DATE NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        admin_remarks TEXT DEFAULT NULL,
        exit_time TIMESTAMP NULL DEFAULT NULL,
        entry_time TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    $conn->exec($sql_homepass);
    
    // Activity logs table
    $sql_logs = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_type VARCHAR(50) NOT NULL, -- Admin, Security, Student, System
        user_id INT DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";
    $conn->exec($sql_logs);
    
    // 5. Seed default Admin if table is empty
    $stmt = $conn->query("SELECT COUNT(*) FROM admin");
    if ($stmt->fetchColumn() == 0) {
        $admin_username = 'admin';
        $admin_password_raw = 'admin123';
        $admin_password_hash = password_hash($admin_password_raw, PASSWORD_DEFAULT);
        
        $insert_admin = $conn->prepare("INSERT INTO admin (username, password) VALUES (:username, :password)");
        $insert_admin->execute([
            ':username' => $admin_username,
            ':password' => $admin_password_hash
        ]);
        $message .= "Default Admin seeded (Username: <strong>admin</strong>, Password: <strong>admin123</strong>).<br>";
    }
    
    // 6. Seed default Security if table is empty
    $stmt = $conn->query("SELECT COUNT(*) FROM security");
    if ($stmt->fetchColumn() == 0) {
        $security_username = 'security';
        $security_password_raw = 'security123';
        $security_password_hash = password_hash($security_password_raw, PASSWORD_DEFAULT);
        
        $insert_security = $conn->prepare("INSERT INTO security (username, password, name, phone) VALUES (:username, :password, :name, :phone)");
        $insert_security->execute([
            ':username' => $security_username,
            ':password' => $security_password_hash,
            ':name' => 'Main Gate Security',
            ':phone' => '9876543210'
        ]);
        $message .= "Default Security seeded (Username: <strong>security</strong>, Password: <strong>security123</strong>).<br>";
    }
    
    // 7. Download libraries locally from user's local XAMPP php process
    function downloadLocalLibrary($url, $destinationPath, &$message) {
        $dir = dirname($destinationPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
                'timeout' => 20
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $filename = basename($destinationPath);
        $data = @file_get_contents($url, false, $context);
        if ($data !== false && strlen($data) > 0) {
            if (file_put_contents($destinationPath, $data) !== false) {
                $message .= "Successfully downloaded local asset: <strong>$filename</strong>.<br>";
                return true;
            }
        }
        
        $message .= "<span class='text-warning'>Warning: Failed to download <strong>$filename</strong> from $url. Please install it manually.</span><br>";
        return false;
    }

    $dependencies = [
        'admin/phpqrcode.php' => 'https://raw.githubusercontent.com/t0k4rt/phpqrcode/master/phpqrcode.php',
        'js/html5-qrcode.min.js' => 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js',
        'css/bootstrap.min.css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
        'js/bootstrap.bundle.min.js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
        'css/all.min.css' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        'webfonts/fa-solid-900.woff2' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.woff2',
        'webfonts/fa-solid-900.ttf' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.ttf',
        'webfonts/fa-regular-400.woff2' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-regular-400.woff2',
        'webfonts/fa-regular-400.ttf' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-regular-400.ttf',
        'webfonts/fa-brands-400.woff2' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-brands-400.woff2',
        'webfonts/fa-brands-400.ttf' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-brands-400.ttf'
    ];

    foreach ($dependencies as $rel_path => $url) {
        $dest_path = __DIR__ . '/../' . $rel_path;
        if (!file_exists($dest_path)) {
            downloadLocalLibrary($url, $dest_path, $message);
        } else {
            $message .= "Asset <strong>" . basename($rel_path) . "</strong> already exists locally.<br>";
        }
    }
    
    $success = true;
    $message = "Database and tables successfully initialized!<br>" . $message;
    
    // Log the setup action
    $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, action, details) VALUES ('System', 'Database Initialization', 'Setup script executed successfully.')");
    $log_stmt->execute();
    
} catch (PDOException $e) {
    $success = false;
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Student Hostel Outpass Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b1120;
            --card-bg: rgba(17, 25, 40, 0.75);
            --border-color: rgba(255, 255, 255, 0.125);
            --text-color: #f3f4f6;
            --accent-green: #10b981;
            --accent-red: #ef4444;
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: radial-gradient(circle at 10% 20%, rgba(31, 38, 103, 0.4) 0%, rgba(11, 17, 32, 1) 90%);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.5rem;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        .btn-custom {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border: none;
            border-radius: 8px;
            color: white;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="glass-card text-center">
        <?php if ($success): ?>
            <div class="status-icon text-success">✓</div>
            <h2 class="mb-4">Database Setup Successful</h2>
            <div class="alert alert-success bg-opacity-10 border-success text-light text-start mb-4" role="alert">
                <?php echo $message; ?>
            </div>
            <a href="../index.html" class="btn btn-custom w-100 py-3 mt-2">Go to Home Page</a>
        <?php else: ?>
            <div class="status-icon text-danger">✗</div>
            <h2 class="mb-4">Database Setup Failed</h2>
            <div class="alert alert-danger bg-opacity-10 border-danger text-light text-start mb-4" role="alert">
                <strong>Error details:</strong><br>
                <?php foreach ($errors as $error): ?>
                    • <?php echo htmlspecialchars($error); ?><br>
                <?php endforeach; ?>
            </div>
            <a href="setup.php" class="btn btn-outline-light w-100 py-3 mt-2">Retry Setup</a>
        <?php endif; ?>
    </div>
</body>
</html>
