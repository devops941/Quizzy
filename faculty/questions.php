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
        $difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : '';

        if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_option) || empty($difficulty)) {
            $error = 'All fields are required.';
        } elseif (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
            $error = 'Invalid correct option.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$subject_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $difficulty]);
                $message = 'Question added successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'delete_question' && isset($_POST['question_id'])) {
        $question_id = (int)$_POST['question_id'];
        try {
            $stmt = $pdo->prepare('DELETE FROM questions WHERE question_id = ?');
            $stmt->execute([$question_id]);
            $message = 'Question deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting question: ' . htmlspecialchars($e->getMessage());
        }
    }
}

try {
    $stmt = $pdo->prepare('SELECT subject_id, name FROM subjects WHERE faculty_id = ? ORDER BY name ASC');
    $stmt->execute([$user['id']]);
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    $subjects = [];
}

$questions = [];
if ($selected_subject > 0) {
    try {
        $stmt = $pdo->prepare('SELECT q.question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, q.difficulty, q.created_at FROM questions q WHERE q.subject_id = ? ORDER BY q.created_at DESC');
        $stmt->execute([$selected_subject]);
        $questions = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <h2 class="mb-4">
            <i class="bi bi-question-circle-fill"></i> Manage Questions
        </h2>

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
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <label class="form-label">Select Subject</label>
        <select class="form-select" id="subjectSelect" onchange="window.location.href = '?subject=' + this.value;">
            <option value="0">Choose a subject...</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?php echo $s['subject_id']; ?>" <?php echo $selected_subject === $s['subject_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6 text-md-end">
        <div class="btn-group" role="group">
            <?php if ($selected_subject > 0): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                    <i class="bi bi-plus-circle"></i> Add Question
                </button>
            <?php endif; ?>
            <a href="/faculty/upload_csv.php" class="btn btn-outline-primary">
                <i class="bi bi-cloud-upload"></i> Bulk Upload
            </a>
        </div>
    </div>
</div>

<?php if ($selected_subject > 0): ?>
<div class="row">
    <div class="col-lg-12">
        <?php if (!empty($questions)): ?>
            <?php foreach ($questions as $idx => $q): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-2">Q<?php echo $idx + 1; ?>: <?php echo htmlspecialchars($q['question_text']); ?></h5>
                                <span class="badge-<?php echo $q['difficulty']; ?>"><?php echo ucfirst($q['difficulty']); ?></span>
                            </div>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="action" value="delete_question">
                                <input type="hidden" name="question_id" value="<?php echo $q['question_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this question?');">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2"><strong>A)</strong> <?php echo htmlspecialchars($q['option_a']); ?> <?php echo $q['correct_option'] === 'A' ? '<span class="badge bg-success">✓ Correct</span>' : ''; ?></p>
                                <p class="mb-2"><strong>B)</strong> <?php echo htmlspecialchars($q['option_b']); ?> <?php echo $q['correct_option'] === 'B' ? '<span class="badge bg-success">✓ Correct</span>' : ''; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2"><strong>C)</strong> <?php echo htmlspecialchars($q['option_c']); ?> <?php echo $q['correct_option'] === 'C' ? '<span class="badge bg-success">✓ Correct</span>' : ''; ?></p>
                                <p class="mb-2"><strong>D)</strong> <?php echo htmlspecialchars($q['option_d']); ?> <?php echo $q['correct_option'] === 'D' ? '<span class="badge bg-success">✓ Correct</span>' : ''; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No questions added yet. Click "Add New Question" to create one.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_question">
                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">

                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="option_a" class="form-label">Option A</label>
                                <input type="text" class="form-control" id="option_a" name="option_a" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="option_b" class="form-label">Option B</label>
                                <input type="text" class="form-control" id="option_b" name="option_b" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="option_c" class="form-label">Option C</label>
                                <input type="text" class="form-control" id="option_c" name="option_c" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="option_d" class="form-label">Option D</label>
                                <input type="text" class="form-control" id="option_d" name="option_d" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="correct_option" class="form-label">Correct Option</label>
                                <select class="form-select" id="correct_option" name="correct_option" required>
                                    <option value="">Select correct option</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="difficulty" class="form-label">Difficulty</label>
                                <select class="form-select" id="difficulty" name="difficulty" required>
                                    <option value="">Select difficulty</option>
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Question</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
