<?php
$pageTitle = 'Manage Questions';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('faculty');

$user = currentUser();
$message = '';
$error = '';
$selected_subject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_question') {
        $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
        $question_text = isset($_POST['question_text']) ? trim($_POST['question_text']) : '';
        $option_a = isset($_POST['option_a']) ? trim($_POST['option_a']) : '';
        $option_b = isset($_POST['option_b']) ? trim($_POST['option_b']) : '';
        $option_c = isset($_POST['option_c']) ? trim($_POST['option_c']) : '';
        $option_d = isset($_POST['option_d']) ? trim($_POST['option_d']) : '';
        $correct_option = isset($_POST['correct_option']) ? strtoupper($_POST['correct_option']) : '';

        if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_option)) {
            $error = 'All fields are required.';
        } elseif (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
            $error = 'Invalid correct option.';
        } else {
            try {
                // Verify this subject belongs to the logged-in faculty
                $check = $pdo->prepare('SELECT 1 FROM subjects WHERE subject_id = ? AND faculty_id = ?');
                $check->execute([$subject_id, $user['id']]);
                if (!$check->fetch()) {
                    $error = 'Unauthorized: subject does not belong to you.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$subject_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, 'medium']);
                    $message = 'Question added successfully!';
                }
            } catch (PDOException $e) {
                error_log('Add question error: ' . $e->getMessage());
                $error = 'Database error adding question.';
            }
        }
    } elseif ($_POST['action'] === 'delete_question' && isset($_POST['question_id'])) {
        $question_id = (int)$_POST['question_id'];
        try {
            // Only delete if the question belongs to a subject owned by this faculty
            $stmt = $pdo->prepare('
                DELETE q FROM questions q
                INNER JOIN subjects s ON q.subject_id = s.subject_id
                WHERE q.question_id = ? AND s.faculty_id = ?
            ');
            $stmt->execute([$question_id, $user['id']]);
            $message = $stmt->rowCount() > 0 ? 'Question deleted successfully!' : 'Question not found or unauthorized.';
        } catch (PDOException $e) {
            error_log('Delete question error: ' . $e->getMessage());
            $error = 'Error deleting question.';
        }
    }
}

