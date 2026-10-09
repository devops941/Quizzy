<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

if ($attemptId <= 0) {
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.attempt_id, a.quiz_id, a.score, a.submitted_at, a.option_maps, a.question_order,
               q.title, q.marks_correct, q.num_questions, q.subject_id
        FROM attempts a
        JOIN quizzes q ON a.quiz_id = q.quiz_id
        WHERE a.attempt_id = ? AND a.student_id = ?
    ");
    $stmt->execute([$attemptId, $userId]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Quiz attempt not found.'];
        header('Location: dashboard.php');
        exit;
    }

    $totalPossible = (int)$attempt['num_questions'] * $attempt['marks_correct'];
    $percentage = $totalPossible > 0 ? ($attempt['score'] / $totalPossible) * 100 : 0;

    // Get all answers with question details
    $questionIds = json_decode($attempt['question_order'], true);
    $optionMaps = json_decode($attempt['option_maps'], true);

    $optionTextMap = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];
    $answers = [];
    foreach ($questionIds as $idx => $qid) {
        $stmt = $pdo->prepare("
            SELECT aa.selected_option, aa.is_correct,
                   q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option
            FROM attempt_answers aa
            JOIN questions q ON aa.question_id = q.question_id
            WHERE aa.attempt_id = ? AND aa.question_id = ?
        ");
        $stmt->execute([$attemptId, $qid]);
        $answer = $stmt->fetch();

        if ($answer && !$answer['is_correct']) { // only wrong answers
            $answer['display_order'] = $idx;

            // Translate displayed option → original option → get text
            $displayedOpt = $answer['selected_option'];
            if ($displayedOpt && isset($optionMaps[$qid][$displayedOpt])) {
                $originalOpt = $optionMaps[$qid][$displayedOpt];
                $answer['your_answer_text'] = $answer[$optionTextMap[$originalOpt]];
            } else {
                $answer['your_answer_text'] = null;
            }

            // Correct answer text (original correct_option is always the unshuffled letter)
            $correctOpt = $answer['correct_option'];
            $answer['correct_answer_text'] = $answer[$optionTextMap[$correctOpt]];

            $answers[] = $answer;
        }
    }

    $pageTitle = 'Quiz Result';
    require_once __DIR__.'/../includes/header.php';

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error loading result: ' . htmlspecialchars($e->getMessage())];
    header('Location: dashboard.php');
    exit;
}
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-lg mb-4">
            <div class="card-body text-center py-5">
                <h2 class="mb-4"><?php echo htmlspecialchars($attempt['title']); ?></h2>

                <div class="mb-4">
                    <div style="font-size: 48px; font-weight: bold; color: #4f46e5; margin-bottom: 10px;">
                        <?php echo number_format($attempt['score'], 2); ?>
                    </div>
                    <div style="font-size: 16px; color: #666; margin-bottom: 15px;">
                        out of <?php echo number_format($totalPossible, 2); ?>
                    </div>

                    <?php if ($percentage >= 80): ?>
                        <div style="color: #28a745; font-size: 18px; font-weight: 500;">
                            <i class="bi bi-check-circle"></i> Excellent!
                        </div>
                    <?php elseif ($percentage >= 60): ?>
                        <div style="color: #17a2b8; font-size: 18px; font-weight: 500;">
                            <i class="bi bi-info-circle"></i> Good
                        </div>
                    <?php else: ?>
                        <div style="color: #ffc107; font-size: 18px; font-weight: 500;">
                            <i class="bi bi-exclamation-circle"></i> Needs Improvement
                        </div>
                    <?php endif; ?>
                </div>

                <p class="text-muted mb-0">
                    <i class="bi bi-calendar-event me-2"></i>
                    Submitted on <?php echo date('M d, Y h:i A', strtotime($attempt['submitted_at'])); ?>
                </p>
            </div>
        </div>

        <div class="progress mb-4" style="height: 25px;">
            <?php
                $barClass = 'bg-success';
                if ($percentage < 60) {
                    $barClass = 'bg-danger';
                } elseif ($percentage < 80) {
                    $barClass = 'bg-info';
                }
            ?>
            <div class="progress-bar <?php echo $barClass; ?>" style="width: <?php echo $percentage; ?>%; font-size: 14px; line-height: 25px;">
                <?php echo number_format($percentage, 1); ?>%
            </div>
        </div>

        <h4 class="mb-3"><i class="bi bi-x-circle text-danger me-2"></i>Wrong Answers (<?php echo count($answers); ?>)</h4>

        <?php if (empty($answers)): ?>
        <div class="alert alert-success">
            <i class="bi bi-trophy me-2"></i> Perfect score! All answers were correct.
        </div>
        <?php endif; ?>

        <?php foreach ($answers as $a): ?>
        <div class="card border-0 border-start border-danger border-3 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0 text-muted">Question <?php echo $a['display_order'] + 1; ?></h6>
                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Wrong</span>
                </div>

                <p class="fw-semibold mb-3"><?php echo htmlspecialchars($a['question_text']); ?></p>

                <div class="d-flex gap-3 flex-wrap">
                    <div class="flex-fill bg-danger bg-opacity-10 border border-danger border-opacity-25 p-2 rounded">
                        <div class="small text-muted mb-1">Your Answer</div>
                        <div class="text-danger fw-semibold">
                            <?php if ($a['your_answer_text']): ?>
                                <?php echo htmlspecialchars($a['your_answer_text']); ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Not answered</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex-fill bg-success bg-opacity-10 border border-success border-opacity-25 p-2 rounded">
                        <div class="small text-muted mb-1">Correct Answer</div>
                        <div class="text-success fw-semibold"><?php echo htmlspecialchars($a['correct_answer_text']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="text-center mt-4 mb-4">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
