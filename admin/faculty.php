<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('admin');

$currentUser = currentUser();
$pageTitle = 'Faculty & Staff Management';

$searchQuery = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? 'all'; // all, faculty, admin

// Handle actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['faculty', 'admin'], true) ? $_POST['role'] : 'faculty';

        if (empty($name) || empty($email) || empty($password)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Name, email, and password are required.'];
        } elseif (strlen($password) < 8) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Password must be at least 8 characters long.'];
        } else {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$name, $email, $passwordHash, $role]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff member added successfully.'];
                header('Location: faculty.php');
                exit;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'An account with this email address already exists.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error adding staff: ' . $e->getMessage()];
                }
            }
        }
    } elseif ($action === 'edit_staff') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['faculty', 'admin'], true) ? $_POST['role'] : 'faculty';

        if (empty($name) || empty($email)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Name and email are required.'];
        } else {
            try {
                // Prevent self-demotion from admin
                if ($userId == $currentUser['id'] && $role !== 'admin') {
                    $role = 'admin';
                }

                if (!empty($password)) {
                    if (strlen($password) < 8) {
                        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Password must be at least 8 characters long.'];
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users SET name = ?, email = ?, password_hash = ?, role = ?
                            WHERE user_id = ? AND role IN ('faculty', 'admin')
                        ");
                        $stmt->execute([$name, $email, $passwordHash, $role, $userId]);
                        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff profile and password updated successfully.'];
                        header('Location: faculty.php');
                        exit;
                    }
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET name = ?, email = ?, role = ?
                        WHERE user_id = ? AND role IN ('faculty', 'admin')
                    ");
                    $stmt->execute([$name, $email, $role, $userId]);
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff profile updated successfully.'];
                    header('Location: faculty.php');
                    exit;
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Email address already in use by another user.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error updating staff member: ' . $e->getMessage()];
                }
            }
        }
    } elseif ($action === 'delete_staff') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId == $currentUser['id']) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'You cannot delete your own administrator account.'];
        } else {
            try {
                $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role IN ('faculty', 'admin')")->execute([$userId]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff member removed successfully.'];
                header('Location: faculty.php');
                exit;
            } catch (Exception $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error deleting staff member: ' . $e->getMessage()];
            }
        }
    }
}

// Fetch staff metrics
try {
    $statStmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN role = 'faculty' THEN 1 ELSE 0 END) as total_faculty,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
            (SELECT COUNT(*) FROM subjects) as total_subjects,
            (SELECT COUNT(*) FROM quizzes) as total_quizzes
        FROM users
    ");
    $stats = $statStmt->fetch() ?: ['total_faculty' => 0, 'total_admins' => 0, 'total_subjects' => 0, 'total_quizzes' => 0];

    // Filtered staff list
    $query = "
        SELECT u.*,
               (SELECT COUNT(*) FROM subjects s WHERE s.faculty_id = u.user_id) as subjects_count,
               (SELECT COUNT(*) FROM quizzes q JOIN subjects s2 ON q.subject_id = s2.subject_id WHERE s2.faculty_id = u.user_id) as quizzes_count,
               (SELECT GROUP_CONCAT(s.name SEPARATOR ', ') FROM subjects s WHERE s.faculty_id = u.user_id) as assigned_subjects
        FROM users u
        WHERE u.role IN ('faculty', 'admin')
    ";
    $params = [];

    if ($roleFilter === 'faculty') {
        $query .= " AND u.role = 'faculty'";
    } elseif ($roleFilter === 'admin') {
        $query .= " AND u.role = 'admin'";
    }

    if (!empty($searchQuery)) {
        $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $term = '%' . $searchQuery . '%';
        $params = [$term, $term];
    }

    $query .= " ORDER BY u.role ASC, u.name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $staffList = $stmt->fetchAll();

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error loading faculty: ' . $e->getMessage()];
    $stats = ['total_faculty' => 0, 'total_admins' => 0, 'total_subjects' => 0, 'total_quizzes' => 0];
    $staffList = [];
}

require_once __DIR__.'/../includes/header.php';
?>

<!-- Page Header (Left-aligned) -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary-pale text-primary text-uppercase px-2 py-1 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Administration</span>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Academic Staff Roster</span>
        </div>
        <h2 class="mb-1 fw-800 text-dark d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
            <i class="bi bi-person-badge-fill text-primary"></i> Faculty & Staff Management
        </h2>
        <p class="text-muted small mb-0">Oversee instructors, curriculum assignees, and institutional administrators.</p>
    </div>
    <div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="bi bi-person-plus-fill"></i> Add Faculty / Staff
        </button>
    </div>
</div>

<!-- Modern Metric Cards Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="metric-card-modern theme-indigo">
            <div class="metric-header">
                <span class="metric-title">Faculty Educators</span>
                <div class="metric-icon-box">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo (int)($stats['total_faculty'] ?? 0); ?></div>
            <div class="metric-trend">
                <i class="bi bi-check-circle-fill"></i> Instructors
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="metric-card-modern theme-emerald">
            <div class="metric-header">
                <span class="metric-title">Curriculum Subjects</span>
                <div class="metric-icon-box">
                    <i class="bi bi-book-half"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo (int)($stats['total_subjects'] ?? 0); ?></div>
            <div class="metric-trend">
                <i class="bi bi-journal-check"></i> Academic Courses
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="metric-card-modern theme-amber">
            <div class="metric-header">
                <span class="metric-title">Quizzes Created</span>
                <div class="metric-icon-box">
                    <i class="bi bi-pencil-square"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo (int)($stats['total_quizzes'] ?? 0); ?></div>
            <div class="metric-trend">
                <i class="bi bi-layers-fill"></i> Active Assessments
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="metric-card-modern theme-danger">
            <div class="metric-header">
                <span class="metric-title">System Admins</span>
                <div class="metric-icon-box">
                    <i class="bi bi-shield-fill"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo (int)($stats['total_admins'] ?? 0); ?></div>
            <div class="metric-trend">
                <i class="bi bi-key-fill"></i> Full Root Access
            </div>
        </div>
    </div>
