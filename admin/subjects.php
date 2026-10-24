<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('admin');

$pageTitle = 'Manage Subjects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_subject') {
        $name = trim($_POST['name'] ?? '');
        $facultyId = (int)($_POST['faculty_id'] ?? 0);

        if (empty($name) || $facultyId <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Subject name and assigned faculty are required.'];
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO subjects (name, faculty_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$name, $facultyId]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Subject created successfully.'];
                header('Location: subjects.php');
                exit;
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error creating subject: ' . $e->getMessage()];
            }
        }
    } elseif ($action === 'edit_subject') {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $facultyId = (int)($_POST['faculty_id'] ?? 0);

        if (empty($name) || $facultyId <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Subject name and faculty are required.'];
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE subjects SET name = ?, faculty_id = ?
                    WHERE subject_id = ?
                ");
                $stmt->execute([$name, $facultyId, $subjectId]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Subject updated successfully.'];
                header('Location: subjects.php');
                exit;
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error updating subject: ' . $e->getMessage()];
            }
        }
    } elseif ($action === 'delete_subject') {
        $subjectId = (int)($_POST['subject_id'] ?? 0);

        try {
            $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?")->execute([$subjectId]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Subject and associated records deleted.'];
            header('Location: subjects.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error deleting subject: ' . $e->getMessage()];
        }
    }
}

