<?php
// edit_student.php - Process Student Detail Edits

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
    $old_roll_number = strtoupper(trim($_POST['old_roll_number'] ?? ''));

    // Check for empty fields
    if (empty($roll_number) || empty($name) || empty($department) || empty($year) || empty($room_number) || empty($hostel_type) || empty($phone) || empty($email)) {
        header('Location: view_students.php?error=empty');
        exit;
    }

    try {
        // Fetch current student details
        $stmt = $conn->prepare("SELECT photo, name FROM students WHERE roll_number = :roll");
        $stmt->execute([':roll' => $old_roll_number]);
        $student = $stmt->fetch();

        if (!$student) {
            header('Location: view_students.php?error=not_found');
            exit;
        }

        $photo_filename = $student['photo'];

        // Process photo upload if a new file is uploaded
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['photo']['name'];
            $file_size = $_FILES['photo']['size'];
            $file_tmp = $_FILES['photo']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_exts = ['jpg', 'jpeg', 'png'];

            // Validate extension and size (2MB max)
            if (in_array($file_ext, $allowed_exts) && $file_size <= 2 * 1024 * 1024) {
                $upload_dir = '../uploads/student_photos/';
                
                // Delete old photo if it exists
                if (!empty($photo_filename)) {
                    $old_photo_path = $upload_dir . $photo_filename;
                    if (file_exists($old_photo_path)) {
                        @unlink($old_photo_path);
                    }
                }
                
                // Create unique new image filename
                $photo_filename = $roll_number . "_" . time() . "." . $file_ext;
                $dest_path = $upload_dir . $photo_filename;
                
                if (!move_uploaded_file($file_tmp, $dest_path)) {
                    header('Location: view_students.php?error=upload');
                    exit;
                }
            } else {
                header('Location: view_students.php?error=upload');
                exit;
            }
        }

        // Update database record
        $update_stmt = $conn->prepare("
            UPDATE students 
            SET name = :name, department = :department, year = :year, room_number = :room_number, 
                hostel_type = :hostel_type, phone = :phone, email = :email, photo = :photo
            WHERE roll_number = :old_roll
        ");
        
        $update_stmt->execute([
            ':name' => $name,
            ':department' => $department,
            ':year' => $year,
            ':room_number' => $room_number,
            ':hostel_type' => $hostel_type,
            ':phone' => $phone,
            ':email' => $email,
            ':photo' => $photo_filename,
            ':old_roll' => $old_roll_number
        ]);

        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, details) VALUES ('Admin', :admin_id, 'Edit Student', :details)");
        $log_stmt->execute([
            ':admin_id' => $_SESSION['admin_id'],
            ':details' => "Updated details of student $name ($roll_number)"
        ]);

        header('Location: view_students.php?success=edited');
        exit;

    } catch (PDOException $e) {
        error_log("Database Error during student update: " . $e->getMessage());
        header('Location: view_students.php?error=db');
        exit;
    }
} else {
    header('Location: view_students.php');
    exit;
}
?>
