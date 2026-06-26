<?php
// view_students.php - Search, List, Edit, and Delete Students

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

// 1. Process Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $roll_to_delete = strtoupper(trim($_GET['roll_number'] ?? ''));
    
    if (!empty($roll_to_delete)) {
        try {
            // Fetch photo and QR paths before deleting from database
            $fetch_stmt = $conn->prepare("SELECT name, photo, qr_code FROM students WHERE roll_number = :roll");
            $fetch_stmt->execute([':roll' => $roll_to_delete]);
            $student_data = $fetch_stmt->fetch();
            
            if ($student_data) {
                // Delete photo file
                if (!empty($student_data['photo'])) {
                    $photo_path = '../uploads/student_photos/' . $student_data['photo'];
                    if (file_exists($photo_path)) {
                        unlink($photo_path);
                    }
                }
                
                // Delete QR code file
                if (!empty($student_data['qr_code'])) {
                    $qr_path = '../' . $student_data['qr_code'];
                    if (file_exists($qr_path)) {
                        unlink($qr_path);
                    }
                }
                
                // Delete from DB
                $delete_stmt = $conn->prepare("DELETE FROM students WHERE roll_number = :roll");
                $delete_stmt->execute([':roll' => $roll_to_delete]);
                
                // Log action
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, details) VALUES ('Admin', :admin_id, 'Delete Student', :details)");
                $log_stmt->execute([
                    ':admin_id' => $_SESSION['admin_id'],
                    ':details' => "Deleted student: " . $student_data['name'] . " ($roll_to_delete)"
                ]);
                
                header("Location: view_students.php?success=deleted");
                exit;
            } else {
                header("Location: view_students.php?error=not_found");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Delete Student SQL Error: " . $e->getMessage());
            header("Location: view_students.php?error=db");
            exit;
        }
    }
}

// 2. Fetch Students List with Search Filter
$search_query = trim($_GET['search'] ?? '');
$hostel_filter = trim($_GET['hostel_type'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM students WHERE 1=1";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (roll_number LIKE :search OR name LIKE :search OR department LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}

if (!empty($hostel_filter)) {
    $sql .= " AND hostel_type = :hostel_type";
    $params[':hostel_type'] = $hostel_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND status = :status";
    $params[':status'] = $status_filter;
}

$sql .= " ORDER BY roll_number ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Students List SQL Error: " . $e->getMessage());
    $students = [];
}

// 3. Set Alert Messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($success === 'added') {
    $message = "Student registered and QR code generated successfully!";
    $alert_class = 'alert-success';
} elseif ($success === 'edited') {
    $message = "Student details updated successfully!";
    $alert_class = 'alert-success';
} elseif ($success === 'deleted') {
    $message = "Student record deleted successfully.";
    $alert_class = 'alert-warning';
}

