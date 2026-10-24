<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('admin');

$currentUser = currentUser();
$pageTitle = 'Student Management';

$searchQuery = trim($_GET['search'] ?? '');

// Handle actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_student') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rollNo = trim($_POST['roll_no'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Student name, email, and password are required.'];
        } elseif (strlen($password) < 8) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Password must be at least 8 characters long.'];
        } else {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role, roll_no)
                    VALUES (?, ?, ?, 'student', ?)
                ");
                $stmt->execute([$name, $email, $passwordHash, !empty($rollNo) ? $rollNo : null]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Student account enrolled successfully.'];
                header('Location: students.php');
                exit;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'A student account with this email address already exists.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error creating student account: ' . $e->getMessage()];
                }
            }
        }
    } elseif ($action === 'edit_student') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rollNo = trim($_POST['roll_no'] ?? '');

        if (empty($name) || empty($email)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Name and email are required.'];
        } else {
            try {
                if (!empty($password)) {
                    if (strlen($password) < 8) {
                        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Password must be at least 8 characters long.'];
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users SET name = ?, email = ?, password_hash = ?, roll_no = ?
                            WHERE user_id = ? AND role = 'student'
                        ");
                        $stmt->execute([$name, $email, $passwordHash, !empty($rollNo) ? $rollNo : null, $userId]);
                        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Student details and password updated successfully.'];
                        header('Location: students.php');
                        exit;
                    }
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET name = ?, email = ?, roll_no = ?
                        WHERE user_id = ? AND role = 'student'
                    ");
                    $stmt->execute([$name, $email, !empty($rollNo) ? $rollNo : null, $userId]);
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Student profile updated successfully.'];
                    header('Location: students.php');
                    exit;
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Email address already in use by another student.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error updating student: ' . $e->getMessage()];
                }
            }
        }
    } elseif ($action === 'delete_student') {
        $userId = (int)($_POST['user_id'] ?? 0);

        try {
            $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'")->execute([$userId]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Student record removed from system.'];
            header('Location: students.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error deleting student: ' . $e->getMessage()];
        }
    }
}

// Fetch student metrics
try {
    $statStmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT u.user_id) as total_students,
            COUNT(a.attempt_id) as total_attempts,
            COALESCE(AVG(a.score), 0) as avg_score
        FROM users u
        LEFT JOIN attempts a ON u.user_id = a.student_id
        WHERE u.role = 'student'
    ");
    $stats = $statStmt->fetch() ?: ['total_students' => 0, 'total_attempts' => 0, 'avg_score' => 0];

    // Filtered student list
    $query = "
        SELECT u.*, 
               COUNT(a.attempt_id) as attempts_count,
               COALESCE(AVG(a.score), 0) as student_avg
        FROM users u
        LEFT JOIN attempts a ON u.user_id = a.student_id
        WHERE u.role = 'student'
    ";
    $params = [];

    if (!empty($searchQuery)) {
        $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.roll_no LIKE ?)";
        $term = '%' . $searchQuery . '%';
        $params = [$term, $term, $term];
    }

    $query .= " GROUP BY u.user_id ORDER BY u.name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error loading students: ' . $e->getMessage()];
    $stats = ['total_students' => 0, 'total_attempts' => 0, 'avg_score' => 0];
    $students = [];
}

require_once __DIR__.'/../includes/header.php';
?>

<!-- Page Header (Left-aligned) -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary-pale text-primary text-uppercase px-2 py-1 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Administration</span>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Student Directory</span>
        </div>
        <h2 class="mb-1 fw-800 text-dark d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
            <i class="bi bi-mortarboard-fill text-primary"></i> Students Management
        </h2>
        <p class="text-muted small mb-0">Manage enrolled student rosters, registration IDs, and track assessment history.</p>
    </div>
    <div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="bi bi-person-plus-fill"></i> Add New Student
        </button>
    </div>
</div>

<!-- Modern Metric Cards Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="metric-card-modern theme-emerald">
            <div class="metric-header">
                <span class="metric-title">Enrolled Students</span>
                <div class="metric-icon-box">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo (int)($stats['total_students'] ?? 0); ?></div>
            <div class="metric-trend">
                <i class="bi bi-check-circle-fill"></i> Registered Candidates
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="metric-card-modern theme-indigo">
            <div class="metric-header">
                <span class="metric-title">Exam Submissions</span>
                <div class="metric-icon-box">
                    <i class="bi bi-clock-history"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo (int)($stats['total_attempts'] ?? 0); ?></div>
            <div class="metric-trend">
                <i class="bi bi-check2-all"></i> Total Quizzes Taken
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="metric-card-modern theme-cyan">
            <div class="metric-header">
                <span class="metric-title">Average Score</span>
                <div class="metric-icon-box">
                    <i class="bi bi-graph-up"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo round((float)($stats['avg_score'] ?? 0), 1); ?> <span style="font-size: 1rem; color: #64748b; font-weight: 500;">pts</span></div>
            <div class="metric-trend">
                <i class="bi bi-award-fill"></i> Overall Performance
            </div>
        </div>
    </div>
