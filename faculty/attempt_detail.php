<?php
$pageTitle = 'Attempt Details';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('faculty');

$user = currentUser();
$attempt_id = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;

if ($attempt_id <= 0) {
    header('Location: /faculty/results.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT a.attempt_id, a.score, a.started_at, a.submitted_at, u.name as student_name, u.email, q.title, q.num_questions, q.marks_correct, a.question_order FROM attempts a INNER JOIN users u ON a.student_id = u.user_id INNER JOIN quizzes q ON a.quiz_id = q.quiz_id INNER JOIN subjects s ON q.subject_id = s.subject_id WHERE a.attempt_id = ? AND s.faculty_id = ?');
    $stmt->execute([$attempt_id, $user['id']]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        header('Location: /faculty/results.php');
        exit;
    }

    $total_possible = $attempt['num_questions'] * $attempt['marks_correct'];
    $percentage = $total_possible > 0 ? ($attempt['score'] / $total_possible) * 100 : 0;

    $question_ids = json_decode($attempt['question_order'], true);
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    $stmt = $pdo->prepare("SELECT aa.*, q.correct_option, q.question_text FROM attempt_answers aa INNER JOIN questions q ON aa.question_id = q.question_id WHERE aa.attempt_id = ? ORDER BY FIELD(aa.question_id, " . implode(',', $question_ids) . ")");
    $stmt->execute(array_merge([$attempt_id], $question_ids));
    $answers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0"><?php echo htmlspecialchars($attempt['title']); ?></h5>
                        <p class="text-muted small mb-0">Student: <?php echo htmlspecialchars($attempt['student_name']); ?></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-0"><strong>Score: <?php echo number_format($attempt['score'], 2); ?></strong> / <?php echo $total_possible; ?></p>
                        <p class="text-muted small mb-0"><?php echo number_format($percentage, 1); ?>%</p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Student Email</h6>
                        <p class="mb-0"><?php echo htmlspecialchars($attempt['email']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Submitted At</h6>
                        <p class="mb-0"><?php echo date('M d, Y H:i:s', strtotime($attempt['submitted_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="progress mb-4" style="height: 2rem;">
            <div class="progress-bar" style="width: <?php echo $percentage; ?>%;">
                <?php echo number_format($percentage, 1); ?>%
            </div>
        </div>

        <h4 class="mb-3">Answer Details</h4>

        <?php foreach ($answers as $idx => $a): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0">Question <?php echo $idx + 1; ?></h5>
                    <?php if ($a['is_correct']): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Correct</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Incorrect</span>
                    <?php endif; ?>
                </div>
                <p class="mb-3"><strong><?php echo htmlspecialchars($a['question_text']); ?></strong></p>

                <div class="row">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <p class="mb-1 small text-muted">Student's Answer:</p>
                            <p class="mb-0">
                                <?php if ($a['selected_option']): ?>
                                    <strong><?php echo $a['selected_option']; ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">Not answered</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded">
                            <p class="mb-1 small text-muted">Correct Answer:</p>
                            <p class="mb-0"><strong class="text-success"><?php echo $a['correct_option']; ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="text-center mt-4">
            <a href="/faculty/results.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Results
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
