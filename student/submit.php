<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user']['id'];
$attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;

if ($attemptId <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid quiz attempt.'];
    header('Location: dashboard.php');
    exit;
}

try {
    // Get attempt and quiz details
    $stmt = $pdo->prepare("
        SELECT a.attempt_id, a.quiz_id, a.started_at, a.submitted_at, a.option_maps, a.question_order,
               q.duration_min, q.marks_correct, q.marks_negative
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

    // Check if already submitted
    if ($attempt['submitted_at'] !== null) {
        $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'This quiz has already been submitted.'];
        header('Location: result.php?attempt_id=' . $attemptId);
        exit;
    }

    // Server-side timer validation (grace period: 30 seconds)
    $nowStr = $pdo->query("SELECT NOW() as now")->fetch()['now'];
    $now = new DateTime($nowStr);
    $startedAt = new DateTime($attempt['started_at']);
    $elapsedSeconds = $now->getTimestamp() - $startedAt->getTimestamp();
    $allowedSeconds = ($attempt['duration_min'] * 60) + 30;
    $isLate = $elapsedSeconds > $allowedSeconds;

    // Get question IDs and option maps
    $questionIds = json_decode($attempt['question_order'], true);
    $optionMaps = json_decode($attempt['option_maps'], true);

    // Calculate score
    $score = 0;

    foreach ($questionIds as $qid) {
        // Get student's selected option
        $stmt = $pdo->prepare("
            SELECT selected_option FROM attempt_answers
            WHERE attempt_id = ? AND question_id = ?
        ");
        $stmt->execute([$attemptId, $qid]);
        $answerRow = $stmt->fetch();
        $selectedDisplayOption = $answerRow ? $answerRow['selected_option'] : null;

        // Get correct answer
        $stmt = $pdo->prepare("
            SELECT correct_option FROM questions WHERE question_id = ?
        ");
        $stmt->execute([$qid]);
        $questionRow = $stmt->fetch();
        $correctOption = $questionRow['correct_option'];

        // Translate selected option back to original option using option_maps
        $selectedOriginalOption = null;
        if ($selectedDisplayOption && isset($optionMaps[$qid][$selectedDisplayOption])) {
            $selectedOriginalOption = $optionMaps[$qid][$selectedDisplayOption];
        }

        // Check correctness
        $isCorrect = ($selectedOriginalOption === $correctOption) ? 1 : 0;

        // Update attempt_answers with correctness and timestamp
        $stmt = $pdo->prepare("
            UPDATE attempt_answers
            SET is_correct = ?
            WHERE attempt_id = ? AND question_id = ?
        ");
        $stmt->execute([$isCorrect, $attemptId, $qid]);

        // Calculate score
        if ($isCorrect) {
            $score += $attempt['marks_correct'];
        } elseif ($selectedDisplayOption !== null) {
            $score -= $attempt['marks_negative'];
        }
    }

    // Ensure score doesn't go negative
    $score = max(0, $score);

    // Update attempt with submission
    $stmt = $pdo->prepare("
        UPDATE attempts
        SET submitted_at = ?, score = ?
        WHERE attempt_id = ?
    ");
    $stmt->execute([$nowStr, $score, $attemptId]);

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Quiz submitted successfully!'];
    header('Location: result.php?attempt_id=' . $attemptId);
    exit;

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error processing submission: ' . htmlspecialchars($e->getMessage())];
    error_log('Submit attempt error: ' . $e->getMessage());
    header('Location: dashboard.php');
    exit;
}