</div>

<!-- Search Bar (Filter pills removed) -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" 
                           placeholder="Search by name or email..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="btn btn-outline-primary px-3">Search</button>
                    <?php if (!empty($searchQuery)): ?>
                        <a href="faculty.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Showing <strong><?php echo count($staffList); ?></strong> staff members
            </div>
        </form>
    </div>
</div>

<!-- Staff Roster Table -->
<div class="card border-0 shadow-sm overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4" style="width: 28%;">Staff Member</th>
                    <th>Email Address</th>
                    <th>Role Designation</th>
                    <th>Assigned Subject(s)</th>
                    <th>Quizzes</th>
                    <th class="text-end pe-4" style="width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($staffList) > 0): ?>
                    <?php foreach ($staffList as $staff): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold shadow-sm" 
                                         style="width: 36px; height: 36px; font-size: 0.9rem; background: <?= $staff['role'] === 'admin' ? 'linear-gradient(135deg, #ef4444, #dc2626)' : 'linear-gradient(135deg, #4f46e5, #4338ca)' ?>; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($staff['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($staff['name']); ?></div>
                                        <small class="text-muted"><?= $staff['role'] === 'admin' ? 'System Administrator' : 'Academic Faculty' ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small"><?php echo htmlspecialchars($staff['email']); ?></span>
                            </td>
                            <td>
                                <?php if ($staff['role'] === 'admin'): ?>
                                    <span class="badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; font-size: 0.75rem;">
                                        <i class="bi bi-shield-lock-fill me-1"></i>Administrator
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; font-size: 0.75rem;">
                                        <i class="bi bi-mortarboard me-1"></i>Faculty Member
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($staff['assigned_subjects'])): ?>
                                    <span class="badge bg-light text-dark border px-2 py-1 text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($staff['assigned_subjects']); ?>">
                                        <i class="bi bi-book me-1 text-primary"></i><?php echo htmlspecialchars($staff['assigned_subjects']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small italic">None assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border px-2 py-1">
                                    <?php echo (int)$staff['quizzes_count']; ?> authored
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="action-btn-group justify-content-end">
                                    <button class="btn-action btn-action-edit" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editStaffModal<?php echo $staff['user_id']; ?>"
                                            title="Edit Staff Member">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <?php if ($staff['user_id'] != $currentUser['id']): ?>
                                        <button class="btn-action btn-action-delete" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteStaffModal<?php echo $staff['user_id']; ?>"
                                                title="Delete Staff Member">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Staff Modal -->
                        <div class="modal fade" id="editStaffModal<?php echo $staff['user_id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="edit_staff">
                                        <input type="hidden" name="user_id" value="<?php echo $staff['user_id']; ?>">
                                        <div class="modal-header border-bottom">
                                            <h5 class="modal-title fw-bold text-dark">
                                                <i class="bi bi-pencil-square text-primary me-2"></i>Edit Staff Member
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($staff['name']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-dark">Role Designation</label>
                                                <select name="role" class="form-select">
                                                    <option value="faculty" <?= $staff['role'] === 'faculty' ? 'selected' : '' ?>>Faculty Member</option>
                                                    <option value="admin" <?= $staff['role'] === 'admin' ? 'selected' : '' ?>>Platform Administrator</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold text-dark">Reset Password</label>
                                                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                                <div class="form-text">Only enter a new password if resetting credentials.</div>
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

                        <!-- Delete Staff Modal -->
                        <?php if ($staff['user_id'] != $currentUser['id']): ?>
                        <div class="modal fade" id="deleteStaffModal<?php echo $staff['user_id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="delete_staff">
                                        <input type="hidden" name="user_id" value="<?php echo $staff['user_id']; ?>">
                                        <div class="modal-header border-bottom">
                                            <h5 class="modal-title fw-bold text-danger">
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Staff Removal
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 text-center">
                                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger mb-3" style="width: 56px; height: 56px; font-size: 1.5rem;">
                                                <i class="bi bi-trash"></i>
                                            </div>
                                            <h5 class="fw-bold mb-2">Remove <?php echo htmlspecialchars($staff['name']); ?>?</h5>
                                            <p class="text-muted small mb-0">Are you sure you want to remove this staff account? Subjects assigned to them will need reassignment.</p>
                                        </div>
                                        <div class="modal-footer border-top justify-content-center">
                                            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger px-4">Confirm Delete</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <div class="mb-2"><i class="bi bi-person-badge text-muted" style="font-size: 2.5rem;"></i></div>
                            <h6 class="fw-bold text-dark">No Staff Members Found</h6>
                            <p class="small text-muted mb-3"><?php echo !empty($searchQuery) ? 'No staff match your search criteria.' : 'No faculty or staff members have been added yet.'; ?></p>
                            <button class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                                <i class="bi bi-person-plus-fill me-1"></i> Add First Staff Member
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_staff">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-person-plus-fill text-primary me-2"></i>Add Faculty / Staff Member
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Dr. Jane Smith" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="faculty@university.edu" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Role Designation <span class="text-danger">*</span></label>
                        <select name="role" class="form-select">
                            <option value="faculty" selected>Faculty Member (Educator)</option>
                            <option value="admin">Administrator (Platform Control)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Initial Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
