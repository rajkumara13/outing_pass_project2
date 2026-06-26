<?php
// dashboard.php - Admin Dashboard Panel

session_start();

// Verify user session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.html?error=unauthorized');
    exit;
}

// Include database connection
require_once '../config/db.php';

try {
    // 1. Fetch Stats - Total Students
    $stmt = $conn->query("SELECT COUNT(*) FROM students");
    $total_students = $stmt->fetchColumn();

    // 2. Fetch Stats - Inside Students
    $stmt = $conn->query("SELECT COUNT(*) FROM students WHERE status = 'Inside'");
    $inside_students = $stmt->fetchColumn();

    // 3. Fetch Stats - Outside Students
    $stmt = $conn->query("SELECT COUNT(*) FROM students WHERE status = 'Outside'");
    $outside_students = $stmt->fetchColumn();

    // 4. Fetch Stats - Pending Home Pass Requests
    $stmt = $conn->query("SELECT COUNT(*) FROM homepass WHERE status = 'Pending'");
    $pending_home_passes = $stmt->fetchColumn();

    // 5. Fetch Recent Activity Logs
    $stmt = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $recent_logs = $stmt->fetchAll();

    // 6. Fetch Active Outings
    $stmt = $conn->query("
        SELECT o.*, s.name, s.roll_number, s.room_number 
        FROM outing o 
        JOIN students s ON o.student_id = s.id 
        WHERE o.status = 'Outside' 
        ORDER BY o.exit_time DESC LIMIT 5
    ");
    $active_outings = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard query failure: " . $e->getMessage());
    $total_students = $inside_students = $outside_students = $pending_home_passes = 0;
    $recent_logs = [];
    $active_outings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Hostel Outpass System</title>
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
                    <a href="dashboard.php" class="nav-link-custom active">
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
                    <a href="apply_homepass.php" class="nav-link-custom">
                        <i class="fa-solid fa-house-chimney-window"></i>
                        <span>Apply Home Pass</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="approve_homepass.php" class="nav-link-custom">
                        <i class="fa-solid fa-house-circle-check"></i>
                        <span>Home Pass Approvals 
                            <?php if ($pending_home_passes > 0): ?>
                                <span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size: 0.7rem;"><?php echo $pending_home_passes; ?></span>
                            <?php endif; ?>
                        </span>
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
                        <span class="text-light" style="font-size: 0.9rem; font-weight:600;"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
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
                    <h1 class="h3 fw-bold mb-0">Dashboard Overview</h1>
                    <p class="text-secondary mb-0">System status and overview stats</p>
                </div>
                <div class="text-secondary" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-calendar-day me-1"></i> <?php echo date('l, d M Y'); ?>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="row g-4 mb-5">
                <!-- Total Students -->
                <div class="col-sm-6 col-lg-3">
                    <div class="glass-card stat-card total">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="stat-number"><?php echo $total_students; ?></h3>
                                <div class="stat-label">Total Students</div>
                            </div>
                            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Inside Hostel -->
                <div class="col-sm-6 col-lg-3">
                    <div class="glass-card stat-card inside">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="stat-number text-success"><?php echo $inside_students; ?></h3>
                                <div class="stat-label">Students Inside</div>
                            </div>
                            <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Outside Hostel -->
                <div class="col-sm-6 col-lg-3">
                    <div class="glass-card stat-card outside">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="stat-number text-danger"><?php echo $outside_students; ?></h3>
                                <div class="stat-label">Students Outside</div>
                            </div>
                            <div class="stat-icon"><i class="fa-solid fa-door-open"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Pending Home Pass -->
                <div class="col-sm-6 col-lg-3">
                    <div class="glass-card stat-card pending">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="stat-number text-warning"><?php echo $pending_home_passes; ?></h3>
                                <div class="stat-label">Pending Home Passes</div>
                            </div>
                            <div class="stat-icon"><i class="fa-solid fa-house-chimney-crack"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Active Outings List -->
                <div class="col-lg-7">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h5 fw-bold mb-0">Students Currently Outside (Outing)</h2>
                            <a href="reports.php" class="btn btn-sm btn-outline-light" style="font-size:0.8rem;">View All</a>
                        </div>
                        <div class="table-custom-container">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Roll Number</th>
                                        <th>Room No</th>
                                        <th>Exit Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($active_outings) > 0): ?>
                                        <?php foreach ($active_outings as $outing): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($outing['name']); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($outing['roll_number']); ?></span></td>
                                                <td><?php echo htmlspecialchars($outing['room_number']); ?></td>
                                                <td><?php echo date('d M, h:i A', strtotime($outing['exit_time'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-secondary py-4">No students currently outside on outings.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Logs -->
                <div class="col-lg-5">
                    <div class="glass-card p-4 h-100">
                        <h2 class="h5 fw-bold mb-4">Recent Activity Logs</h2>
                        <div class="log-list">
                            <?php if (count($recent_logs) > 0): ?>
                                <?php foreach ($recent_logs as $log): ?>
                                    <div class="log-item px-0">
                                        <div class="log-details">
                                            <span class="log-action"><?php echo htmlspecialchars($log['action']); ?></span>
                                            <small class="log-time text-secondary"><?php echo date('d M Y, H:i:s', strtotime($log['created_at'])); ?></small>
                                            <p class="mb-0 text-secondary mt-1" style="font-size:0.85rem;"><?php echo htmlspecialchars($log['details']); ?></p>
                                        </div>
                                        <div>
                                            <span class="log-user"><?php echo htmlspecialchars($log['user_type']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-secondary py-4">No recent logs found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
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