</div>

<!-- Search & Controls Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" 
                           placeholder="Search by student name, email, or roll no..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="btn btn-outline-primary px-3">Search</button>
                    <?php if (!empty($searchQuery)): ?>
                        <a href="students.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Showing <strong><?php echo count($students); ?></strong> students
            </div>
        </form>
    </div>
</div>

<!-- Student Roster Table -->
<div class="card border-0 shadow-sm overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4" style="width: 32%;">Student Name</th>
                    <th>Email Address</th>
                    <th>Student ID / Roll No</th>
                    <th>Exams Taken</th>
                    <th>Status</th>
                    <th class="text-end pe-4" style="width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($students) > 0): ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold shadow-sm" 
                                         style="width: 36px; height: 36px; font-size: 0.9rem; background: linear-gradient(135deg, #10b981, #059669); flex-shrink: 0;">
                                        <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['name']); ?></div>
                                        <small class="text-muted">Student Account</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small"><?php echo htmlspecialchars($student['email']); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($student['roll_no'])): ?>
                                    <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                        #<?php echo htmlspecialchars($student['roll_no']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary-pale text-primary fw-semibold px-2 py-1">
                                    <i class="bi bi-clock-history me-1"></i><?php echo (int)$student['attempts_count']; ?> tests
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success-pale text-success border border-success-subtle px-2 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i>Active
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="action-btn-group justify-content-end">
                                    <button class="btn-action btn-action-edit" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editStudentModal<?php echo $student['user_id']; ?>"
                                            title="Edit Student">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn-action btn-action-delete" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteStudentModal<?php echo $student['user_id']; ?>"
                                            title="Delete Student">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Student Modal -->
                        <div class="modal fade" id="editStudentModal<?php echo $student['user_id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="edit_student">
                                        <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                        <div class="modal-header border-bottom">
                                            <h5 class="modal-title fw-bold text-dark">
                                                <i class="bi bi-pencil-square text-primary me-2"></i>Edit Student Profile
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-dark">Student ID / Roll No</label>
                                                <input type="text" name="roll_no" class="form-control" value="<?php echo htmlspecialchars($student['roll_no'] ?? ''); ?>" placeholder="e.g. CS-2026-042">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-dark">Reset Password</label>
                                                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                                <div class="form-text">Only fill this if you wish to reset their password.</div>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Student Modal -->
                        <div class="modal fade" id="deleteStudentModal<?php echo $student['user_id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="delete_student">
                                        <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                        <div class="modal-header border-bottom">
                                            <h5 class="modal-title fw-bold text-danger">
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Student Removal
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 text-center">
                                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger mb-3" style="width: 56px; height: 56px; font-size: 1.5rem;">
                                                <i class="bi bi-trash"></i>
                                            </div>
                                            <h5 class="fw-bold mb-2">Remove <?php echo htmlspecialchars($student['name']); ?>?</h5>
                                            <p class="text-muted small mb-0">Are you sure you want to permanently delete this student account? All test attempts and records will be deleted.</p>
                                        </div>
                                        <div class="modal-footer border-top justify-content-center">
                                            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger px-4">Confirm Delete</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <div class="mb-2"><i class="bi bi-mortarboard text-muted" style="font-size: 2.5rem;"></i></div>
                            <h6 class="fw-bold text-dark">No Students Found</h6>
                            <p class="small text-muted mb-3"><?php echo !empty($searchQuery) ? 'No students match your search filter.' : 'No students have been enrolled in the platform yet.'; ?></p>
                            <button class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                <i class="bi bi-person-plus-fill me-1"></i> Enroll First Student
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_student">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-person-plus-fill text-primary me-2"></i>Enroll New Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="student@university.edu" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Student ID / Roll No</label>
                        <input type="text" name="roll_no" class="form-control" placeholder="e.g. CS-2026-001">
                        <div class="form-text">Unique identification code used for exam grading.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Initial Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