try {
    $stmt = $pdo->prepare('
        SELECT s.subject_id, s.name, COUNT(q.question_id) as question_count
        FROM subjects s
        LEFT JOIN questions q ON s.subject_id = q.subject_id
        WHERE s.faculty_id = ?
        GROUP BY s.subject_id, s.name
        ORDER BY s.name ASC
    ');
    $stmt->execute([$user['id']]);
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    $subjects = [];
}

$questions = [];
if ($selected_subject > 0) {
    try {
        $stmt = $pdo->prepare('SELECT q.question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, q.created_at FROM questions q WHERE q.subject_id = ? ORDER BY q.created_at DESC');
        $stmt->execute([$selected_subject]);
        $questions = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-question-circle"></i> Manage Questions</h2>
        <p class="text-muted small mb-0">Create, curate, and review multiple choice questions for your subjects.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if ($selected_subject > 0): ?>
            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                <i class="bi bi-plus-lg"></i> Add Question
            </button>
        <?php endif; ?>
        <a href="<?= BASE_PATH ?>/faculty/upload_csv.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-inline-flex align-items-center gap-1">
            <i class="bi bi-cloud-upload"></i> Bulk Upload
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: rgba(99, 102, 241, 0.12); color: #4f46e5;">
                <i class="bi bi-funnel-fill"></i>
            </div>
            <div>
                <span class="fw-bold text-dark d-block" style="font-size: 0.9rem;">Filter by Subject</span>
                <span class="text-muted small" style="font-size: 0.75rem;">Switch subject repository</span>
            </div>
        </div>
        <div style="min-width: 260px; max-width: 420px; width: 100%;">
            <select class="form-select" id="subjectSelect" onchange="window.location.href = '?subject=' + this.value;">
                <option value="0">Choose a subject to view questions...</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo $s['subject_id']; ?>" <?php echo $selected_subject === (int)$s['subject_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['name']); ?> (<?php echo (int)$s['question_count']; ?> questions)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<?php if ($selected_subject > 0): ?>
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <a href="<?= BASE_PATH ?>/faculty/questions.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Back to All Subjects
        </a>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill">
            <?php echo count($questions); ?> Question<?php echo count($questions) === 1 ? '' : 's'; ?> in this subject
        </span>
    </div>
    <?php if (!empty($questions)): ?>
        <?php foreach ($questions as $idx => $q): ?>
            <div class="card border-0 question-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light text-dark border fw-bold">Q<?php echo $idx + 1; ?></span>
                        </div>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="question_id" value="<?php echo $q['question_id']; ?>">
                            <button type="submit" class="btn-action btn-action-delete" title="Delete Question" onclick="return confirm('Delete this question?');">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                    <p class="fw-semibold mb-3"><?php echo htmlspecialchars($q['question_text']); ?></p>
                    <div class="row g-2">
                        <?php foreach (['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']] as $letter => $text): ?>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:<?php echo $q['correct_option'] === $letter ? 'var(--success-pale)' : 'var(--gray-50)'; ?>; border: 1px solid <?php echo $q['correct_option'] === $letter ? 'var(--success)' : 'var(--gray-200)'; ?>;">
                                    <span class="fw-700 small" style="color:<?php echo $q['correct_option'] === $letter ? 'var(--success)' : 'var(--gray-400)'; ?>;"><?php echo $letter; ?></span>
                                    <span class="small"><?php echo htmlspecialchars($text); ?></span>
                                    <?php if ($q['correct_option'] === $letter): ?>
                                        <i class="bi bi-check-circle-fill ms-auto" style="color:var(--success);"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card border-0 shadow-sm p-4 p-md-5 my-3">
            <div class="empty-state-modern py-3">
                <div class="empty-state-icon-box">
                    <i class="bi bi-file-earmark-plus"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">No Questions Added Yet</h5>
                <p class="text-muted small mb-4" style="max-width: 440px; margin-inline: auto;">
                    Start building the question repository for this subject. Add questions individually or upload in bulk using CSV.
                </p>
                <div class="d-inline-flex align-items-center justify-content-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                        <i class="bi bi-plus-lg"></i> Add First Question
                    </button>
                    <a href="<?= BASE_PATH ?>/faculty/upload_csv.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-cloud-upload"></i> Bulk Upload
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="addQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_question">
                        <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                        <div class="mb-3">
                            <label for="question_text" class="form-label">Question Text</label>
                            <textarea class="form-control" id="question_text" name="question_text" rows="3" required placeholder="Enter the question..."></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="option_a" class="form-label">Option A</label>
                                <input type="text" class="form-control" id="option_a" name="option_a" required>
                            </div>
                            <div class="col-md-6">
                                <label for="option_b" class="form-label">Option B</label>
                                <input type="text" class="form-control" id="option_b" name="option_b" required>
                            </div>
                            <div class="col-md-6">
                                <label for="option_c" class="form-label">Option C</label>
                                <input type="text" class="form-control" id="option_c" name="option_c" required>
                            </div>
                            <div class="col-md-6">
                                <label for="option_d" class="form-label">Option D</label>
                                <input type="text" class="form-control" id="option_d" name="option_d" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="correct_option" class="form-label">Correct Option</label>
                            <select class="form-select" id="correct_option" name="correct_option" required>
                                <option value="">Select correct option</option>
                                <option value="A">Option A</option>
                                <option value="B">Option B</option>
                                <option value="C">Option C</option>
                                <option value="D">Option D</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-journals text-primary fs-5"></i>
                <div>
                    <h6 class="fw-bold text-dark mb-0">My Subject Question Banks</h6>
                    <small class="text-muted">Select any subject from the list below to view and manage questions</small>
                </div>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill">
                <?= count($subjects) ?> Assigned Subject<?= count($subjects) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if (!empty($subjects)): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($subjects as $idx => $sub): ?>
                    <a href="?subject=<?= $sub['subject_id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3 subject-list-row">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center text-primary" style="width: 44px; height: 44px; background: rgba(99, 102, 241, 0.1); font-size: 1.25rem; flex-shrink: 0;">
                                <i class="bi bi-book-half"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;"><?= htmlspecialchars($sub['name']) ?></h6>
                                <span class="text-muted small" style="font-size: 0.78rem;">Click to open questions repository</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge <?= (int)$sub['question_count'] > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-light text-muted border' ?> px-3 py-1.5 rounded-pill" style="font-size: 0.78rem;">
                                <i class="bi bi-question-circle me-1"></i><?= (int)$sub['question_count'] ?> Question<?= (int)$sub['question_count'] === 1 ? '' : 's' ?>
                            </span>
                            <span class="btn btn-sm btn-outline-primary rounded-pill px-3 d-none d-sm-inline-flex align-items-center gap-1">
                                <span>Manage</span> <i class="bi bi-arrow-right"></i>
                            </span>
                            <i class="bi bi-chevron-right text-muted d-sm-none"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-journal-x mb-2" style="font-size: 2.5rem; opacity: 0.5;"></i>
                <p class="mb-0">No subjects assigned to your faculty account yet.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
