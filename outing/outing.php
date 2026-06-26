<?php
// outing.php - Handles student outings (exits and returns)

require_once '../config/db.php';

$roll_number = strtoupper(trim($_GET['roll_number'] ?? $_POST['roll_number'] ?? ''));

if (empty($roll_number)) {
    header('Location: scan.php');
    exit;
}

try {
    // 1. Fetch student details
    $stmt = $conn->prepare("SELECT * FROM students WHERE roll_number = :roll LIMIT 1");
    $stmt->execute([':roll' => $roll_number]);
    $student = $stmt->fetch();

    if (!$student) {
        // Redirect to success/error page with failed status
        header('Location: success.php?status=failed&error=student_not_found');
        exit;
    }

    $is_outside = ($student['status'] === 'Outside');
    $active_outing = null;

    // If student is currently outside, check if there is an active outing record
    if ($is_outside) {
        $stmt_out = $conn->prepare("SELECT * FROM outing WHERE student_id = :id AND status = 'Outside' ORDER BY exit_time DESC LIMIT 1");
        $stmt_out->execute([':id' => $student['id']]);
        $active_outing = $stmt_out->fetch();
        
        // Note: If no active outing record exists, they might be out on a home pass instead!
        // We will check that in the POST processing or warn the user.
    }

    // 2. Process Form Submission (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'exit') {
            // Processing Outing Exit
            $reason = trim($_POST['reason'] ?? 'Personal');
            if (empty($reason)) $reason = 'Personal';

            // Start transaction for consistency
            $conn->beginTransaction();

            // Insert outing record
            $ins_stmt = $conn->prepare("
                INSERT INTO outing (student_id, reason, exit_time, status) 
                VALUES (:student_id, :reason, CURRENT_TIMESTAMP, 'Outside')
            ");
            $ins_stmt->execute([
                ':student_id' => $student['id'],
                ':reason' => $reason
            ]);

            // Update student status to Outside
            $up_stmt = $conn->prepare("UPDATE students SET status = 'Outside' WHERE id = :id");
            $up_stmt->execute([':id' => $student['id']]);

            // Log activity
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, action, details) VALUES ('System', 'Outing Exit', :details)");
            $log_stmt->execute([':details' => "Student " . $student['name'] . " (" . $roll_number . ") checked out for outing. Reason: $reason"]);

            $conn->commit();
            
            header('Location: success.php?status=success_exit&redirect=scan.php');
            exit;

        } elseif (isset($_POST['action']) && $_POST['action'] === 'entry') {
            // Processing Outing Entry (Return check-in)
            $conn->beginTransaction();

            if ($active_outing) {
                // Update existing outing record
                $up_out_stmt = $conn->prepare("
                    UPDATE outing 
                    SET entry_time = CURRENT_TIMESTAMP, status = 'Returned' 
                    WHERE id = :id
                ");
                $up_out_stmt->execute([':id' => $active_outing['id']]);
            } else {
                // Fallback: If they were outside but no active outing log was found (e.g. they left on home pass or data mismatch),
                // we still check them in to ensure student is inside, and create an entry log.
                $ins_out_stmt = $conn->prepare("
                    INSERT INTO outing (student_id, reason, exit_time, entry_time, status) 
                    VALUES (:student_id, 'Returned without exit log', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Returned')
                ");
                $ins_out_stmt->execute([':student_id' => $student['id']]);
            }

            // Update student status back to Inside
            $up_student_stmt = $conn->prepare("UPDATE students SET status = 'Inside' WHERE id = :id");
            $up_student_stmt->execute([':id' => $student['id']]);

            // Log activity
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, action, details) VALUES ('System', 'Outing Entry', :details)");
            $log_stmt->execute([':details' => "Student " . $student['name'] . " (" . $roll_number . ") returned to hostel and checked in."]);

            $conn->commit();

            header('Location: success.php?status=success_entry&redirect=scan.php');
            exit;
        }
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Outing SQL Error: " . $e->getMessage());
    header('Location: success.php?status=failed&error=db_error');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outing Verification - Student Hostel Outpass System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="../css/all.min.css" rel="stylesheet">
    <!-- Global CSS and Dashboard CSS -->
    <link href="../css/style.css" rel="stylesheet">
    <link href="../css/dashboard.css" rel="stylesheet">
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem;">
    <div class="container" style="max-width: 650px;">
        <div class="glass-card p-4 p-md-5">
            <h2 class="fw-bold mb-4 text-center">
                <?php if ($is_outside): ?>
                    <i class="fa-solid fa-right-to-bracket text-success me-2 animate-pulse"></i>Confirm Entry Check-In
                <?php else: ?>
                    <i class="fa-solid fa-person-walking-arrow-right text-primary me-2"></i>Register Outing Exit
                <?php endif; ?>
            </h2>

            <!-- Student Profile Information Display -->
            <div class="row align-items-center mb-4 p-3 bg-dark bg-opacity-25 rounded-3 border border-secondary border-opacity-10">
                <div class="col-sm-auto text-center text-sm-start mb-3 mb-sm-0">
                    <?php if (!empty($student['photo']) && file_exists('../uploads/student_photos/' . $student['photo'])): ?>
                        <img src="../uploads/student_photos/<?php echo htmlspecialchars($student['photo']); ?>" class="student-avatar-lg" alt="Profile">
                    <?php else: ?>
                        <i class="fa-solid fa-user-circle fa-6x text-secondary"></i>
                    <?php endif; ?>
                </div>
                <div class="col">
                    <h3 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($student['name']); ?></h3>
                    <p class="badge bg-secondary mb-2"><?php echo htmlspecialchars($student['roll_number']); ?></p>
                    
                    <div style="font-size: 0.9rem;" class="text-secondary">
                        <strong>Department:</strong> <span class="text-light"><?php echo htmlspecialchars($student['department']); ?></span><br>
                        <strong>Room No:</strong> <span class="text-light"><?php echo htmlspecialchars($student['room_number']); ?></span> | <strong>Block:</strong> <span class="text-light"><?php echo htmlspecialchars($student['hostel_type']); ?></span><br>
                        <strong>Current Status:</strong> 
                        <?php if ($is_outside): ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Outside Hostel</span>
                        <?php else: ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">Inside Hostel</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Forms -->
            <form action="outing.php" method="POST">
                <!-- Pass student roll number to POST processing -->
                <input type="hidden" name="roll_number" value="<?php echo htmlspecialchars($roll_number); ?>">
                
                <?php if ($is_outside): ?>
                    <!-- Case B: Returning inside (check-in) -->
                    <input type="hidden" name="action" value="entry">
                    
                    <?php if ($active_outing): ?>
                        <div class="alert alert-warning bg-opacity-10 border-warning text-light mb-4" role="alert" style="font-size:0.95rem;">
                            <h4 class="alert-heading h6 fw-bold"><i class="fa-solid fa-clock me-1"></i>Active Outing Details:</h4>
                            <hr class="border-warning border-opacity-20 my-2">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($active_outing['reason']); ?><br>
                            <strong>Exit Time:</strong> <?php echo date('d M Y, h:i A', strtotime($active_outing['exit_time'])); ?>
                        </div>
                    <?php else: ?>
                        <!-- They might be outside on a homepass, let security check in anyway -->
                        <div class="alert alert-info bg-opacity-10 border-info text-light mb-4" role="alert" style="font-size:0.95rem;">
                            <i class="fa-solid fa-circle-info me-2"></i>Student was marked <strong>Outside</strong>, but no active outing record was found. They may have checked out under a Home Pass. Proceed to check them in anyway?
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-center text-secondary mb-4">Click "Confirm Entry Check-In" below to log the student's return and update status to Inside.</p>

                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-success flex-grow-1 py-3 fw-bold">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>Confirm Entry Check-In
                        </button>
                        <a href="scan.php" class="btn btn-secondary-custom py-3 px-4">Cancel</a>
                    </div>

                <?php else: ?>
                    <!-- Case A: Leaving outside (checkout) -->
                    <input type="hidden" name="action" value="exit">
                    
                    <div class="mb-4">
                        <label for="reason" class="form-label">Select Outing Reason *</label>
                        <select class="form-select" id="reason" name="reason" required>
                            <option value="Personal" selected>Personal</option>
                            <option value="Library">Library</option>
                            <option value="Medical">Medical</option>
                            <option value="Sports">Sports</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-primary-custom flex-grow-1 py-3 fw-bold" style="background: linear-gradient(135deg, var(--accent-green) 0%, #047857 100%);">
                            <i class="fa-solid fa-person-walking-arrow-right me-2"></i>Submit & Approve Exit
                        </button>
                        <a href="scan.php" class="btn btn-secondary-custom py-3 px-4">Cancel</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
