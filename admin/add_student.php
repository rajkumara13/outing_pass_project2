<?php
// add_student.php - Process Add Student Form

session_start();

// Verify user session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.html?error=unauthorized');
    exit;
}

// Include database connection
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extract and sanitize inputs
    $roll_number = strtoupper(trim($_POST['roll_number'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $year = intval($_POST['year'] ?? 0);
    $room_number = trim($_POST['room_number'] ?? '');
    $hostel_type = trim($_POST['hostel_type'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Check for empty fields
    if (empty($roll_number) || empty($name) || empty($department) || empty($year) || empty($room_number) || empty($hostel_type) || empty($phone) || empty($email)) {
        header('Location: add_student.html?error=empty');
        exit;
    }

    try {
        // 1. Check if roll number already exists
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE roll_number = :roll_number");
        $check_stmt->execute([':roll_number' => $roll_number]);
        if ($check_stmt->fetchColumn() > 0) {
            header('Location: add_student.html?error=exists');
            exit;
        }

        // 2. Handle photo upload
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            header('Location: add_student.html?error=upload');
            exit;
        }

        $file_name = $_FILES['photo']['name'];
        $file_size = $_FILES['photo']['size'];
        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png'];

        // Validate extension and size (2MB max)
        if (!in_array($file_ext, $allowed_exts) || $file_size > 2 * 1024 * 1024) {
            header('Location: add_student.html?error=upload');
            exit;
        }

        // Create upload directory if not exists
        $upload_dir = '../uploads/student_photos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Create unique image filename
        $new_image_name = $roll_number . "_" . time() . "." . $file_ext;
        $dest_path = $upload_dir . $new_image_name;

        if (!move_uploaded_file($file_tmp, $dest_path)) {
            header('Location: add_student.html?error=upload');
            exit;
        }

        // 3. Insert student details into the database
        $insert_stmt = $conn->prepare("
            INSERT INTO students (roll_number, name, department, year, room_number, hostel_type, phone, email, photo, status) 
            VALUES (:roll_number, :name, :department, :year, :room_number, :hostel_type, :phone, :email, :photo, 'Inside')
        ");
        
        $insert_stmt->execute([
            ':roll_number' => $roll_number,
            ':name' => $name,
            ':department' => $department,
            ':year' => $year,
            ':room_number' => $room_number,
            ':hostel_type' => $hostel_type,
            ':phone' => $phone,
            ':email' => $email,
            ':photo' => $new_image_name
        ]);

        $student_id = $conn->lastInsertId();

        // 4. Log the action
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, details) VALUES ('Admin', :admin_id, 'Add Student', :details)");
        $log_stmt->execute([
            ':admin_id' => $_SESSION['admin_id'],
            ':details' => "Registered student $name ($roll_number) in $hostel_type Room $room_number"
        ]);

        // 5. Redirect to QR generation controller
        header("Location: generate_qr.php?roll_number=" . urlencode($roll_number));
        exit;

    } catch (PDOException $e) {
        error_log("Database Error during student registration: " . $e->getMessage());
        header('Location: add_student.html?error=db');
        exit;
    }
} else {
    header('Location: add_student.html');
    exit;
}
?>
