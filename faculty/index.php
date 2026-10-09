<?php
$pageTitle = 'Faculty Dashboard';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('faculty');

$user = currentUser();
$userId = $user['id'];

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM subjects WHERE faculty_id = ?');
    $stmt->execute([$userId]);
    $subjectsCount = $stmt->fetch()['count'];

    $stmt = $pdo->prepare('SELECT COUNT(q.question_id) as count FROM questions q INNER JOIN subjects s ON q.subject_id = s.subject_id WHERE s.faculty_id = ?');
    $stmt->execute([$userId]);
    $questionsCount = $stmt->fetch()['count'];

    $stmt = $pdo->prepare('SELECT COUNT(q.quiz_id) as count FROM quizzes q INNER JOIN subjects s ON q.subject_id = s.subject_id WHERE s.faculty_id = ?');
    $stmt->execute([$userId]);
    $quizzesCount = $stmt->fetch()['count'];

    $stmt = $pdo->prepare('SELECT COUNT(q.quiz_id) as count FROM quizzes q INNER JOIN subjects s ON q.subject_id = s.subject_id WHERE s.faculty_id = ? AND q.start_time > NOW()');
    $stmt->execute([$userId]);
    $upcomingCount = $stmt->fetch()['count'];

    $stmt = $pdo->prepare('SELECT COUNT(q.quiz_id) as count FROM quizzes q INNER JOIN subjects s ON q.subject_id = s.subject_id WHERE s.faculty_id = ? AND q.start_time <= NOW() AND q.end_time >= NOW()');
    $stmt->execute([$userId]);
    $activeCount = $stmt->fetch()['count'];

    $stmt = $pdo->prepare('
        SELECT q.quiz_id, q.title, s.name as subject_name, q.start_time, q.end_time, q.duration_min,
               COUNT(a.attempt_id) as attempt_count, ROUND(AVG(a.score), 2) as avg_score
        FROM quizzes q
        INNER JOIN subjects s ON q.subject_id = s.subject_id
        LEFT JOIN attempts a ON q.quiz_id = a.quiz_id
        WHERE s.faculty_id = ?
        AND q.end_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY q.quiz_id, q.title, s.name, q.start_time, q.end_time, q.duration_min
        ORDER BY q.start_time DESC
        LIMIT 10
    ');
    $stmt->execute([$userId]);
    $recentQuizzes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    $subjectsCount = 0;
    $questionsCount = 0;
    $quizzesCount = 0;
    $upcomingCount = 0;
    $activeCount = 0;
    $recentQuizzes = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-5">
    <div class="col-lg-12">
        <h2 class="mb-1">
            <i class="bi bi-speedometer2"></i> Faculty Dashboard
        </h2>
        <p class="text-muted">Welcome, <?php echo htmlspecialchars($user['name']); ?></p>
    </div>
</div>

<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-book" style="font-size: 2.5rem; color: #4f46e5;"></i>
                </div>
                <h3 class="card-title mb-0"><?php echo $subjectsCount; ?></h3>
                <p class="text-muted small mt-2">My Subjects</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-question-circle" style="font-size: 2.5rem; color: #10b981;"></i>
                </div>
                <h3 class="card-title mb-0"><?php echo $questionsCount; ?></h3>
                <p class="text-muted small mt-2">Total Questions</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-files" style="font-size: 2.5rem; color: #f59e0b;"></i>
                </div>
                <h3 class="card-title mb-0"><?php echo $quizzesCount; ?></h3>
                <p class="text-muted small mt-2">Total Quizzes</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-play-circle" style="font-size: 2.5rem; color: #ef4444;"></i>
                </div>
                <h3 class="card-title mb-0"><?php echo $activeCount; ?></h3>
                <p class="text-muted small mt-2">Active Now</p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 g-3">
    <div class="col-md-4">
        <a href="/faculty/questions.php" class="card border-0 shadow-sm text-decoration-none text-dark h-100 quick-link-card">
            <div class="card-body text-center py-4">
                <i class="bi bi-plus-circle" style="font-size: 2rem; color: #4f46e5; margin-bottom: 1rem; display: block;"></i>
                <h5 class="card-title">Add Questions</h5>
                <p class="text-muted small">Create or manage your question bank</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="/faculty/create_quiz.php" class="card border-0 shadow-sm text-decoration-none text-dark h-100 quick-link-card">
            <div class="card-body text-center py-4">
                <i class="bi bi-pencil-square" style="font-size: 2rem; color: #10b981; margin-bottom: 1rem; display: block;"></i>
                <h5 class="card-title">Create Quiz</h5>
                <p class="text-muted small">Design and schedule new quizzes</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="/faculty/results.php" class="card border-0 shadow-sm text-decoration-none text-dark h-100 quick-link-card">
            <div class="card-body text-center py-4">
                <i class="bi bi-graph-up" style="font-size: 2rem; color: #f59e0b; margin-bottom: 1rem; display: block;"></i>
                <h5 class="card-title">View Results</h5>
                <p class="text-muted small">Check student performance and scores</p>
            </div>
        </a>
    </div>
</div>

<div class="row mt-5">
    <div class="col-lg-12">
        <h4 class="mb-3">
            <i class="bi bi-clock-history"></i> Recent Quizzes
        </h4>
        <?php if (!empty($recentQuizzes)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Quiz Title</th>
                            <th>Subject</th>
                            <th>Duration</th>
                            <th>Attempts</th>
                            <th>Avg Score</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentQuizzes as $quiz): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($quiz['title']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($quiz['subject_name']); ?></td>
                                <td><?php echo $quiz['duration_min']; ?> min</td>
                                <td><?php echo (int)$quiz['attempt_count']; ?></td>
                                <td>
                                    <?php
                                    if ($quiz['avg_score'] !== null) {
                                        echo '<strong>' . number_format($quiz['avg_score'], 2) . '</strong>';
                                    } else {
                                        echo '<span class="text-muted">—</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $nowStr = $pdo->query("SELECT NOW() as now")->fetch()['now'];
                                    $now = new DateTime($nowStr);
                                    $start = new DateTime($quiz['start_time']);
                                    $end = new DateTime($quiz['end_time']);

                                    if ($now < $start) {
                                        echo '<span class="badge bg-info">Upcoming</span>';
                                    } elseif ($now >= $start && $now <= $end) {
                                        echo '<span class="badge bg-danger">Active</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">Closed</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No quizzes created yet. <a href="/faculty/create_quiz.php">Create your first quiz</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.quick-link-card {
    transition: all 0.3s ease;
}

.quick-link-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 24px rgba(79, 70, 229, 0.15) !important;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