try {
    $stmt = $pdo->query("
        SELECT s.subject_id, s.name, s.faculty_id, u.name as faculty_name, u.email as faculty_email,
               (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.subject_id) as question_count,
               (SELECT COUNT(*) FROM quizzes qz WHERE qz.subject_id = s.subject_id) as quiz_count
        FROM subjects s
        INNER JOIN users u ON s.faculty_id = u.user_id
        ORDER BY s.name ASC
    ");
    $subjects = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT user_id, name, email FROM users WHERE role = 'faculty'
        ORDER BY name ASC
    ");
    $stmt->execute();
    $facultyUsers = $stmt->fetchAll();

    $totalQuestions = array_sum(array_column($subjects, 'question_count'));
    $totalQuizzes = array_sum(array_column($subjects, 'quiz_count'));

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error loading subjects: ' . $e->getMessage()];
    $subjects = [];
    $facultyUsers = [];
    $totalQuestions = 0;
    $totalQuizzes = 0;
}

require_once __DIR__.'/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary-pale text-primary text-uppercase px-2 py-1 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Curriculum</span>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Course Catalog</span>
        </div>
        <h2 class="mb-1 fw-800 text-dark d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
            <i class="bi bi-book-fill text-primary"></i> Subject Management
        </h2>
        <p class="text-muted small mb-0">Define academic curriculum, assign educators, and oversee question banks.</p>
    </div>
    <div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="bi bi-plus-circle-fill me-1"></i> Add New Subject
        </button>
    </div>
</div>

<!-- Stats matching Reference Image Style -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card-modern theme-indigo">
            <div class="metric-header">
                <span class="metric-title">Active Subjects</span>
                <div class="metric-icon-box">
                    <i class="bi bi-journal-bookmark-fill"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo count($subjects); ?></div>
            <div class="metric-trend">
                <i class="bi bi-book-half"></i> Curriculum Courses
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card-modern theme-emerald">
            <div class="metric-header">
                <span class="metric-title">Question Bank Total</span>
                <div class="metric-icon-box">
                    <i class="bi bi-patch-question-fill"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo (int)$totalQuestions; ?></div>
            <div class="metric-trend">
                <i class="bi bi-check-all"></i> Available Pool
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card-modern theme-amber">
            <div class="metric-header">
                <span class="metric-title">Quizzes Linked</span>
                <div class="metric-icon-box">
                    <i class="bi bi-file-earmark-check-fill"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo (int)$totalQuizzes; ?></div>
            <div class="metric-trend">
                <i class="bi bi-clock-history"></i> Published Tests
            </div>
        </div>
    </div>
</div>

<!-- Subject List Card -->
<div class="card border-0 shadow-sm overflow-hidden mb-4">
    <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-collection text-primary"></i>
            <span class="fw-bold text-dark">Subject Directory</span>
            <span class="badge bg-light text-dark border"><?php echo count($subjects); ?> Total</span>
        </div>
        <span class="text-muted small">Updated in real-time</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Subject Name</th>
                    <th>Assigned Faculty Member</th>
                    <th>Question Bank</th>
                    <th>Created Quizzes</th>
                    <th class="text-end pe-4" style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($subjects) > 0): ?>
                    <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-pale text-primary fw-bold" 
                                         style="width: 42px; height: 42px; font-size: 1.15rem; flex-shrink: 0;">
                                        <i class="bi bi-book"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($subject['name']); ?></div>
                                        <small class="text-muted">Subject ID: #SUB-<?php echo str_pad($subject['subject_id'], 3, '0', STR_PAD_LEFT); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold shadow-sm" 
                                         style="width: 32px; height: 32px; font-size: 0.8rem; background: linear-gradient(135deg, #6366f1, #4f46e5); flex-shrink: 0;">
                                        <?php echo strtoupper(substr($subject['faculty_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($subject['faculty_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($subject['faculty_email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <i class="bi bi-question-circle me-1 text-primary"></i><?php echo (int)$subject['question_count']; ?> Questions
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <i class="bi bi-pencil-square me-1 text-success"></i><?php echo (int)$subject['quiz_count']; ?> Quizzes
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="action-btn-group justify-content-end">
                                    <button class="btn-action btn-action-edit" title="Edit Subject" 
                                            data-bs-toggle="modal" data-bs-target="#editSubjectModal"
                                            onclick="loadSubjectData(<?php echo htmlspecialchars(json_encode($subject)); ?>)">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Deleting <?php echo addslashes(htmlspecialchars($subject['name'])); ?> will permanently delete its quizzes and questions. Continue?');">
                                        <input type="hidden" name="action" value="delete_subject">
                                        <input type="hidden" name="subject_id" value="<?php echo $subject['subject_id']; ?>">
                                        <button type="submit" class="btn-action btn-action-delete" title="Delete Subject">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="empty-state py-4">
                                <i class="bi bi-journal-plus mb-2" style="font-size: 2.5rem; color: #94a3b8;"></i>
                                <h5 class="fw-bold text-dark">No Subjects Created Yet</h5>
                                <p class="text-muted small mb-3">Get started by creating your first academic subject and assigning an educator.</p>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                                    <i class="bi bi-plus-circle me-1"></i> Add Subject
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-journal-plus me-2 text-primary"></i>Add Academic Subject
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add_subject">
                    <div class="mb-3">
                        <label for="addName" class="form-label fw-semibold">Subject Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="addName" name="name" required placeholder="e.g. Data Structures & Algorithms">
                    </div>
                    <div class="mb-3">
                        <label for="addFacultyId" class="form-label fw-semibold">Assign Faculty In-Charge <span class="text-danger">*</span></label>
                        <select class="form-select" id="addFacultyId" name="faculty_id" required>
                            <option value="">Select a faculty educator...</option>
                            <?php foreach ($facultyUsers as $faculty): ?>
                                <option value="<?php echo $faculty['user_id']; ?>">
                                    <?php echo htmlspecialchars($faculty['name']); ?> (<?php echo htmlspecialchars($faculty['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Only registered faculty members are available for assignment.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Create Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Subject Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_subject">
                    <input type="hidden" name="subject_id" id="editSubjectId">
                    <div class="mb-3">
                        <label for="editName" class="form-label fw-semibold">Subject Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editFacultyId" class="form-label fw-semibold">Assigned Faculty Member <span class="text-danger">*</span></label>
                        <select class="form-select" id="editFacultyId" name="faculty_id" required>
                            <?php foreach ($facultyUsers as $faculty): ?>
                                <option value="<?php echo $faculty['user_id']; ?>">
                                    <?php echo htmlspecialchars($faculty['name']); ?> (<?php echo htmlspecialchars($faculty['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadSubjectData(subject) {
    document.getElementById('editSubjectId').value = subject.subject_id;
    document.getElementById('editName').value = subject.name;
    document.getElementById('editFacultyId').value = subject.faculty_id;
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
