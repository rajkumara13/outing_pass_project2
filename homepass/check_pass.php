<?php
// check_pass.php - Validates home pass approval and processes gate check-outs/check-ins

require_once '../config/db.php';

$roll_number = strtoupper(trim($_GET['roll_number'] ?? $_POST['roll_number'] ?? ''));

if (empty($roll_number)) {
    header('Location: scan.php');
    exit;
}

$student = null;
$homepass = null;
$is_approved = false;
$error_type = '';

try {
    // 1. Fetch student details
    $stmt = $conn->prepare("SELECT * FROM students WHERE roll_number = :roll LIMIT 1");
    $stmt->execute([':roll' => $roll_number]);
    $student = $stmt->fetch();

    if (!$student) {
        $error_type = 'student_not_found';
    } else {
        $student_status = $student['status']; // 'Inside' or 'Outside'
        
        // 2. Fetch active approved homepass
        if ($student_status === 'Inside') {
            // Student is trying to exit - look for approved homepass not yet exited
            $hp_stmt = $conn->prepare("
                SELECT * FROM homepass 
                WHERE student_id = :id AND status = 'Approved' AND exit_time IS NULL AND entry_time IS NULL
                ORDER BY created_at DESC LIMIT 1
            ");
        } else {
            // Student is outside, returning - look for approved homepass checked out but not yet returned
            $hp_stmt = $conn->prepare("
                SELECT * FROM homepass 
                WHERE student_id = :id AND status = 'Approved' AND exit_time IS NOT NULL AND entry_time IS NULL
                ORDER BY created_at DESC LIMIT 1
            ");
        }
        
        $hp_stmt->execute([':id' => $student['id']]);
        $homepass = $hp_stmt->fetch();
        
        if ($homepass) {
            $is_approved = true;
        } else {
            $error_type = 'not_approved';
        }
    }

    // 3. Process Form Submission (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_approved) {
        $action = $_POST['action'] ?? '';
        $homepass_id = intval($_POST['homepass_id'] ?? 0);

        if ($action === 'exit' && $student_status === 'Inside') {
            $conn->beginTransaction();
            
            // Log exit time in homepass
            $up_hp = $conn->prepare("UPDATE homepass SET exit_time = CURRENT_TIMESTAMP WHERE id = :id");
            $up_hp->execute([':id' => $homepass_id]);

            // Update student status to Outside
            $up_std = $conn->prepare("UPDATE students SET status = 'Outside' WHERE id = :id");
            $up_std->execute([':id' => $student['id']]);

            // Log activity
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, action, details) VALUES ('System', 'Home Pass Exit', :details)");
            $log_stmt->execute([':details' => "Student " . $student['name'] . " ($roll_number) exited gate using approved Home Pass (ID: $homepass_id)"]);

            $conn->commit();
            header('Location: ../outing/success.php?status=success_exit&redirect=../homepass/scan.php');
            exit;

        } elseif ($action === 'entry' && $student_status === 'Outside') {
            $conn->beginTransaction();
            
            // Log entry time in homepass
            $up_hp = $conn->prepare("UPDATE homepass SET entry_time = CURRENT_TIMESTAMP WHERE id = :id");
            $up_hp->execute([':id' => $homepass_id]);

            // Update student status to Inside
            $up_std = $conn->prepare("UPDATE students SET status = 'Inside' WHERE id = :id");
            $up_std->execute([':id' => $student['id']]);

            // Log activity
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, action, details) VALUES ('System', 'Home Pass Entry', :details)");
            $log_stmt->execute([':details' => "Student " . $student['name'] . " ($roll_number) returned to hostel using approved Home Pass (ID: $homepass_id)"]);

            $conn->commit();
            header('Location: ../outing/success.php?status=success_entry&redirect=../homepass/scan.php');
            exit;
        }
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Homepass check SQL error: " . $e->getMessage());
    $error_type = 'system_error';
    $is_approved = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Pass Verification - Student Hostel Outpass System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="../css/all.min.css" rel="stylesheet">
    <!-- Global CSS and Dashboard CSS -->
    <link href="../css/style.css" rel="stylesheet">
    <link href="../css/dashboard.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .result-container {
            max-width: 600px;
            width: 100%;
        }

        .denied-card {
            border: 2px solid rgba(239, 68, 68, 0.4) !important;
            box-shadow: 0 10px 40px rgba(239, 68, 68, 0.2);
            background: rgba(28, 11, 11, 0.7);
            border-radius: 24px;
            padding: 3.5rem 2rem;
            text-align: center;
        }

        .denied-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            margin-bottom: 2rem;
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
            border: 2px solid rgba(239, 68, 68, 0.2);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }
    </style>
