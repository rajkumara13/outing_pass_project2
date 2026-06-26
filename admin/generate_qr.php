<?php
// generate_qr.php - Generate QR code for student and save path in database

session_start();

// Verify user session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.html?error=unauthorized');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get student roll number
$roll_number = strtoupper(trim($_GET['roll_number'] ?? ''));

if (empty($roll_number)) {
    header('Location: view_students.php?error=no_roll');
    exit;
}

try {
    // Check if student exists in database
    $stmt = $conn->prepare("SELECT id FROM students WHERE roll_number = :roll_number");
    $stmt->execute([':roll_number' => $roll_number]);
    $student = $stmt->fetch();

    if (!$student) {
        header('Location: view_students.php?error=not_found');
        exit;
    }

    // Set QR Code filename and directory
    $qr_dir = '../assets/qr/';
    if (!is_dir($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    
    // Check if GD extension is loaded to choose image format (PNG or SVG)
    $use_svg = !function_exists('imagecreate');
    $qr_ext = $use_svg ? '.svg' : '.png';

    $qr_filename = $roll_number . $qr_ext;
    $qr_filepath = $qr_dir . $qr_filename;
    $db_qr_path = "assets/qr/" . $qr_filename; // relative path stored in database

    // Generate the QR code locally using phpqrcode
    $generated = false;
    
    if (file_exists('phpqrcode.php')) {
        try {
            require_once 'phpqrcode.php';
            if ($use_svg) {
                // Generate QR code as SVG (does not require GD library)
                QRcode::svg($roll_number, $qr_filepath, QR_ECLEVEL_H, 6, 2);
            } else {
                // Generate QR code as PNG (requires GD library)
                QRcode::png($roll_number, $qr_filepath, QR_ECLEVEL_H, 6, 2);
            }
            $generated = true;
        } catch (Exception $e) {
            error_log("phpqrcode generation failed: " . $e->getMessage());
        }
    } else {
        header("Location: view_students.php?error=qr_lib_missing");
        exit;
    }

    if ($generated) {
        // Update qr_code path in database
        $update_stmt = $conn->prepare("UPDATE students SET qr_code = :qr_code WHERE roll_number = :roll_number");
        $update_stmt->execute([
            ':qr_code' => $db_qr_path,
            ':roll_number' => $roll_number
        ]);

        // Log action
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, details) VALUES ('Admin', :admin_id, 'Generate QR', :details)");
        $log_stmt->execute([
            ':admin_id' => $_SESSION['admin_id'],
            ':details' => "Generated QR Code for student: $roll_number"
        ]);

        header("Location: view_students.php?success=added&roll_number=" . urlencode($roll_number));
        exit;
    } else {
        header("Location: view_students.php?error=qr_failed");
        exit;
    }

} catch (PDOException $e) {
    error_log("Database Error during QR generation: " . $e->getMessage());
    header('Location: view_students.php?error=db');
    exit;
}
?>