if ($error === 'not_found') {
    $message = "Student record not found.";
    $alert_class = 'alert-danger';
} elseif ($error === 'db') {
    $message = "A database error occurred. Operation failed.";
    $alert_class = 'alert-danger';
} elseif ($error === 'qr_failed') {
    $message = "Student registered, but QR Code generation failed. Please regenerate.";
    $alert_class = 'alert-danger';
} elseif ($error === 'qr_lib_missing') {
    $message = "Local QR Code library (phpqrcode.php) is missing! Please run <a href='../config/setup.php' class='alert-link'>config/setup.php</a> in your browser to download it locally.";
    $alert_class = 'alert-danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students List - Student Hostel Outpass System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="../css/all.min.css" rel="stylesheet">
    <!-- Global CSS and Dashboard CSS -->
    <link href="../css/style.css" rel="stylesheet">
    <link href="../css/dashboard.css" rel="stylesheet">
    <style>
        .search-container {
            background: rgba(17, 25, 40, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
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
                    <a href="view_students.php" class="nav-link-custom active">
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
                    <h1 class="h3 fw-bold mb-0">Students Management</h1>
                    <p class="text-secondary mb-0">Add, edit, delete, and search students or print QR passes</p>
                </div>
                <a href="add_student.html" class="btn btn-primary-custom">
                    <i class="fa-solid fa-plus me-2"></i>Add Student
                </a>
            </div>

            <!-- Status Alerts -->
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $alert_class; ?> bg-opacity-10 alert-dismissible fade show mb-4" role="alert">
                    <i class="fa-solid fa-circle-info me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Search Filters -->
            <div class="search-container">
                <form method="GET" action="view_students.php" class="row g-3">
                    <div class="col-md-5">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, roll no, department..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="hostel_type" class="form-label">Hostel Block</label>
                        <select class="form-select" id="hostel_type" name="hostel_type">
                            <option value="">All Blocks</option>
                            <option value="Boys Hostel A" <?php echo $hostel_filter === 'Boys Hostel A' ? 'selected' : ''; ?>>Boys Hostel A</option>
                            <option value="Boys Hostel B" <?php echo $hostel_filter === 'Boys Hostel B' ? 'selected' : ''; ?>>Boys Hostel B</option>
                            <option value="Girls Hostel A" <?php echo $hostel_filter === 'Girls Hostel A' ? 'selected' : ''; ?>>Girls Hostel A</option>
                            <option value="Girls Hostel B" <?php echo $hostel_filter === 'Girls Hostel B' ? 'selected' : ''; ?>>Girls Hostel B</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Inside" <?php echo $status_filter === 'Inside' ? 'selected' : ''; ?>>Inside</option>
                            <option value="Outside" <?php echo $status_filter === 'Outside' ? 'selected' : ''; ?>>Outside</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary-custom w-100 py-2">Filter</button>
                    </div>
                </form>
            </div>

            <!-- Students List Table -->
            <div class="glass-card p-4">
                <div class="table-custom-container">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Roll Number</th>
                                <th>Name</th>
                                <th>Dept & Year</th>
                                <th>Room & Hostel</th>
                                <th>Status</th>
                                <th>QR Pass</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $row): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($row['photo']) && file_exists('../uploads/student_photos/' . $row['photo'])): ?>
                                                <img src="../uploads/student_photos/<?php echo htmlspecialchars($row['photo']); ?>" class="student-avatar" alt="Photo">
                                            <?php else: ?>
                                                <i class="fa-solid fa-user-circle fa-3x text-secondary"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['roll_number']); ?></span></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['department']); ?><br>
                                            <small class="text-secondary"><?php echo htmlspecialchars($row['year']); ?> Year</small>
                                        </td>
                                        <td>
                                            Room: <?php echo htmlspecialchars($row['room_number']); ?><br>
                                            <small class="text-secondary"><?php echo htmlspecialchars($row['hostel_type']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] === 'Inside'): ?>
                                                <span class="badge-inside">Inside</span>
                                            <?php else: ?>
                                                <span class="badge-outside">Outside</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['qr_code']) && file_exists('../' . $row['qr_code'])): ?>
                                                <img src="../<?php echo htmlspecialchars($row['qr_code']); ?>" class="img-thumbnail bg-white" style="width: 50px; height: 50px; cursor: pointer;" 
                                                     onclick="showQRModal('<?php echo htmlspecialchars($row['name']); ?>', '<?php echo htmlspecialchars($row['roll_number']); ?>', '../<?php echo htmlspecialchars($row['qr_code']); ?>')" 
                                                     title="Click to view QR">
                                            <?php else: ?>
                                                <a href="generate_qr.php?roll_number=<?php echo urlencode($row['roll_number']); ?>" class="btn btn-sm btn-outline-warning">
                                                    <i class="fa-solid fa-qrcode"></i> Generate
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary me-1" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </button>
                                            <a href="view_students.php?action=delete&roll_number=<?php echo urlencode($row['roll_number']); ?>" 
                                               class="btn btn-sm btn-outline-danger btn-confirm-delete" 
                                               data-confirm-message="Are you sure you want to delete student: <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['roll_number']); ?>)? This will delete all their outpass records!">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-secondary py-5">No students found matching the criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Reprint Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
            <div class="modal-content glass-card border-light text-light" style="background: rgba(17, 25, 40, 0.95); backdrop-filter: blur(20px);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="qrModalLabel">Student Outpass QR Code</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <h4 class="fw-bold mb-1" id="qrStudentName">Student Name</h4>
                    <p class="badge bg-secondary mb-4" id="qrStudentRoll">ROLL_NO</p>
                    <div class="d-inline-block bg-white p-3 rounded-3 mb-4 shadow">
                        <img src="" id="qrModalImage" style="width: 200px; height: 200px;" alt="QR Code">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary-custom flex-grow-1" onclick="printQRCode()">
                            <i class="fa-solid fa-print me-2"></i>Print QR Code
                        </button>
                        <a href="" id="reprintQRLink" class="btn btn-secondary-custom">
                            <i class="fa-solid fa-rotate me-2"></i>Regenerate
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content glass-card border-light text-light" style="background: rgba(17, 25, 40, 0.95); backdrop-filter: blur(20px);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Student Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="edit_student.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="modal-body p-4">
                        <input type="hidden" name="old_roll_number" id="edit_old_roll_number">
                        
                        <div class="row g-3">
                            <!-- Roll Number (Disabled or editable with caution) -->
                            <div class="col-md-6">
                                <label for="edit_roll_number" class="form-label">Roll Number</label>
                                <input type="text" class="form-control bg-dark bg-opacity-25" id="edit_roll_number" name="roll_number" readonly required>
                            </div>
                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label for="edit_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            <!-- Department -->
                            <div class="col-md-6">
                                <label for="edit_department" class="form-label">Department *</label>
                                <input type="text" class="form-control" id="edit_department" name="department" required>
                            </div>
                            <!-- Year -->
                            <div class="col-md-6">
                                <label for="edit_year" class="form-label">Year of Study *</label>
                                <select class="form-select" id="edit_year" name="year" required>
                                    <option value="1">I Year</option>
                                    <option value="2">II Year</option>
                                    <option value="3">III Year</option>
                                    <option value="4">IV Year</option>
                                </select>
                            </div>
                            <!-- Room Number -->
                            <div class="col-md-6">
                                <label for="edit_room_number" class="form-label">Room Number *</label>
                                <input type="text" class="form-control" id="edit_room_number" name="room_number" required>
                            </div>
                            <!-- Hostel Block -->
                            <div class="col-md-6">
                                <label for="edit_hostel_type" class="form-label">Hostel Block *</label>
                                <select class="form-select" id="edit_hostel_type" name="hostel_type" required>
                                    <option value="Boys Hostel A">Boys Hostel A</option>
                                    <option value="Boys Hostel B">Boys Hostel B</option>
                                    <option value="Girls Hostel A">Girls Hostel A</option>
                                    <option value="Girls Hostel B">Girls Hostel B</option>
                                </select>
                            </div>
                            <!-- Phone -->
                            <div class="col-md-6">
                                <label for="edit_phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone" pattern="[0-9]{10}" required>
                            </div>
                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <!-- Current Photo Display & New File Upload -->
                            <div class="col-12 d-flex align-items-center gap-3">
                                <img src="" id="edit_photo_preview" class="student-avatar" alt="Current Photo">
                                <div class="flex-grow-1">
                                    <label for="edit_photo" class="form-label">Change Photo (Leave blank to keep current photo)</label>
                                    <input type="file" class="form-control" id="edit_photo" name="photo" accept=".jpg,.jpeg,.png">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom px-4">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Save Changes
                        </button>
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
        // Modal functions
        const qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));

        function showQRModal(name, rollNumber, qrUrl) {
            document.getElementById('qrStudentName').innerText = name;
            document.getElementById('qrStudentRoll').innerText = rollNumber;
            document.getElementById('qrModalImage').src = qrUrl;
            document.getElementById('reprintQRLink').href = 'generate_qr.php?roll_number=' + encodeURIComponent(rollNumber);
            qrModal.show();
        }

        function printQRCode() {
            const qrImgUrl = document.getElementById('qrModalImage').src;
            const name = document.getElementById('qrStudentName').innerText;
            const roll = document.getElementById('qrStudentRoll').innerText;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Print QR Outpass - ${roll}</title>
                    <style>
                        body { font-family: sans-serif; text-align: center; padding: 40px; color: #333; }
                        .card { border: 2px solid #333; border-radius: 15px; padding: 30px; display: inline-block; }
                        h2 { margin: 0 0 5px 0; font-size: 24px; }
                        p { margin: 0 0 20px 0; font-size: 16px; color: #666; font-weight: bold; }
                        img { width: 220px; height: 220px; }
                        .footer { margin-top: 20px; font-size: 12px; color: #999; }
                    </style>
                </head>
                <body>
                    <div class="card">
                        <h2>${name}</h2>
                        <p>ROLL NO: ${roll}</p>
                        <img src="${qrImgUrl}">
                        <div class="footer">Student Hostel Outpass ID Pass</div>
                    </div>
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(function() { window.close(); }, 500);
                        }
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        function openEditModal(student) {
            document.getElementById('edit_old_roll_number').value = student.roll_number;
            document.getElementById('edit_roll_number').value = student.roll_number;
            document.getElementById('edit_name').value = student.name;
            document.getElementById('edit_department').value = student.department;
            document.getElementById('edit_year').value = student.year;
            document.getElementById('edit_room_number').value = student.room_number;
            document.getElementById('edit_hostel_type').value = student.hostel_type;
            document.getElementById('edit_phone').value = student.phone;
            document.getElementById('edit_email').value = student.email;
            
            const previewImg = document.getElementById('edit_photo_preview');
            if (student.photo) {
                previewImg.src = '../uploads/student_photos/' + student.photo;
                previewImg.classList.remove('d-none');
            } else {
                previewImg.classList.add('d-none');
            }
            
            editModal.show();
        }
    </script>
</body>
</html>
