<?php
// reports.php - Daily, Monthly, Student History, and Occupancy Reports Panel

session_start();

// Verify user session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.html?error=unauthorized');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Get current tab
$active_tab = $_GET['tab'] ?? 'daily';

// Initialize report variables
$daily_date = $_GET['daily_date'] ?? date('Y-m-d');
$monthly_month = $_GET['monthly_month'] ?? date('Y-m');
$student_roll = strtoupper(trim($_GET['student_roll'] ?? ''));

$daily_records = [];
$monthly_records = [];
$student_info = null;
$student_history = [];
$occupancy_records = [];

try {
    // ----------------------------------------------------
    // Tab 1: Daily Outpass Report (Outing + Homepass)
    // ----------------------------------------------------
    if ($active_tab === 'daily') {
        // Fetch daily outings
        $stmt_outings = $conn->prepare("
            SELECT o.id, s.name, s.roll_number, s.room_number, s.hostel_type, o.reason, o.exit_time, o.entry_time, 'Outing' as pass_type
            FROM outing o
            JOIN students s ON o.student_id = s.id
            WHERE DATE(o.exit_time) = :date OR DATE(o.entry_time) = :date
            ORDER BY o.exit_time DESC
        ");
        $stmt_outings->execute([':date' => $daily_date]);
        $daily_outings = $stmt_outings->fetchAll();

        // Fetch daily homepasses
        $stmt_hpasses = $conn->prepare("
            SELECT h.id, s.name, s.roll_number, s.room_number, s.hostel_type, h.reason, h.exit_time, h.entry_time, 'Home Pass' as pass_type
            FROM homepass h
            JOIN students s ON h.student_id = s.id
            WHERE (DATE(h.exit_time) = :date OR DATE(h.entry_time) = :date) AND h.status = 'Approved'
            ORDER BY h.exit_time DESC
        ");
        $stmt_hpasses->execute([':date' => $daily_date]);
        $daily_hpasses = $stmt_hpasses->fetchAll();

        // Merge and sort by exit time
        $daily_records = array_merge($daily_outings, $daily_hpasses);
        usort($daily_records, function($a, $b) {
            return strcmp($b['exit_time'], $a['exit_time']);
        });
    }

    // ----------------------------------------------------
    // Tab 2: Monthly Summary Report
    // ----------------------------------------------------
    if ($active_tab === 'monthly') {
        $year_month = explode('-', $monthly_month);
        $year = $year_month[0];
        $month = $year_month[1];

        // Fetch monthly outings
        $stmt_m_outings = $conn->prepare("
            SELECT o.id, s.name, s.roll_number, s.hostel_type, o.reason, o.exit_time, o.entry_time, 'Outing' as pass_type
            FROM outing o
            JOIN students s ON o.student_id = s.id
            WHERE YEAR(o.exit_time) = :year AND MONTH(o.exit_time) = :month
            ORDER BY o.exit_time DESC
        ");
        $stmt_m_outings->execute([':year' => $year, ':month' => $month]);
        $monthly_outings = $stmt_m_outings->fetchAll();

        // Fetch monthly homepasses
        $stmt_m_hpasses = $conn->prepare("
            SELECT h.id, s.name, s.roll_number, s.hostel_type, h.reason, h.exit_time, h.entry_time, 'Home Pass' as pass_type
            FROM homepass h
            JOIN students s ON h.student_id = s.id
            WHERE YEAR(h.exit_time) = :year AND MONTH(h.exit_time) = :month AND h.status = 'Approved'
            ORDER BY h.exit_time DESC
        ");
        $stmt_m_hpasses->execute([':year' => $year, ':month' => $month]);
        $monthly_hpasses = $stmt_m_hpasses->fetchAll();

        $monthly_records = array_merge($monthly_outings, $monthly_hpasses);
        usort($monthly_records, function($a, $b) {
            return strcmp($b['exit_time'], $a['exit_time']);
        });
    }

    // ----------------------------------------------------
    // Tab 3: Student History Search
    // ----------------------------------------------------
    if ($active_tab === 'student' && !empty($student_roll)) {
        // Fetch student profile
        $stmt_student = $conn->prepare("SELECT * FROM students WHERE roll_number = :roll");
        $stmt_student->execute([':roll' => $student_roll]);
        $student_info = $stmt_student->fetch();

        if ($student_info) {
            // Fetch outing history
            $stmt_sh_outings = $conn->prepare("
                SELECT id, reason, exit_time, entry_time, 'Outing' as pass_type
                FROM outing
                WHERE student_id = :id
            ");
            $stmt_sh_outings->execute([':id' => $student_info['id']]);
            $sh_outings = $stmt_sh_outings->fetchAll();

            // Fetch homepass history
            $stmt_sh_hpasses = $conn->prepare("
                SELECT id, reason, exit_time, entry_time, 'Home Pass' as pass_type
                FROM homepass
                WHERE student_id = :id AND status = 'Approved'
            ");
            $stmt_sh_hpasses->execute([':id' => $student_info['id']]);
            $sh_hpasses = $stmt_sh_hpasses->fetchAll();

            $student_history = array_merge($sh_outings, $sh_hpasses);
            usort($student_history, function($a, $b) {
                return strcmp($b['exit_time'], $a['exit_time']);
            });
        }
    }

    // ----------------------------------------------------
    // Tab 4: Hostel Occupancy Report
    // ----------------------------------------------------
    if ($active_tab === 'occupancy') {
        // Group and count inside / outside per block
        $stmt_occ = $conn->query("
            SELECT hostel_type,
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'Inside' THEN 1 ELSE 0 END) as inside_count,
                   SUM(CASE WHEN status = 'Outside' THEN 1 ELSE 0 END) as outside_count
            FROM students
            GROUP BY hostel_type
        ");
        $occupancy_records = $stmt_occ->fetchAll();
    }

} catch (PDOException $e) {
    error_log("Reports panel SQL error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Panel - Student Hostel Outpass System</title>
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
        <div class="sidebar no-print">
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
                    <a href="apply_homepass.php" class="nav-link-custom">
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
                    <a href="reports.php" class="nav-link-custom active">
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
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <div>
                    <h1 class="h3 fw-bold mb-0">System Reports</h1>
                    <p class="text-secondary mb-0">Generate daily, monthly, student outpass histories, and block occupancy reports</p>
                </div>
                <button class="btn btn-secondary-custom" onclick="printReport()">
                    <i class="fa-solid fa-print me-2"></i>Print Report
                </button>
            </div>

            <!-- Print Header (Hidden on screen) -->
            <div class="d-none d-print-block text-center mb-4">
                <h2>Hostel Outpass System Report</h2>
                <h4 class="text-muted">Generated on <?php echo date('d M Y, H:i A'); ?></h4>
                <hr>
            </div>

            <!-- Nav Tabs Selection (Hidden on print) -->
            <ul class="nav nav-pills gap-2 mb-4 pb-2 border-bottom border-secondary border-opacity-25 no-print">
                <li class="nav-item">
                    <a class="nav-link-custom <?php echo $active_tab === 'daily' ? 'active' : ''; ?>" href="reports.php?tab=daily">
                        <i class="fa-solid fa-calendar-day me-1"></i> Daily Outpass
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link-custom <?php echo $active_tab === 'monthly' ? 'active' : ''; ?>" href="reports.php?tab=monthly">
                        <i class="fa-solid fa-calendar-week me-1"></i> Monthly Summary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link-custom <?php echo $active_tab === 'student' ? 'active' : ''; ?>" href="reports.php?tab=student">
                        <i class="fa-solid fa-address-book me-1"></i> Student History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link-custom <?php echo $active_tab === 'occupancy' ? 'active' : ''; ?>" href="reports.php?tab=occupancy">
                        <i class="fa-solid fa-percent me-1"></i> Block Occupancy
                    </a>
                </li>
            </ul>

            <!-- Active Tab Content -->
            
            <!-- 1. Daily Report -->
            <?php if ($active_tab === 'daily'): ?>
                <div class="glass-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 no-print">
                        <h2 class="h5 fw-bold mb-0">Daily Outpass Records</h2>
                        <form method="GET" action="reports.php" class="d-flex gap-2">
                            <input type="hidden" name="tab" value="daily">
                            <input type="date" name="daily_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($daily_date); ?>" onchange="this.form.submit()">
                        </form>
                    </div>
                    
                    <h3 class="h6 text-secondary d-none d-print-block mb-3">Daily outpass logs for: <?php echo date('d M Y', strtotime($daily_date)); ?></h3>
                    
                    <div class="table-custom-container">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Roll Number</th>
                                    <th>Room & Block</th>
                                    <th>Type</th>
                                    <th>Reason</th>
                                    <th>Exit Time</th>
                                    <th>Entry Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($daily_records) > 0): ?>
                                    <?php foreach ($daily_records as $row): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['roll_number']); ?></span></td>
                                            <td>Room: <?php echo htmlspecialchars($row['room_number']); ?> (<?php echo htmlspecialchars($row['hostel_type']); ?>)</td>
                                            <td>
                                                <span class="badge <?php echo $row['pass_type'] === 'Outing' ? 'bg-primary' : 'bg-info'; ?>">
                                                    <?php echo $row['pass_type']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                            <td><?php echo !empty($row['exit_time']) ? date('d M, h:i A', strtotime($row['exit_time'])) : '<em>Not Exited</em>'; ?></td>
                                            <td><?php echo !empty($row['entry_time']) ? date('d M, h:i A', strtotime($row['entry_time'])) : '<span class="text-warning">Outside</span>'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-5">No outpass records found for this date.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 2. Monthly Report -->
            <?php if ($active_tab === 'monthly'): ?>
                <div class="glass-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 no-print">
                        <h2 class="h5 fw-bold mb-0">Monthly Outpass Records</h2>
                        <form method="GET" action="reports.php" class="d-flex gap-2">
                            <input type="hidden" name="tab" value="monthly">
                            <input type="month" name="monthly_month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($monthly_month); ?>" onchange="this.form.submit()">
                        </form>
                    </div>

                    <h3 class="h6 text-secondary d-none d-print-block mb-3">Monthly summary for: <?php echo date('F Y', strtotime($monthly_month . '-01')); ?></h3>
                    
                    <div class="table-custom-container">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Roll Number</th>
                                    <th>Hostel Block</th>
                                    <th>Type</th>
                                    <th>Reason</th>
                                    <th>Exit Time</th>
                                    <th>Entry Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($monthly_records) > 0): ?>
                                    <?php foreach ($monthly_records as $row): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['roll_number']); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['hostel_type']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $row['pass_type'] === 'Outing' ? 'bg-primary' : 'bg-info'; ?>">
                                                    <?php echo $row['pass_type']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                            <td><?php echo !empty($row['exit_time']) ? date('d M, h:i A', strtotime($row['exit_time'])) : '<em>Not Exited</em>'; ?></td>
                                            <td><?php echo !empty($row['entry_time']) ? date('d M, h:i A', strtotime($row['entry_time'])) : '<span class="text-warning">Outside</span>'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-5">No records found for this month.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 3. Student History -->
            <?php if ($active_tab === 'student'): ?>
                <div class="glass-card p-4 mb-4 no-print">
                    <h2 class="h5 fw-bold mb-3">Search Student Outpass History</h2>
                    <form method="GET" action="reports.php" class="row g-3">
                        <input type="hidden" name="tab" value="student">
                        <div class="col-sm-8 col-md-6 col-lg-5">
                            <input type="text" name="student_roll" class="form-control" placeholder="Enter student Roll Number (e.g. 20IT105)" value="<?php echo htmlspecialchars($student_roll); ?>" required>
                        </div>
                        <div class="col-sm-4 col-md-3">
                            <button type="submit" class="btn btn-primary-custom w-100">Search History</button>
                        </div>
                    </form>
                </div>

                <?php if ($student_info): ?>
                    <!-- Student Profile Info -->
                    <div class="glass-card p-4 mb-4">
                        <div class="row g-4 align-items-center">
                            <div class="col-auto text-center text-sm-start">
                                <?php if (!empty($student_info['photo']) && file_exists('../uploads/student_photos/' . $student_info['photo'])): ?>
                                    <img src="../uploads/student_photos/<?php echo htmlspecialchars($student_info['photo']); ?>" class="student-avatar-lg" alt="Profile">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-circle fa-6x text-secondary"></i>
                                <?php endif; ?>
                            </div>
                            <div class="col">
                                <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($student_info['name']); ?></h3>
                                <p class="badge bg-primary mb-3" style="font-size:0.9rem;"><?php echo htmlspecialchars($student_info['roll_number']); ?></p>
                                
                                <div class="row g-2" style="font-size: 0.95rem;">
                                    <div class="col-sm-6 text-secondary">
                                        <strong>Department:</strong> <span class="text-light"><?php echo htmlspecialchars($student_info['department']); ?> (<?php echo htmlspecialchars($student_info['year']); ?> Year)</span>
                                    </div>
                                    <div class="col-sm-6 text-secondary">
                                        <strong>Hostel Block:</strong> <span class="text-light"><?php echo htmlspecialchars($student_info['hostel_type']); ?>, Room <?php echo htmlspecialchars($student_info['room_number']); ?></span>
                                    </div>
                                    <div class="col-sm-6 text-secondary">
                                        <strong>Phone:</strong> <span class="text-light"><?php echo htmlspecialchars($student_info['phone']); ?></span>
                                    </div>
                                    <div class="col-sm-6 text-secondary">
                                        <strong>Current Status:</strong> 
                                        <?php if ($student_info['status'] === 'Inside'): ?>
                                            <span class="badge-inside text-success bg-transparent border-0 p-0 font-weight-bold">Inside Hostel</span>
                                        <?php else: ?>
                                            <span class="badge-outside text-danger bg-transparent border-0 p-0 font-weight-bold">Outside Hostel</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- History Table -->
                    <div class="glass-card p-4">
                        <h4 class="h5 fw-bold mb-4">Outpass Logs History</h4>
                        <div class="table-custom-container">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Outing Reason / Destination</th>
                                        <th>Exit Time</th>
                                        <th>Entry Time</th>
                                        <th>Duration Outside</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($student_history) > 0): ?>
                                        <?php foreach ($student_history as $log): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge <?php echo $log['pass_type'] === 'Outing' ? 'bg-primary' : 'bg-info'; ?>">
                                                        <?php echo $log['pass_type']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['reason']); ?></td>
                                                <td><?php echo !empty($log['exit_time']) ? date('d M Y, h:i A', strtotime($log['exit_time'])) : '<em>Not Exited</em>'; ?></td>
                                                <td><?php echo !empty($log['entry_time']) ? date('d M Y, h:i A', strtotime($log['entry_time'])) : '<span class="text-warning">Still Outside</span>'; ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($log['exit_time']) && !empty($log['entry_time'])) {
                                                        $exit = new DateTime($log['exit_time']);
                                                        $entry = new DateTime($log['entry_time']);
                                                        $interval = $exit->diff($entry);
                                                        echo $interval->format('%h hrs, %i mins');
                                                    } elseif (!empty($log['exit_time'])) {
                                                        $exit = new DateTime($log['exit_time']);
                                                        $now = new DateTime();
                                                        $interval = $exit->diff($now);
                                                        echo '<span class="text-warning">' . $interval->format('%h hrs, %i mins') . ' (Active)</span>';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-secondary py-5">No check-out records found for this student.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif ($student_roll): ?>
                    <div class="alert alert-danger bg-opacity-10 border-danger text-light py-4" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2 fa-lg"></i> Student with Roll Number <strong><?php echo htmlspecialchars($student_roll); ?></strong> not found in database.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- 4. Occupancy Report -->
            <?php if ($active_tab === 'occupancy'): ?>
                <div class="glass-card p-4 mb-4">
                    <h2 class="h5 fw-bold mb-4">Hostel Blocks Occupancy Status</h2>
                    <div class="table-custom-container">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Hostel Block</th>
                                    <th>Total Registered</th>
                                    <th>Students Inside</th>
                                    <th>Students Outside</th>
                                    <th>Occupancy Rate (Inside)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($occupancy_records) > 0): ?>
                                    <?php foreach ($occupancy_records as $row): ?>
                                        <?php 
                                        $rate = $row['total'] > 0 ? round(($row['inside_count'] / $row['total']) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($row['hostel_type']); ?></td>
                                            <td><span class="badge bg-secondary px-2.5 py-1.5"><?php echo $row['total']; ?></span></td>
                                            <td class="text-success"><?php echo $row['inside_count']; ?></td>
                                            <td class="text-danger"><?php echo $row['outside_count']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 10px; background-color: rgba(255,255,255,0.05); border-radius:5px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $rate; ?>%" aria-valuenow="<?php echo $rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <span style="font-size:0.9rem; font-weight:600;"><?php echo $rate; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-5">No occupancy records found. Make sure students are added first.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    <!-- Global Script -->
    <script src="../js/script.js"></script>
</body>
</html>
