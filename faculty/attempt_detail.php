<?php
$pageTitle = 'Attempt Details';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('faculty');

$user = currentUser();
$attempt_id = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;

if ($attempt_id <= 0) {
    header('Location: ' . BASE_PATH . '/faculty/results.php');
    exit;
}

$attempt = null;
$answers = [];
$total_possible = 0;
$percentage = 0;
$error = '';

try {
    $stmt = $pdo->prepare('SELECT a.attempt_id, a.score, a.started_at, a.submitted_at, u.name as student_name, u.email, q.title, q.num_questions, q.marks_correct, a.question_order, a.option_maps FROM attempts a INNER JOIN users u ON a.student_id = u.user_id INNER JOIN quizzes q ON a.quiz_id = q.quiz_id INNER JOIN subjects s ON q.subject_id = s.subject_id WHERE a.attempt_id = ? AND s.faculty_id = ?');
    $stmt->execute([$attempt_id, $user['id']]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        header('Location: ' . BASE_PATH . '/faculty/results.php');
        exit;
    }

    $total_possible = $attempt['num_questions'] * $attempt['marks_correct'];
    $percentage = $total_possible > 0 ? ($attempt['score'] / $total_possible) * 100 : 0;

    $question_ids = json_decode($attempt['question_order'], true);
    $optionMaps = !empty($attempt['option_maps']) ? json_decode($attempt['option_maps'], true) : [];

    if (!empty($question_ids) && is_array($question_ids)) {
        $safeIds = array_map('intval', $question_ids);
        $fieldClause = implode(',', $safeIds);
        $stmt = $pdo->prepare("
            SELECT aa.*, q.correct_option, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d 
            FROM attempt_answers aa 
            INNER JOIN questions q ON aa.question_id = q.question_id 
            WHERE aa.attempt_id = ? 
            ORDER BY FIELD(aa.question_id, $fieldClause)
        ");
        $stmt->execute([$attempt_id]);
        $answers = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Attempt detail error: ' . $e->getMessage());
    $error = 'Database error loading attempt details.';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="<?= BASE_PATH ?>/faculty/results.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 mb-2 d-inline-flex align-items-center gap-1">
        <i class="bi bi-arrow-left"></i> Back to Results
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($attempt): ?>
<div class="row">
    <div class="col-lg-10">
        <!-- Overview Scorecard -->
        <div class="card border-0 shadow-sm mb-4 overflow-hidden">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="badge bg-primary-pale text-primary text-uppercase px-2 py-1 fw-bold" style="font-size: 0.7rem;">Examination Evaluation</span>
                    <h4 class="mb-0 fw-800 text-dark mt-1"><?php echo htmlspecialchars($attempt['title']); ?></h4>
                </div>
                <div class="text-end">
                    <span class="badge <?php echo $percentage >= 50 ? 'bg-success-pale text-success' : 'bg-danger-pale text-danger'; ?> fs-6 px-3 py-1 border rounded-pill">
                        <?php echo number_format($percentage, 1); ?>% (<?php echo $percentage >= 50 ? 'Passed' : 'Failed'; ?>)
                    </span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6 border-end-md">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-pale text-primary fw-bold" 
                                 style="width: 48px; height: 48px; font-size: 1.25rem;">
                                <?php echo strtoupper(substr($attempt['student_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($attempt['student_name']); ?></h5>
                                <div class="text-muted small"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($attempt['email']); ?></div>
                                <div class="text-muted small"><i class="bi bi-clock me-1"></i>Submitted <?php echo date('M d, Y h:i A', strtotime($attempt['submitted_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-center">
                        <div class="text-muted small text-uppercase fw-bold mb-1">Final Score Recorded</div>
                        <div class="fs-2 fw-800 text-primary">
                            <?php echo number_format($attempt['score'], 1); ?>
                            <span class="text-muted fs-5 fw-normal">/ <?php echo number_format($total_possible, 1); ?> pts</span>
                        </div>
                        <div class="progress mt-2 mx-auto" style="height: 8px; max-width: 250px;">
                            <div class="progress-bar <?php echo $percentage >= 50 ? 'bg-success' : 'bg-danger'; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-list-check text-primary"></i> Question Response Breakdown (<?php echo count($answers); ?>)
        </h5>

        <?php 
        $optionTextMap = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];
        foreach ($answers as $idx => $a): 
            $qid = (int)$a['question_id'];
            $displayedOpt = $a['selected_option'];
            $originalSelectedOpt = null;
            $studentAnswerText = '';

            if (!empty($displayedOpt)) {
                // Map the displayed option back to the original option letter
                if (isset($optionMaps[$qid][$displayedOpt])) {
                    $originalSelectedOpt = $optionMaps[$qid][$displayedOpt];
                } else {
                    $originalSelectedOpt = $displayedOpt;
                }

                if (isset($optionTextMap[$originalSelectedOpt]) && isset($a[$optionTextMap[$originalSelectedOpt]])) {
                    $studentAnswerText = $a[$optionTextMap[$originalSelectedOpt]];
                }
            }

            $correctOpt = $a['correct_option'];
            $correctAnswerText = isset($optionTextMap[$correctOpt]) && isset($a[$optionTextMap[$correctOpt]]) ? $a[$optionTextMap[$correctOpt]] : '';
            $isAnswered = !empty($displayedOpt);
            $isCorrect = (bool)$a['is_correct'];
        ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-light text-dark border fw-bold">Question <?php echo $idx + 1; ?></span>
                    <?php if (!$isAnswered): ?>
                        <span class="badge bg-light text-muted border px-3 py-1 rounded-pill">
                            <i class="bi bi-dash-circle me-1"></i> Not Attempted
                        </span>
                    <?php elseif ($isCorrect): ?>
                        <span class="badge bg-success-pale text-success border border-success-subtle px-3 py-1 rounded-pill">
                            <i class="bi bi-check-circle-fill me-1"></i> Correct Answer
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger-pale text-danger border border-danger-subtle px-3 py-1 rounded-pill">
                            <i class="bi bi-x-circle-fill me-1"></i> Incorrect Answer
                        </span>
                    <?php endif; ?>
                </div>
                <p class="fw-semibold text-dark mb-3"><?php echo htmlspecialchars($a['question_text']); ?></p>

                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="p-3 rounded-3" style="background: <?php echo !$isAnswered ? 'var(--gray-50)' : ($isCorrect ? 'var(--success-pale)' : 'var(--danger-pale)'); ?>; border: 1px solid <?php echo !$isAnswered ? 'var(--gray-200)' : ($isCorrect ? '#a7f3d0' : '#fecaca'); ?>;">
                            <small class="text-muted text-uppercase fw-bold d-block mb-1">Student's Selection</small>
                            <?php if ($isAnswered): ?>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge <?php echo $isCorrect ? 'bg-success' : 'bg-danger'; ?> text-white fw-bold">
                                        Option <?php echo htmlspecialchars($originalSelectedOpt); ?>
                                    </span>
                                    <?php if (!empty($studentAnswerText)): ?>
                                        <span class="fw-semibold <?php echo $isCorrect ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo htmlspecialchars($studentAnswerText); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small fst-italic">No answer selected (Skipped)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 bg-light border border-success-subtle">
                            <small class="text-muted text-uppercase fw-bold d-block mb-1">Official Correct Key</small>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success text-white fw-bold">
                                    Option <?php echo htmlspecialchars($correctOpt); ?>
                                </span>
                                <?php if (!empty($correctAnswerText)): ?>
                                    <span class="fw-semibold text-dark">
                                        <?php echo htmlspecialchars($correctAnswerText); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
