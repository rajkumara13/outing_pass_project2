<?php
// approve_homepass.php - Security Gatekeeper Home Pass Review, Approval, and Rejection
session_start();

// Verify security session
if (!isset($_SESSION['security_logged_in']) || $_SESSION['security_logged_in'] !== true) {
    header('Location: login.html?error=unauthorized');
    exit;
}

// Include database connection
require_once '../config/db.php';

$message = '';
$alert_class = 'alert-success';

// Process Approval/Rejection Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $homepass_id = intval($_POST['homepass_id'] ?? 0);
    $action = $_POST['action']; // 'approve' or 'reject'
    $remarks = trim($_POST['remarks'] ?? '');

    if ($homepass_id > 0 && ($action === 'approve' || $action === 'reject')) {
        try {
            $status = ($action === 'approve') ? 'Approved' : 'Rejected';
            
            // 1. Fetch home pass details for logging
            $fetch_stmt = $conn->prepare("
                SELECT h.*, s.name, s.roll_number 
                FROM homepass h 
                JOIN students s ON h.student_id = s.id 
                WHERE h.id = :id
            ");
            $fetch_stmt->execute([':id' => $homepass_id]);
            $hp_record = $fetch_stmt->fetch();
            
            if ($hp_record) {
                // 2. Update home pass status
                $update_stmt = $conn->prepare("
                    UPDATE homepass 
                    SET status = :status, admin_remarks = :remarks 
                    WHERE id = :id
                ");
                $update_stmt->execute([
                    ':status' => $status,
                    ':remarks' => $remarks,
                    ':id' => $homepass_id
                ]);
                
                // 3. Log action as Security Gatekeeper
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, details) VALUES ('Security', :security_id, :action, :details)");
                $log_stmt->execute([
                    ':security_id' => $_SESSION['security_id'],
                    ':action' => "$status Home Pass",
                    ':details' => "Home Pass request of " . $hp_record['name'] . " (" . $hp_record['roll_number'] . ") was $status by Security Gatekeeper. Remarks: $remarks"
                ]);
                
                $message = "Home pass request has been successfully " . strtolower($status) . ".";
                $alert_class = ($status === 'Approved') ? 'alert-success' : 'alert-warning';
            } else {
                $message = "Home pass record not found.";
                $alert_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            error_log("Homepass process SQL error: " . $e->getMessage());
            $message = "A database error occurred. Action failed.";
            $alert_class = 'alert-danger';
        }
    }
}

// Fetch pending requests
try {
    $stmt = $conn->query("
        SELECT h.*, s.name, s.roll_number, s.room_number, s.hostel_type 
        FROM homepass h 
        JOIN students s ON h.student_id = s.id 
        WHERE h.status = 'Pending' 
        ORDER BY h.created_at ASC
    ");
    $pending_requests = $stmt->fetchAll();

    // Fetch history (Approved/Rejected)
    $stmt = $conn->query("
        SELECT h.*, s.name, s.roll_number, s.room_number 
        FROM homepass h 
        JOIN students s ON h.student_id = s.id 
        WHERE h.status != 'Pending' 
        ORDER BY h.created_at DESC LIMIT 10
    ");
    $history_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Homepass fetch SQL error: " . $e->getMessage());
    $pending_requests = [];
    $history_requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Pass Approvals - Student Hostel Outpass System</title>
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
                <i class="fa-solid fa-shield-halved text-primary"></i>
                <span>Outpass Security</span>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link-custom">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Console Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../outing/scan.php" class="nav-link-custom">
                        <i class="fa-solid fa-person-walking-arrow-right text-success"></i>
                        <span>Outing Scan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../homepass/scan.php" class="nav-link-custom">
                        <i class="fa-solid fa-house-chimney-user text-warning"></i>
                        <span>Home Pass Scan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="approve_homepass.php" class="nav-link-custom active">
                        <i class="fa-solid fa-house-circle-check text-warning"></i>
                        <span>Home Pass Approvals
                            <?php if (count($pending_requests) > 0): ?>
                                <span class="badge bg-warning text-dark rounded-pill ms-1"><?php echo count($pending_requests); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-user">
                <div class="d-flex align-items-center gap-2 px-2 text-secondary mb-3">
                    <i class="fa-solid fa-shield-halved fa-2x text-primary"></i>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span class="text-light" style="font-size: 0.9rem; font-weight:600;"><?php echo htmlspecialchars($_SESSION['security_name']); ?></span>
                        <span style="font-size: 0.75rem;">Gate Guard</span>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-outline-danger w-100 btn-confirm-delete" data-confirm-message="Are you sure you want to logout?">
                    <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                </a>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="main-content animate-fade-in">
            <div class="mb-4">
                <h1 class="h3 fw-bold mb-0">Home Pass Gate Approvals</h1>
                <p class="text-secondary mb-0">Approve or reject students requests for long-term home leaves directly from gate</p>
            </div>

            <!-- Status Alert -->
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $alert_class; ?> bg-opacity-10 alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-circle-info me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Pending Requests Table -->
            <div class="glass-card p-4 mb-5">
                <h2 class="h5 fw-bold mb-4"><i class="fa-solid fa-clock-rotate-left me-2 text-warning"></i>Pending Requests</h2>
                <div class="table-custom-container">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Student details</th>
                                <th>Room & Block</th>
                                <th>Leave Dates</th>
                                <th>Reason</th>
                                <th>Gate Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pending_requests) > 0): ?>
                                <?php foreach ($pending_requests as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></div>
                                            <small class="text-secondary"><?php echo htmlspecialchars($row['roll_number']); ?></small>
                                        </td>
                                        <td>
                                            Room: <?php echo htmlspecialchars($row['room_number']); ?><br>
                                            <small class="text-secondary"><?php echo htmlspecialchars($row['hostel_type']); ?></small>
                                        </td>
                                        <td>
                                            <strong>From:</strong> <?php echo date('d M Y', strtotime($row['leave_date'])); ?><br>
                                            <strong>To:</strong> <?php echo date('d M Y', strtotime($row['return_date'])); ?>
                                        </td>
                                        <td>
                                            <div style="max-width: 250px; white-space: normal; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($row['reason']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <form action="approve_homepass.php" method="POST" class="d-flex flex-column gap-2" style="max-width: 240px;">
                                                <input type="hidden" name="homepass_id" value="<?php echo $row['id']; ?>">
                                                <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Add remarks (optional)">
                                                <div class="d-flex gap-2">
                                                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-success flex-grow-1">
                                                        <i class="fa-solid fa-circle-check me-1"></i>Approve
                                                    </button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger flex-grow-1">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i>Reject
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-5">No pending home pass requests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Approval/Rejection History -->
            <div class="glass-card p-4">
                <h2 class="h5 fw-bold mb-4"><i class="fa-solid fa-history me-2 text-info"></i>Recent Approvals History (Last 10)</h2>
                <div class="table-custom-container">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Leave Dates</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($history_requests) > 0): ?>
                                <?php foreach ($history_requests as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></div>
                                            <small class="text-secondary"><?php echo htmlspecialchars($row['roll_number']); ?></small>
                                        </td>
                                        <td>
                                            <strong>From:</strong> <?php echo date('d M Y', strtotime($row['leave_date'])); ?><br>
                                            <strong>To:</strong> <?php echo date('d M Y', strtotime($row['return_date'])); ?>
                                        </td>
                                        <td style="font-size: 0.9rem;"><?php echo htmlspecialchars($row['reason']); ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'Approved'): ?>
                                                <span class="badge-inside">Approved</span>
                                            <?php else: ?>
                                                <span class="badge-outside">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 0.9rem;" class="text-secondary">
                                            <?php echo !empty($row['admin_remarks']) ? htmlspecialchars($row['admin_remarks']) : '<em>No remarks</em>'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-5">No approval history found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    <!-- Global Script -->
    <script src="../js/script.js"></script>
</body>
</html>
