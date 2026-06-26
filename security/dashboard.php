<?php
// dashboard.php - Security Gatekeeper Dashboard

session_start();

// Verify user session
if (!isset($_SESSION['security_logged_in']) || $_SESSION['security_logged_in'] !== true) {
    header('Location: login.html?error=unauthorized');
    exit;
}

// Include database connection
require_once '../config/db.php';

// Initialize search result
$search_roll = strtoupper(trim($_GET['search_roll'] ?? ''));
$student_info = null;
$student_logs = [];
$search_error = '';

try {
    // 1. Process Roll Number Search
    if (!empty($search_roll)) {
        $stmt = $conn->prepare("SELECT * FROM students WHERE roll_number = :roll");
        $stmt->execute([':roll' => $search_roll]);
        $student_info = $stmt->fetch();
        
        if ($student_info) {
            // Get last 5 logs for this student (outings and homepasses combined)
            $sh_outings_stmt = $conn->prepare("
                SELECT exit_time, entry_time, reason, 'Outing' as pass_type
                FROM outing
                WHERE student_id = :id
            ");
            $sh_outings_stmt->execute([':id' => $student_info['id']]);
            $sh_outings = $sh_outings_stmt->fetchAll();

            $sh_hpasses_stmt = $conn->prepare("
                SELECT exit_time, entry_time, reason, 'Home Pass' as pass_type
                FROM homepass
                WHERE student_id = :id AND status = 'Approved'
            ");
            $sh_hpasses_stmt->execute([':id' => $student_info['id']]);
            $sh_hpasses = $sh_hpasses_stmt->fetchAll();

            $student_logs = array_merge($sh_outings, $sh_hpasses);
            usort($student_logs, function($a, $b) {
                return strcmp($b['exit_time'], $a['exit_time']);
            });
            $student_logs = array_slice($student_logs, 0, 5); // top 5
        } else {
            $search_error = "Student with Roll Number <strong>$search_roll</strong> not found.";
        }
    }

    // 2. Fetch Stats - Inside Students
    $stmt = $conn->query("SELECT COUNT(*) FROM students WHERE status = 'Inside'");
    $inside_count = $stmt->fetchColumn();

    // 3. Fetch Stats - Outside Students
    $stmt = $conn->query("SELECT COUNT(*) FROM students WHERE status = 'Outside'");
    $outside_count = $stmt->fetchColumn();

    // 4. Fetch Today's Outing Records count
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM outing 
        WHERE DATE(exit_time) = CURDATE() OR DATE(entry_time) = CURDATE()
    ");
    $stmt->execute();
    $today_outing_count = $stmt->fetchColumn();

    // 5. Fetch Today's Log History (Outing & Homepass Combined)
    $stmt = $conn->query("
        SELECT o.exit_time, o.entry_time, o.reason, s.name, s.roll_number, s.room_number, 'Outing' as pass_type
        FROM outing o
        JOIN students s ON o.student_id = s.id
        WHERE DATE(o.exit_time) = CURDATE() OR DATE(o.entry_time) = CURDATE()
        
        UNION ALL
        
        SELECT h.exit_time, h.entry_time, h.reason, s.name, s.roll_number, s.room_number, 'Home Pass' as pass_type
        FROM homepass h
        JOIN students s ON h.student_id = s.id
        WHERE (DATE(h.exit_time) = CURDATE() OR DATE(h.entry_time) = CURDATE()) AND h.status = 'Approved'
        
        ORDER BY exit_time DESC LIMIT 10
    ");
    $today_logs = $stmt->fetchAll();

    // 6. Fetch Students Currently Outside
    $stmt = $conn->query("
        SELECT s.name, s.roll_number, s.room_number, s.hostel_type, s.phone,
               CASE 
                   WHEN EXISTS (SELECT 1 FROM outing WHERE student_id = s.id AND status = 'Outside') THEN 'Outing'
                   ELSE 'Home Pass'
               END as type
        FROM students s 
        WHERE s.status = 'Outside'
        ORDER BY s.name ASC
    ");
    $outside_students = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Security Dashboard SQL Error: " . $e->getMessage());
    $inside_count = $outside_count = $today_outing_count = 0;
    $today_logs = [];
    $outside_students = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Console - Student Hostel Outpass System</title>
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
                    <a href="dashboard.php" class="nav-link-custom active">
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
                    <a href="approve_homepass.php" class="nav-link-custom">
                        <i class="fa-solid fa-house-circle-check text-warning"></i>
                        <span>Home Pass Approvals</span>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-0">Security Gate Console</h1>
                    <p class="text-secondary mb-0">Monitor gate entries, exits, and student logs</p>
                </div>
                <div class="text-secondary" style="font-size: 0.9rem;">
                    <i class="fa-solid fa-clock me-1"></i> <?php echo date('h:i A'); ?> | <i class="fa-solid fa-calendar-day me-1"></i> <?php echo date('d M Y'); ?>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="row g-4 mb-4">
                <!-- Inside Hostel -->
                <div class="col-md-4">
                    <div class="glass-card stat-card inside">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="stat-number text-success"><?php echo $inside_count; ?></h3>
                                <div class="stat-label">Students Inside</div>
                            </div>
                            <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Outside Hostel -->
                <div class="col-md-4">
                    <div class="glass-card stat-card outside">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="stat-number text-danger"><?php echo $outside_count; ?></h3>
                                <div class="stat-label">Students Outside</div>
                            </div>
                            <div class="stat-icon"><i class="fa-solid fa-door-open"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Today's checkouts -->
                <div class="col-md-4">
                    <div class="glass-card stat-card total">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="stat-number text-primary"><?php echo $today_outing_count; ?></h3>
                                <div class="stat-label">Today's Outings</div>
                            </div>
                            <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search roll number & results -->
            <div class="row g-4 mb-4">
                <!-- Search panel -->
                <div class="<?php echo $student_info ? 'col-lg-6' : 'col-12'; ?>">
                    <div class="glass-card p-4 h-100">
                        <h2 class="h5 fw-bold mb-3"><i class="fa-solid fa-magnifying-glass me-2 text-primary"></i>Verify Student by Roll Number</h2>
                        <form method="GET" action="dashboard.php" class="row g-2">
                            <div class="col-sm-9">
                                <input type="text" name="search_roll" class="form-control" placeholder="Enter student Roll Number (e.g. 20IT105)" value="<?php echo htmlspecialchars($search_roll); ?>" required autocomplete="off">
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="btn btn-primary-custom w-100">Verify</button>
                            </div>
                        </form>

                        <?php if (!empty($search_error)): ?>
                            <div class="alert alert-danger bg-opacity-10 border-danger text-light mt-3 mb-0" role="alert">
                                <i class="fa-solid fa-circle-xmark me-2"></i><?php echo $search_error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$student_info && empty($search_error)): ?>
                            <p class="text-secondary mt-3 mb-0" style="font-size:0.9rem;">
                                Type student's roll number to check their photograph, hostel details, current checkout status, and recent entry/exit history before permitting exit/entry.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search results info display -->
                <?php if ($student_info): ?>
                    <div class="col-lg-6">
                        <div class="glass-card p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h3 class="h5 fw-bold mb-0">Verification Result</h3>
                                <a href="dashboard.php" class="btn-close btn-close-white" title="Clear results"></a>
                            </div>
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <?php if (!empty($student_info['photo']) && file_exists('../uploads/student_photos/' . $student_info['photo'])): ?>
                                        <img src="../uploads/student_photos/<?php echo htmlspecialchars($student_info['photo']); ?>" class="student-avatar-lg" alt="Student Photo">
                                    <?php else: ?>
                                        <i class="fa-solid fa-user-circle fa-5x text-secondary"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="col">
                                    <h4 class="fw-bold mb-1" style="font-size:1.15rem;"><?php echo htmlspecialchars($student_info['name']); ?></h4>
                                    <p class="badge bg-secondary mb-2"><?php echo htmlspecialchars($student_info['roll_number']); ?></p>
                                    <div style="font-size: 0.85rem;" class="text-secondary">
                                        <strong>Hostel:</strong> <span class="text-light"><?php echo htmlspecialchars($student_info['hostel_type']); ?>, Room <?php echo htmlspecialchars($student_info['room_number']); ?></span><br>
                                        <strong>Dept:</strong> <span class="text-light"><?php echo htmlspecialchars($student_info['department']); ?></span><br>
                                        <strong>Phone:</strong> <span class="text-light"><?php echo htmlspecialchars($student_info['phone']); ?></span><br>
                                        <strong>Status:</strong> 
                                        <?php if ($student_info['status'] === 'Inside'): ?>
                                            <span class="badge bg-success bg-opacity-10 border border-success text-success px-2 py-0.5 rounded">Inside Hostel</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 border border-danger text-danger px-2 py-0.5 rounded">Outside Hostel</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mini student logs -->
                            <div class="mt-3 pt-3 border-top border-secondary border-opacity-25">
                                <h5 class="fw-bold text-secondary mb-2" style="font-size:0.85rem;">Recent Logs</h5>
                                <div class="list-group list-group-flush" style="font-size: 0.8rem;">
                                    <?php if (count($student_logs) > 0): ?>
                                        <?php foreach ($student_logs as $log): ?>
                                            <div class="list-group-item bg-transparent text-light px-0 py-1.5 border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="badge bg-secondary me-1" style="font-size: 0.7rem;"><?php echo $log['pass_type']; ?></span>
                                                    <span><?php echo htmlspecialchars($log['reason']); ?></span>
                                                </div>
                                                <div class="text-secondary" style="font-size: 0.75rem;">
                                                    Out: <?php echo date('d M, h:i A', strtotime($log['exit_time'])); ?> 
                                                    <?php if ($log['entry_time']): ?>
                                                        | In: <?php echo date('h:i A', strtotime($log['entry_time'])); ?>
                                                    <?php else: ?>
                                                        | <span class="text-warning">Outside</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-center text-secondary mb-0 py-1">No logs found for this student.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Gate activity logs & current outside list -->
            <div class="row g-4">
                <!-- Gate Log Activity Feed -->
                <div class="col-lg-7">
                    <div class="glass-card p-4 h-100">
                        <h2 class="h5 fw-bold mb-4"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Today's Gate Entries & Exits</h2>
                        <div class="table-custom-container">
                            <table class="table table-custom mb-0" style="font-size: 0.9rem;">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Roll Number</th>
                                        <th>Pass Type</th>
                                        <th>Exit Time</th>
                                        <th>Entry Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($today_logs) > 0): ?>
                                        <?php foreach ($today_logs as $log): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($log['name']); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['roll_number']); ?></span></td>
                                                <td>
                                                    <span class="badge <?php echo $log['pass_type'] === 'Outing' ? 'bg-primary' : 'bg-info'; ?>">
                                                        <?php echo $log['pass_type']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('h:i A', strtotime($log['exit_time'])); ?></td>
                                                <td>
                                                    <?php if ($log['entry_time']): ?>
                                                        <span class="text-success"><i class="fa-solid fa-circle-check me-1"></i><?php echo date('h:i A', strtotime($log['entry_time'])); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Outside</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-secondary py-5">No gate activity recorded today.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Students Currently Outside List -->
                <div class="col-lg-5">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="h5 fw-bold mb-0"><i class="fa-solid fa-door-open me-2 text-danger"></i>Currently Outside</h2>
                            <span class="badge bg-danger rounded-pill"><?php echo count($outside_students); ?></span>
                        </div>
                        
                        <div class="outside-list" style="max-height: 400px; overflow-y: auto;">
                            <?php if (count($outside_students) > 0): ?>
                                <?php foreach ($outside_students as $student): ?>
                                    <div class="d-flex justify-content-between align-items-center py-2.5 border-bottom border-secondary border-opacity-10">
                                        <div>
                                            <div class="fw-bold" style="font-size:0.95rem;"><?php echo htmlspecialchars($student['name']); ?></div>
                                            <small class="text-secondary" style="font-size:0.8rem;"><?php echo htmlspecialchars($student['roll_number']); ?> | Room <?php echo htmlspecialchars($student['room_number']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $student['type'] === 'Outing' ? 'bg-primary' : 'bg-info'; ?>" style="font-size: 0.75rem;">
                                                <?php echo $student['type']; ?>
                                            </span>
                                            <br>
                                            <small class="text-secondary" style="font-size: 0.75rem;"><?php echo htmlspecialchars($student['hostel_type']); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-secondary py-5 mb-0">All students are currently inside the hostel.</p>
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