</head>
<body>
    <div class="result-container animate-fade-in">
        
        <?php if ($is_approved): ?>
            <!-- Approved Outpass Display -->
            <div class="glass-card p-4 p-md-5">
                <h2 class="fw-bold mb-4 text-center">
                    <?php if ($student['status'] === 'Inside'): ?>
                        <i class="fa-solid fa-house-chimney-user text-warning me-2"></i>Confirm Home Pass Exit
                    <?php else: ?>
                        <i class="fa-solid fa-right-to-bracket text-success me-2"></i>Confirm Home Pass Return
                    <?php endif; ?>
                </h2>

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
                            <strong>Pass Status:</strong> <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-0.5 rounded">Approved</span>
                        </div>
                    </div>
                </div>

                <!-- Outpass dates & reason -->
                <div class="alert alert-warning bg-opacity-10 border-warning text-light mb-4" role="alert" style="font-size: 0.95rem;">
                    <h4 class="alert-heading h6 fw-bold"><i class="fa-solid fa-calendar-days me-1"></i>Approved Outpass Duration:</h4>
                    <hr class="border-warning border-opacity-20 my-2">
                    <strong>Leave Date:</strong> <?php echo date('d M Y (l)', strtotime($homepass['leave_date'])); ?><br>
                    <strong>Return Date:</strong> <?php echo date('d M Y (l)', strtotime($homepass['return_date'])); ?><br>
                    <strong>Leave Reason:</strong> <?php echo htmlspecialchars($homepass['reason']); ?><br>
                    <?php if (!empty($homepass['admin_remarks'])): ?>
                        <strong>Admin Remarks:</strong> <?php echo htmlspecialchars($homepass['admin_remarks']); ?>
                    <?php endif; ?>
                </div>

                <form action="check_pass.php" method="POST">
                    <input type="hidden" name="roll_number" value="<?php echo htmlspecialchars($roll_number); ?>">
                    <input type="hidden" name="homepass_id" value="<?php echo $homepass['id']; ?>">
                    
                    <?php if ($student['status'] === 'Inside'): ?>
                        <input type="hidden" name="action" value="exit">
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary-custom flex-grow-1 py-3 fw-bold" style="background: linear-gradient(135deg, var(--accent-yellow) 0%, #b45309 100%);">
                                <i class="fa-solid fa-door-open me-2"></i>Confirm Exit
                            </button>
                            <a href="scan.php" class="btn btn-secondary-custom py-3 px-4">Cancel</a>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="action" value="entry">
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-success flex-grow-1 py-3 fw-bold">
                                <i class="fa-solid fa-right-to-bracket me-2"></i>Confirm Entry Check-In
                            </button>
                            <a href="scan.php" class="btn btn-secondary-custom py-3 px-4">Cancel</a>
                        </div>
                    <?php endif; ?>
                </form>

        <?php else: ?>
            <!-- Denied / Error Display -->
            <div class="denied-card">
                <div class="denied-icon">
                    <i class="fa-solid fa-hand-holding-hand fa-circle-xmark"></i>
                </div>
                
                <h2 class="fw-bold text-danger mb-2" style="font-size: 1.5rem;">HOME PASS NOT APPROVED</h2>
                
                <p class="text-secondary mb-0 px-3">
                    <?php 
                    if ($error_type === 'student_not_found') {
                        echo "Student Roll Number is not registered in the system.";
                    } elseif ($error_type === 'system_error') {
                        echo "A system database error occurred. Please contact admin.";
                    } else {
                        echo "No active approved home pass request was found for this student.";
                    }
                    ?>
                </p>
                
                <div class="text-secondary mt-5" style="font-size: 0.85rem;">
                    <i class="fa-solid fa-spinner fa-spin me-2"></i>Returning to home page in <span id="countdown">3</span> seconds...
                </div>
            </div>
            
            <script>
                // Play error buzz
                window.addEventListener('DOMContentLoaded', () => {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(150, ctx.currentTime);
                    gain.gain.setValueAtTime(0.15, ctx.currentTime);
                    osc.start();
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
                    osc.stop(ctx.currentTime + 0.55);
                });

                // Auto redirect count
                let timeLeft = 3;
                const countdownEl = document.getElementById('countdown');
                const timer = setInterval(() => {
                    timeLeft--;
                    countdownEl.innerText = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        window.location.href = 'scan.php';
                    }
                }, 1000);
            </script>
        <?php endif; ?>

    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
