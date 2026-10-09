<?php
$pageTitle = 'Manage Quizzes';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('faculty');

$user = currentUser();
$message = '';
$error = '';
$edit_quiz = null;
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create_quiz' || $action === 'update_quiz') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
        $num_questions = isset($_POST['num_questions']) ? (int)$_POST['num_questions'] : 0;
        $duration_min = isset($_POST['duration_min']) ? (int)$_POST['duration_min'] : 0;
        $marks_correct = isset($_POST['marks_correct']) ? (float)$_POST['marks_correct'] : 1.00;
        $marks_negative = isset($_POST['marks_negative']) ? (float)$_POST['marks_negative'] : 0.00;
        $start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
        $end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
        $quiz_id_post = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;

        $validation_errors = [];

        if (empty($title) || strlen($title) > 150) {
            $validation_errors[] = 'Title is required and must be 150 characters or less.';
        }
        if ($subject_id <= 0) {
            $validation_errors[] = 'Please select a valid subject.';
        }
        if ($num_questions <= 0) {
            $validation_errors[] = 'Number of questions must be greater than 0.';
        }
        if ($duration_min < 1 || $duration_min > 180) {
            $validation_errors[] = 'Duration must be between 1 and 180 minutes.';
        }
        if ($marks_correct < 0) {
            $validation_errors[] = 'Marks for correct answer must be non-negative.';
        }
        if ($marks_negative < 0) {
            $validation_errors[] = 'Negative marks must be non-negative.';
        }
        if (empty($start_time)) {
            $validation_errors[] = 'Start time is required.';
        }
        if (empty($end_time)) {
            $validation_errors[] = 'End time is required.';
        }

        $start_dt = DateTime::createFromFormat('Y-m-d\TH:i', $start_time);
        $end_dt = DateTime::createFromFormat('Y-m-d\TH:i', $end_time);

        if (!$start_dt || !$end_dt) {
            $validation_errors[] = 'Invalid date/time format.';
        } elseif ($start_dt >= $end_dt) {
            $validation_errors[] = 'End time must be after start time.';
        }

        if (empty($validation_errors)) {
            try {
                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM questions WHERE subject_id = ?');
                $stmt->execute([$subject_id]);
                $result = $stmt->fetch();
                $available_questions = $result['count'];

                if ($num_questions > $available_questions) {
                    $validation_errors[] = "Only $available_questions questions available in this subject.";
                }
            } catch (PDOException $e) {
                $validation_errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }

        if (empty($validation_errors)) {
            try {
                $start_time_formatted = $start_dt->format('Y-m-d H:i:s');
                $end_time_formatted = $end_dt->format('Y-m-d H:i:s');

                if ($action === 'create_quiz') {
                    $stmt = $pdo->prepare('INSERT INTO quizzes (subject_id, title, num_questions, duration_min, marks_correct, marks_negative, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$subject_id, $title, $num_questions, $duration_min, $marks_correct, $marks_negative, $start_time_formatted, $end_time_formatted]);
                    $message = 'Quiz created successfully!';
                } else {
                    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM attempts WHERE quiz_id = ?');
                    $stmt->execute([$quiz_id_post]);
                    $attempt_count = $stmt->fetch()['count'];

                    if ($attempt_count > 0) {
                        $error = 'Cannot edit quiz after students have started attempting it.';
                    } else {
                        $stmt = $pdo->prepare('UPDATE quizzes SET title = ?, subject_id = ?, num_questions = ?, duration_min = ?, marks_correct = ?, marks_negative = ?, start_time = ?, end_time = ? WHERE quiz_id = ?');
                        $stmt->execute([$title, $subject_id, $num_questions, $duration_min, $marks_correct, $marks_negative, $start_time_formatted, $end_time_formatted, $quiz_id_post]);
                        $message = 'Quiz updated successfully!';
                        $quiz_id = 0;
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        } else {
            $error = implode(' ', $validation_errors);
        }
    } elseif ($action === 'delete_quiz' && isset($_POST['quiz_id'])) {
        $quiz_id_del = (int)$_POST['quiz_id'];
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM attempts WHERE quiz_id = ?');
            $stmt->execute([$quiz_id_del]);
            $attempt_count = $stmt->fetch()['count'];

            if ($attempt_count > 0) {
                $error = 'Cannot delete quiz after students have started attempting it.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM quizzes WHERE quiz_id = ?');
                $stmt->execute([$quiz_id_del]);
                $message = 'Quiz deleted successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error deleting quiz: ' . htmlspecialchars($e->getMessage());
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

if ($quiz_id > 0) {
    try {
        $stmt = $pdo->prepare('SELECT q.*, s.name as subject_name FROM quizzes q INNER JOIN subjects s ON q.subject_id = s.subject_id WHERE q.quiz_id = ? AND s.faculty_id = ?');
        $stmt->execute([$quiz_id, $user['id']]);
        $edit_quiz = $stmt->fetch();

        if (!$edit_quiz) {
            $error = 'Quiz not found or you do not have permission to edit it.';
            $quiz_id = 0;
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        $quiz_id = 0;
    }
}

try {
    $stmt = $pdo->prepare('
        SELECT q.quiz_id, q.title, s.name as subject_name, q.start_time, q.end_time, q.num_questions, COUNT(a.attempt_id) as attempt_count
        FROM quizzes q
        INNER JOIN subjects s ON q.subject_id = s.subject_id
        LEFT JOIN attempts a ON q.quiz_id = a.quiz_id
        WHERE s.faculty_id = ?
        GROUP BY q.quiz_id
        ORDER BY q.start_time DESC
    ');
    $stmt->execute([$user['id']]);
    $quizzes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    $quizzes = [];
}

$nowStr = $pdo->query("SELECT NOW() as now")->fetch()['now'];
$now = new DateTime($nowStr);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <h2 class="mb-4">
            <i class="bi bi-pencil-square"></i> Manage Quizzes
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

<div class="row mb-5">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-plus-circle"></i> <?php echo $quiz_id > 0 ? 'Edit Quiz' : 'Create New Quiz'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $quiz_id > 0 ? 'update_quiz' : 'create_quiz'; ?>">
                    <?php if ($quiz_id > 0): ?>
                        <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="title" class="form-label">Quiz Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Midterm Exam" maxlength="150" required value="<?php echo $edit_quiz ? htmlspecialchars($edit_quiz['title']) : ''; ?>">
                        <small class="text-muted">Maximum 150 characters</small>
                    </div>

                    <div class="mb-3">
                        <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                        <select class="form-select" id="subject_id" name="subject_id" required onchange="updateQuestionCount()">
                            <option value="">Choose a subject...</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['subject_id']; ?>"
                                    <?php echo ($edit_quiz && $edit_quiz['subject_id'] === $s['subject_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="num_questions" class="form-label">Number of Questions <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="num_questions" name="num_questions" min="1" max="500" required value="<?php echo $edit_quiz ? $edit_quiz['num_questions'] : ''; ?>">
                            <span class="input-group-text" id="max-questions-hint">Out of ? available</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="duration_min" class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="duration_min" name="duration_min" min="1" max="180" required value="<?php echo $edit_quiz ? $edit_quiz['duration_min'] : ''; ?>">
                        <small class="text-muted">Between 1 and 180 minutes</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="marks_correct" class="form-label">Marks for Correct Answer</label>
                                <input type="number" class="form-control" id="marks_correct" name="marks_correct" min="0" step="0.01" value="<?php echo $edit_quiz ? $edit_quiz['marks_correct'] : '1.00'; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="marks_negative" class="form-label">Negative Marks</label>
                                <input type="number" class="form-control" id="marks_negative" name="marks_negative" min="0" step="0.01" value="<?php echo $edit_quiz ? $edit_quiz['marks_negative'] : '0.00'; ?>">
                                <small class="text-muted">Subtracted for wrong answer</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="start_time" name="start_time" required value="<?php echo $edit_quiz ? substr($edit_quiz['start_time'], 0, 16) : ''; ?>">
                    </div>

                    <div class="mb-3">
                        <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="end_time" name="end_time" required value="<?php echo $edit_quiz ? substr($edit_quiz['end_time'], 0, 16) : ''; ?>">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg"></i> <?php echo $quiz_id > 0 ? 'Update Quiz' : 'Create Quiz'; ?>
                    </button>

                    <?php if ($quiz_id > 0): ?>
                        <a href="?quiz_id=0" class="btn btn-secondary w-100 mt-2">
                            <i class="bi bi-x-lg"></i> Cancel
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="alert alert-info" role="alert">
            <h6 class="alert-heading">
                <i class="bi bi-info-circle"></i> Quiz Setup Tips
            </h6>
            <ul class="mb-0">
                <li>Make sure you have added enough questions to your subject before creating the quiz.</li>
                <li>Start time must be before end time.</li>
                <li>Students can only attempt the quiz between start and end times.</li>
                <li>Questions will be shuffled randomly for each student.</li>
                <li>You can edit a quiz only if no students have started attempting it.</li>
                <li>Once students begin attempting, the quiz is locked from editing.</li>
            </ul>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <h4 class="mb-3">
            <i class="bi bi-list-check"></i> Your Quizzes
        </h4>

        <?php if (empty($quizzes)): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle"></i> No quizzes created yet. Create your first quiz to get started.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Start Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $q):
                            $quiz_start = DateTime::createFromFormat('Y-m-d H:i:s', $q['start_time']);
                            $quiz_end = DateTime::createFromFormat('Y-m-d H:i:s', $q['end_time']);

                            if ($now < $quiz_start) {
                                $status = 'Upcoming';
                                $status_badge = 'badge bg-info';
                            } elseif ($now > $quiz_end) {
                                $status = 'Ended';
                                $status_badge = 'badge bg-secondary';
                            } else {
                                $status = 'Active';
                                $status_badge = 'badge bg-success';
                            }
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($q['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($q['subject_name']); ?></td>
                                <td><span class="<?php echo $status_badge; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                <td><?php echo $q['num_questions']; ?></td>
                                <td><?php echo $q['attempt_count']; ?></td>
                                <td><small><?php echo date('M d, Y H:i', strtotime($q['start_time'])); ?></small></td>
                                <td>
                                    <a href="?quiz_id=<?php echo $q['quiz_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="/faculty/results.php?quiz_id=<?php echo $q['quiz_id']; ?>" class="btn btn-sm btn-outline-success" title="View Results">
                                        <i class="bi bi-bar-chart"></i>
                                    </a>
                                    <?php if ($q['attempt_count'] == 0): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this quiz?');"><input type="hidden" name="action" value="delete_quiz"><input type="hidden" name="quiz_id" value="<?php echo $q['quiz_id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-danger" disabled title="Cannot delete quiz with attempts">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateQuestionCount() {
    const subjectSelect = document.getElementById('subject_id');
    const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];

    if (selectedOption.value) {
        const subjectId = parseInt(selectedOption.value);

        fetch('/faculty/get_question_count.php?subject_id=' + subjectId)
            .then(response => response.json())
            .then(data => {
                const maxQuestionsHint = document.getElementById('max-questions-hint');
                maxQuestionsHint.textContent = 'Out of ' + data.count + ' available';
                document.getElementById('num_questions').max = data.count;
            })
            .catch(error => console.error('Error:', error));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateQuestionCount();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
