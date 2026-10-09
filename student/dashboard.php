<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$pageTitle = 'My Quizzes';
require_once __DIR__.'/../includes/header.php';

try {
    // Available quizzes (active, not yet attempted by this student)
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, s.name as subject_name, q.num_questions,
               q.duration_min, q.start_time, q.end_time, q.marks_correct, q.marks_negative
        FROM quizzes q
        JOIN subjects s ON q.subject_id = s.subject_id
        WHERE q.start_time <= NOW() AND q.end_time > NOW()
        AND q.quiz_id NOT IN (
            SELECT quiz_id FROM attempts WHERE student_id = ?
        )
        ORDER BY q.start_time ASC
    ");
    $stmt->execute([$userId]);
    $availableQuizzes = $stmt->fetchAll();

    // Past attempts (submitted quizzes)
    $stmt = $pdo->prepare("
        SELECT a.attempt_id, q.quiz_id, q.title, s.name as subject_name,
               a.started_at, a.submitted_at, a.score, q.num_questions,
               q.marks_correct, q.marks_negative
        FROM attempts a
        JOIN quizzes q ON a.quiz_id = q.quiz_id
        JOIN subjects s ON q.subject_id = s.subject_id
        WHERE a.student_id = ? AND a.submitted_at IS NOT NULL
        ORDER BY a.submitted_at DESC
    ");
    $stmt->execute([$userId]);
    $pastAttempts = $stmt->fetchAll();

    // Upcoming quizzes
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, s.name as subject_name, q.num_questions,
               q.duration_min, q.start_time, q.end_time
        FROM quizzes q
        JOIN subjects s ON q.subject_id = s.subject_id
        WHERE q.start_time > NOW()
        ORDER BY q.start_time ASC
    ");
    $stmt->execute([]);
    $upcomingQuizzes = $stmt->fetchAll();

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error loading quizzes: ' . htmlspecialchars($e->getMessage())];
    $availableQuizzes = [];
    $pastAttempts = [];
    $upcomingQuizzes = [];
}
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">My Quizzes</h2>
    </div>
</div>

<?php if (!empty($availableQuizzes)): ?>
<div class="row mb-5">
    <div class="col-md-12">
        <h4 class="mb-3" style="color: #4f46e5;">Available Quizzes</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Quiz Title</th>
                        <th>Subject</th>
                        <th>Questions</th>
                        <th>Duration</th>
                        <th>Marks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($availableQuizzes as $quiz): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($quiz['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($quiz['subject_name']); ?></td>
                        <td><?php echo (int)$quiz['num_questions']; ?></td>
                        <td><?php echo (int)$quiz['duration_min']; ?> mins</td>
                        <td>
                            +<?php echo number_format($quiz['marks_correct'], 2); ?>
                            <?php if ($quiz['marks_negative'] > 0): ?>
                                / -<?php echo number_format($quiz['marks_negative'], 2); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="attempt.php?quiz_id=<?php echo (int)$quiz['quiz_id']; ?>" class="btn btn-sm btn-primary">Start</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info mb-5">
    <i class="bi bi-info-circle me-2"></i>No quizzes available at this moment.
</div>
<?php endif; ?>

<?php if (!empty($pastAttempts)): ?>
<div class="row mb-5">
    <div class="col-md-12">
        <h4 class="mb-3" style="color: #4f46e5;">Completed Quizzes</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Quiz Title</th>
                        <th>Subject</th>
                        <th>Attempted On</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pastAttempts as $attempt): ?>
                    <?php
                        $maxScore = (int)$attempt['num_questions'] * $attempt['marks_correct'];
                        $percentage = $maxScore > 0 ? ($attempt['score'] / $maxScore) * 100 : 0;
                        $badgeClass = $percentage >= 60 ? 'bg-success' : 'bg-danger';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($attempt['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($attempt['subject_name']); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($attempt['submitted_at'])); ?></td>
                        <td><?php echo number_format($attempt['score'], 2); ?> / <?php echo number_format($maxScore, 2); ?></td>
                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo number_format($percentage, 1); ?>%</span></td>
                        <td>
                            <a href="result.php?attempt_id=<?php echo (int)$attempt['attempt_id']; ?>" class="btn btn-sm btn-outline-primary">Review</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($upcomingQuizzes)): ?>
<div class="row mb-5">
    <div class="col-md-12">
        <h4 class="mb-3" style="color: #4f46e5;">Upcoming Quizzes</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Quiz Title</th>
                        <th>Subject</th>
                        <th>Questions</th>
                        <th>Duration</th>
                        <th>Starts</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingQuizzes as $quiz): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($quiz['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($quiz['subject_name']); ?></td>
                        <td><?php echo (int)$quiz['num_questions']; ?></td>
                        <td><?php echo (int)$quiz['duration_min']; ?> mins</td>
                        <td><?php echo date('M d, Y h:i A', strtotime($quiz['start_time'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
