<?php
// apply_homepass.php - Admin submits Home Pass on behalf of students
session_start();

// Verify user session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.html?error=unauthorized');
    exit;
}

// Include database connection
require_once '../config/db.php';

$message = '';
$alert_class = 'alert-success';
$success_mode = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roll_number = strtoupper(trim($_POST['roll_number'] ?? ''));
    $leave_date = $_POST['leave_date'] ?? '';
    $return_date = $_POST['return_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $status = $_POST['status'] ?? 'Approved';
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($roll_number) || empty($leave_date) || empty($return_date) || empty($reason)) {
        $message = "Please fill out all required fields.";
        $alert_class = "alert-danger";
    } elseif ($leave_date > $return_date) {
        $message = "Return date must be on or after the leave date.";
        $alert_class = "alert-danger";
    } else {
        try {
            // 1. Verify student exists
            $stmt = $conn->prepare("SELECT id, name FROM students WHERE roll_number = :roll LIMIT 1");
            $stmt->execute([':roll' => $roll_number]);
            $student = $stmt->fetch();

            if (!$student) {
                $message = "Student with Roll Number <strong>$roll_number</strong> is not registered. Please check again.";
                $alert_class = "alert-danger";
            } else {
                // 2. Check if they already have an active pending or approved pass for those dates
                $stmt_pending = $conn->prepare("
                    SELECT COUNT(*) FROM homepass 
                    WHERE student_id = :id AND status IN ('Pending', 'Approved') AND entry_time IS NULL
                ");
                $stmt_pending->execute([':id' => $student['id']]);
                
                if ($stmt_pending->fetchColumn() > 0 && $status === 'Pending') {
                    $message = "This student already has an active or pending Home Pass in the system.";
                    $alert_class = "alert-warning";
                } else {
                    // 3. Insert new request (Status can be Approved or Pending)
                    $stmt_ins = $conn->prepare("
                        INSERT INTO homepass (student_id, leave_date, return_date, reason, status, admin_remarks) 
                        VALUES (:student_id, :leave_date, :return_date, :reason, :status, :remarks)
                    ");
                    $stmt_ins->execute([
                        ':student_id' => $student['id'],
                        ':leave_date' => $leave_date,
                        ':return_date' => $return_date,
                        ':reason' => $reason,
                        ':status' => $status,
                        ':remarks' => $remarks
                    ]);

                    // 4. Log activity
                    $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, details) VALUES ('Admin', :admin_id, 'Admin Apply Home Pass', :details)");
                    $log_stmt->execute([
                        ':admin_id' => $_SESSION['admin_id'],
                        ':details' => "Admin applied Home Pass for student " . $student['name'] . " ($roll_number). Status: $status, Remarks: $remarks"
                    ]);

                    $success_mode = true;
                    $message = "Home Pass successfully created for " . htmlspecialchars($student['name']) . " with status: <strong>$status</strong>!";
                }
            }
        } catch (PDOException $e) {
            error_log("Admin Homepass apply SQL error: " . $e->getMessage());
            $message = "A system error occurred. Please try again later.";
            $alert_class = "alert-danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Home Pass - Student Hostel Outpass System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="../css/all.min.css" rel="stylesheet">
    <!-- Global CSS and Dashboard CSS -->
    <link href="../css/style.css" rel="stylesheet">
    <link href="../css/dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="portal-brand">
                <i class="fa-solid fa-hotel"></i>
                <span>Outpass Admin</span>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link-custom">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_students.php" class="nav-link-custom">
                        <i class="fa-solid fa-user-graduate"></i>
                        <span>Students List</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="add_student.html" class="nav-link-custom">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Add Student</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="apply_homepass.php" class="nav-link-custom active">
                        <i class="fa-solid fa-house-chimney-window"></i>
                        <span>Apply Home Pass</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="approve_homepass.php" class="nav-link-custom">
                        <i class="fa-solid fa-house-circle-check"></i>
                        <span>Home Pass Approvals</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link-custom">
                        <i class="fa-solid fa-file-contract"></i>
                        <span>Reports Panel</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-user">
                <div class="d-flex align-items-center gap-2 px-2 text-secondary mb-3">
                    <i class="fa-solid fa-user-circle fa-2x"></i>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span class="text-light" style="font-size: 0.9rem; font-weight:600;">Admin</span>
                        <span style="font-size: 0.75rem;">Administrator</span>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-outline-danger w-100 btn-confirm-delete" data-confirm-message="Are you sure you want to logout?">
                    <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                </a>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="main-content animate-fade-in">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-0">Apply Home Pass</h1>
                    <p class="text-secondary mb-0">Submit a Home Pass outpass on behalf of a student</p>
                </div>
                <a href="dashboard.php" class="btn btn-secondary-custom">
                    <i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

            <div class="glass-card p-4 p-md-5">
                <!-- Status alerts -->
                <?php if (!empty($message)): ?>
                    <div class="alert <?php echo $alert_class; ?> bg-opacity-10 alert-dismissible fade show mb-4" role="alert">
                        <i class="fa-solid fa-circle-info me-2"></i><?php echo $message; ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="apply_homepass.php" method="POST" class="needs-validation" novalidate>
                    <div class="row g-4">
                        <!-- Student Roll Number -->
                        <div class="col-md-6">
                            <label for="roll_number" class="form-label">Student Roll Number *</label>
                            <input type="text" class="form-control" id="roll_number" name="roll_number" placeholder="e.g. 20IT105" required autocomplete="off" style="text-transform: uppercase;">
                            <div class="invalid-feedback">Roll Number is required.</div>
                        </div>

                        <!-- Initial Status -->
                        <div class="col-md-6">
                            <label for="status" class="form-label">Initial Pass Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Approved" selected>Approved (Active immediately)</option>
                                <option value="Pending">Pending (Requires review)</option>
                            </select>
                            <div class="invalid-feedback">Please select the initial status.</div>
                        </div>

                        <!-- Dates -->
                        <div class="col-md-6">
                            <label for="leave_date" class="form-label">Leave Start Date *</label>
                            <input type="date" class="form-control" id="leave_date" name="leave_date" required min="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">Leave Date is required.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="return_date" class="form-label">Return Date *</label>
                            <input type="date" class="form-control" id="return_date" name="return_date" required min="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">Return Date is required.</div>
                        </div>

                        <!-- Reason -->
                        <div class="col-12">
                            <label for="reason" class="form-label">Detailed Leave Reason *</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Provide reason for student's leave" required></textarea>
                            <div class="invalid-feedback">Please provide a reason.</div>
                        </div>

                        <!-- Remarks -->
                        <div class="col-12">
                            <label for="remarks" class="form-label">Admin Remarks (Optional)</label>
                            <input type="text" class="form-control" id="remarks" name="remarks" placeholder="Add any specific instructions or approvals">
                        </div>

                        <!-- Buttons -->
                        <div class="col-12 mt-4 d-flex gap-3">
                            <button type="submit" class="btn btn-primary-custom flex-grow-1 py-3">
                                <i class="fa-solid fa-paper-plane me-2"></i>Create Student Home Pass
                            </button>
                            <button type="reset" class="btn btn-secondary-custom px-4 py-3">
                                Reset Form
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    <!-- Global Script -->
    <script src="../js/script.js"></script>
    <script>
        // Adjust return date min attribute dynamically based on leave date choice
        const leaveDateInput = document.getElementById('leave_date');
        const returnDateInput = document.getElementById('return_date');
        
        if (leaveDateInput && returnDateInput) {
            leaveDateInput.addEventListener('change', () => {
                returnDateInput.min = leaveDateInput.value;
                if (returnDateInput.value && returnDateInput.value < leaveDateInput.value) {
                    returnDateInput.value = leaveDateInput.value;
                }
            });
        }
    </script>
</body>
</html>
