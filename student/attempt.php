<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('student');

$userId = $_SESSION['user']['id'];

// Handle POST for saving answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_answer') {
    $attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;
    $questionId = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
    $selectedOption = isset($_POST['selected_option']) ? strtoupper($_POST['selected_option']) : null;

    if ($attemptId > 0 && $questionId > 0 && in_array($selectedOption, ['A','B','C','D'], true)) {
        try {
            // Get option_maps to translate displayed option → original option
            $stmt = $pdo->prepare("SELECT option_maps FROM attempts WHERE attempt_id = ? AND student_id = ?");
            $stmt->execute([$attemptId, $userId]);
            $attemptRow = $stmt->fetch();

            $isCorrect = 0;
            if ($attemptRow) {
                $optionMaps = json_decode($attemptRow['option_maps'], true);
                $originalOption = $optionMaps[$questionId][$selectedOption] ?? $selectedOption;

                $stmt = $pdo->prepare("SELECT correct_option FROM questions WHERE question_id = ?");
                $stmt->execute([$questionId]);
                $q = $stmt->fetch();
                $isCorrect = ($q && $originalOption === $q['correct_option']) ? 1 : 0;
            }

            // UPSERT — check existing first
            $stmt = $pdo->prepare("SELECT id FROM attempt_answers WHERE attempt_id = ? AND question_id = ?");
            $stmt->execute([$attemptId, $questionId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("UPDATE attempt_answers SET selected_option=?, is_correct=? WHERE id=?");
                $stmt->execute([$selectedOption, $isCorrect, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO attempt_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?,?,?,?)");
                $stmt->execute([$attemptId, $questionId, $selectedOption, $isCorrect]);
            }
        } catch (Exception $e) {
            error_log('Error saving answer: ' . $e->getMessage());
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}

$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$questionIndex = isset($_GET['q']) ? (int)$_GET['q'] : 0;

if ($quizId <= 0) {
    header('Location: attempt.php');
    exit;
}

try {
    // Get current MySQL server time (avoids PHP/MySQL timezone mismatch)
    $nowStr = $pdo->query("SELECT NOW() as now")->fetch()['now'];
    $now = new DateTime($nowStr);

    // Get quiz details + active status from MySQL
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, q.subject_id, q.duration_min, q.num_questions,
               q.marks_correct, q.marks_negative, q.start_time, q.end_time,
               (NOW() >= q.start_time AND NOW() <= q.end_time) as is_active,
               (NOW() < q.start_time) as not_started_yet,
               (NOW() > q.end_time) as has_expired
        FROM quizzes q
        WHERE q.quiz_id = ?
    ");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Quiz not found.'];
        header('Location: dashboard.php');
        exit;
    }

    // Validate quiz is active
    if ($quiz['not_started_yet']) {
        $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'This quiz has not started yet.'];
        header('Location: dashboard.php');
        exit;
    }

    if ($quiz['has_expired']) {
        $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'This quiz has expired.'];
        header('Location: dashboard.php');
        exit;
    }

    // Check for existing attempt
    $stmt = $pdo->prepare("
        SELECT attempt_id, question_order, option_maps, started_at, submitted_at
        FROM attempts
        WHERE quiz_id = ? AND student_id = ?
    ");
    $stmt->execute([$quizId, $userId]);
    $attempt = $stmt->fetch();

    // Create new attempt if doesn't exist
    if (!$attempt) {
        // Get random questions
        $stmt = $pdo->prepare("
            SELECT question_id FROM questions
            WHERE subject_id = ?
            ORDER BY RAND()
            LIMIT ?
        ");
        $stmt->execute([$quiz['subject_id'], $quiz['num_questions']]);
        $selectedQuestions = $stmt->fetchAll();
        $questionIds = array_column($selectedQuestions, 'question_id');

        // Generate option maps (shuffle options for each question)
        $optionMaps = [];
        foreach ($questionIds as $qid) {
            $originalOptions = ['A', 'B', 'C', 'D'];
            shuffle($originalOptions);
            $displayMap = [];
            foreach (['A', 'B', 'C', 'D'] as $idx => $displayOpt) {
                $displayMap[$displayOpt] = $originalOptions[$idx];
            }
            $optionMaps[$qid] = $displayMap;
        }

        $questionOrder = json_encode($questionIds);
        $optionMapsJson = json_encode($optionMaps);

        $stmt = $pdo->prepare("
            INSERT INTO attempts (quiz_id, student_id, started_at, question_order, option_maps)
            VALUES (?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([$quizId, $userId, $questionOrder, $optionMapsJson]);

        $attemptId = $pdo->lastInsertId();

        $attempt = [
            'attempt_id' => $attemptId,
            'question_order' => $questionOrder,
            'option_maps' => $optionMapsJson,
            'started_at' => $nowStr,
            'submitted_at' => null
        ];
    }

    // If already submitted, redirect to result
    if ($attempt['submitted_at'] !== null) {
        header('Location: result.php?attempt_id=' . $attempt['attempt_id']);
        exit;
    }

    $questionIds = json_decode($attempt['question_order'], true);
    $optionMaps = json_decode($attempt['option_maps'], true);

    // Validate question index
    if ($questionIndex < 0 || $questionIndex >= count($questionIds)) {
        $questionIndex = 0;
    }

    $currentQuestionId = $questionIds[$questionIndex];

    // Get current question
    $stmt = $pdo->prepare("
        SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_option
        FROM questions
        WHERE question_id = ?
    ");
    $stmt->execute([$currentQuestionId]);
    $currentQuestion = $stmt->fetch();

    // Get student's previous answer if any
    $stmt = $pdo->prepare("
        SELECT selected_option FROM attempt_answers
        WHERE attempt_id = ? AND question_id = ?
    ");
    $stmt->execute([$attempt['attempt_id'], $currentQuestionId]);
    $previousAnswer = $stmt->fetch();
    $selectedOption = $previousAnswer ? $previousAnswer['selected_option'] : null;

    // Get started time for timer
    $startedAt = new DateTime($attempt['started_at']);
    $now = new DateTime($nowStr);
    $elapsedSeconds = $now->getTimestamp() - $startedAt->getTimestamp();
    $totalSeconds = $quiz['duration_min'] * 60;
    $timeRemaining = max(0, $totalSeconds - $elapsedSeconds);

    $pageTitle = 'Take Quiz';
    require_once __DIR__.'/../includes/header.php';

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error: ' . htmlspecialchars($e->getMessage())];
    header('Location: dashboard.php');
    exit;
}
?>

<style>
    .quiz-timer {
        position: sticky;
        top: 70px;
        background: white;
        border: 2px solid #4f46e5;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 100;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .quiz-timer.timer-critical {
        background: #fff3cd;
        border-color: #ff6b6b;
    }

    .timer-info {
        flex: 1;
    }

    .timer-value {
        font-size: 28px;
        font-weight: bold;
        color: #4f46e5;
        margin: 0;
    }

    .quiz-timer.timer-critical .timer-value {
        color: #ff6b6b;
    }

    .timer-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 2px;
    }

    .option-label {
        display: block;
        padding: 12px 15px;
        margin-bottom: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
        background: #f8f9fa;
    }

    .option-label:hover {
        border-color: #4f46e5;
        background: #f0f0ff;
    }

    .option-label input[type="radio"] {
        margin-right: 10px;
    }

    .option-label.selected {
        background: #e8eaff;
        border-color: #4f46e5;
        font-weight: 500;
    }
</style>

<div class="quiz-timer">
    <div class="timer-info">
        <div class="timer-value" id="timer">00:00</div>
        <div class="timer-label">Time Remaining</div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h3 class="mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                <p class="text-muted mb-0">
                    Question <?php echo $questionIndex + 1; ?> of <?php echo count($questionIds); ?>
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="mb-4"><?php echo htmlspecialchars($currentQuestion['question_text']); ?></h5>

                <form id="answerForm" method="POST" action="attempt.php" class="mb-4">
                    <input type="hidden" name="action" value="answer">
                    <input type="hidden" name="quiz_id" value="<?php echo $quizId; ?>">
                    <input type="hidden" name="attempt_id" value="<?php echo $attempt['attempt_id']; ?>">
                    <input type="hidden" name="question_id" value="<?php echo $currentQuestionId; ?>">

                    <div class="options">
                        <?php
                        $displayOptions = ['A', 'B', 'C', 'D'];
                        $originalTexts = [
                            'A' => $currentQuestion['option_a'],
                            'B' => $currentQuestion['option_b'],
                            'C' => $currentQuestion['option_c'],
                            'D' => $currentQuestion['option_d']
                        ];
                        // Apply option_maps shuffle: displayed A shows text of original $qMap['A']
                        $qMap = $optionMaps[$currentQuestionId] ?? ['A'=>'A','B'=>'B','C'=>'C','D'=>'D'];
                        $optionTexts = [
                            'A' => $originalTexts[$qMap['A']],
                            'B' => $originalTexts[$qMap['B']],
                            'C' => $originalTexts[$qMap['C']],
                            'D' => $originalTexts[$qMap['D']],
                        ];

                        foreach ($displayOptions as $displayOpt):
                        ?>
                        <label class="option-label <?php if ($selectedOption === $displayOpt) echo 'selected'; ?>">
                            <input type="radio" name="selected_option" value="<?php echo $displayOpt; ?>"
                                   <?php if ($selectedOption === $displayOpt) echo 'checked'; ?> onchange="autoSaveAnswer()">
                            <span><?php echo htmlspecialchars($optionTexts[$displayOpt]); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </form>

                <div class="d-flex justify-content-between pt-4 border-top">
                    <?php if ($questionIndex > 0): ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="navigateTo('attempt.php?quiz_id=<?php echo $quizId; ?>&q=<?php echo $questionIndex - 1; ?>')">
                        <i class="bi bi-chevron-left"></i> Previous
                    </button>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>

                    <?php if ($questionIndex < count($questionIds) - 1): ?>
                    <button type="button" class="btn btn-primary" onclick="navigateTo('attempt.php?quiz_id=<?php echo $quizId; ?>&q=<?php echo $questionIndex + 1; ?>')">
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-success" onclick="submitQuiz()">
                        <i class="bi bi-check-circle"></i> Submit Quiz
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let timeRemaining = <?php echo max(0, $timeRemaining); ?>;
    const timerDisplay = document.getElementById('timer');
    const timerContainer = document.querySelector('.quiz-timer');
    const ATTEMPT_ID = <?php echo $attempt['attempt_id']; ?>;
    const QUESTION_ID = <?php echo $currentQuestionId; ?>;
    const QUIZ_ID = <?php echo $quizId; ?>;
    let submitting = false;
    let timerInterval = null;

    function saveCurrentAnswer() {
        const selectedOpt = document.querySelector('input[name="selected_option"]:checked');
        if (!selectedOpt) return Promise.resolve();
        const formData = new FormData();
        formData.append('action', 'save_answer');
        formData.append('attempt_id', ATTEMPT_ID);
        formData.append('question_id', QUESTION_ID);
        formData.append('selected_option', selectedOpt.value);
        return fetch('attempt.php', { method: 'POST', body: formData })
            .catch(err => console.error('Save error:', err));
    }

    function doSubmit() {
        if (submitting) return;
        submitting = true;
        clearInterval(timerInterval);
        saveCurrentAnswer().finally(() => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'submit.php';
            const f1 = document.createElement('input');
            f1.type = 'hidden'; f1.name = 'attempt_id'; f1.value = ATTEMPT_ID;
            const f2 = document.createElement('input');
            f2.type = 'hidden'; f2.name = 'quiz_id'; f2.value = QUIZ_ID;
            form.appendChild(f1); form.appendChild(f2);
            document.body.appendChild(form);
            form.submit();
        });
    }

    function submitQuiz() {
        if (submitting) return;
        if (!confirm('Are you sure you want to submit? This cannot be undone.')) return;
        doSubmit();
    }

    function navigateTo(url) {
        if (submitting) return;
        saveCurrentAnswer().finally(() => { window.location.href = url; });
    }

    function updateTimer() {
        if (submitting) return;
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        timerDisplay.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

        if (timeRemaining <= 60) timerContainer.classList.add('timer-critical');

        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            doSubmit(); // auto-submit without confirm when time is up
            return;
        }
        timeRemaining--;
    }

    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);

    // Update label styling on selection
    document.querySelectorAll('.option-label input').forEach(input => {
        input.addEventListener('change', function() {
            document.querySelectorAll('.option-label').forEach(label => label.classList.remove('selected'));
            this.parentElement.classList.add('selected');
            saveCurrentAnswer();
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
