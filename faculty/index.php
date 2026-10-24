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

<div class="page-header">
    <div>
        <h2><i class="bi bi-speedometer2"></i> Faculty Dashboard</h2>
        <p class="text-muted mb-0">Welcome back, <strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
    </div>
   
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="metric-card-modern theme-indigo">
            <div class="metric-header">
                <span class="metric-title">My Subjects</span>
                <div class="metric-icon-box">
                    <i class="bi bi-book-half"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo $subjectsCount; ?></div>
            <div class="metric-trend">
                <i class="bi bi-person-workspace"></i> Assigned Subjects
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card-modern theme-emerald">
            <div class="metric-header">
                <span class="metric-title">Questions</span>
                <div class="metric-icon-box">
                    <i class="bi bi-question-circle-fill"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo $questionsCount; ?></div>
            <div class="metric-trend">
                <i class="bi bi-collection-fill"></i> In Question Bank
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card-modern theme-amber">
            <div class="metric-header">
                <span class="metric-title">Total Quizzes</span>
                <div class="metric-icon-box">
                    <i class="bi bi-file-earmark-check-fill"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo $quizzesCount; ?></div>
            <div class="metric-trend">
                <i class="bi bi-check2-circle"></i> Created Assessments
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card-modern theme-cyan">
            <div class="metric-header">
                <span class="metric-title">Active Now</span>
                <div class="metric-icon-box">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
            </div>
            <div class="metric-value"><?php echo $activeCount; ?></div>
            <div class="metric-trend">
                <i class="bi bi-broadcast"></i> Live Quizzes
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <a href="<?= BASE_PATH ?>/faculty/questions.php" class="faculty-quick-link">
            <div class="fql-icon icon-indigo">
                <i class="bi bi-question-circle-fill"></i>
            </div>
            <div class="fql-content">
                <span class="fql-title">Question Bank</span>
                <span class="fql-sub">Manage & create questions</span>
            </div>
            <i class="bi bi-chevron-right fql-arrow"></i>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= BASE_PATH ?>/faculty/create_quiz.php" class="faculty-quick-link">
            <div class="fql-icon icon-emerald">
                <i class="bi bi-plus-square-fill"></i>
            </div>
            <div class="fql-content">
                <span class="fql-title">Create Quiz</span>
                <span class="fql-sub">Design & schedule new tests</span>
            </div>
            <i class="bi bi-chevron-right fql-arrow"></i>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= BASE_PATH ?>/faculty/results.php" class="faculty-quick-link">
            <div class="fql-icon icon-amber">
                <i class="bi bi-bar-chart-line-fill"></i>
            </div>
            <div class="fql-content">
                <span class="fql-title">View Results</span>
                <span class="fql-sub">Review student performance</span>
            </div>
            <i class="bi bi-chevron-right fql-arrow"></i>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden mb-4">
    <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary fs-5"></i>
            <span class="fw-bold text-dark fs-6">Recent Quizzes Overview</span>
        </div>
        <a href="<?= BASE_PATH ?>/faculty/create_quiz.php" class="btn btn-sm btn-outline-primary">View All Quizzes</a>
    </div>
    <?php if (!empty($recentQuizzes)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Quiz Title</th>
                        <th>Subject</th>
                        <th>Duration</th>
                        <th>Attempts</th>
                        <th>Avg Score</th>
                        <th class="pe-4 text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $nowStr = $pdo->query("SELECT NOW() as now")->fetch()['now'];
                    $now = new DateTime($nowStr);
                    foreach ($recentQuizzes as $quiz): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($quiz['title']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-book me-1 text-primary"></i><?php echo htmlspecialchars($quiz['subject_name']); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted font-monospace"><?php echo $quiz['duration_min']; ?> mins</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo (int)$quiz['attempt_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($quiz['avg_score'] !== null): ?>
                                    <strong class="text-primary"><?php echo number_format($quiz['avg_score'], 1); ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <?php
                                $start = new DateTime($quiz['start_time']);
                                $end   = new DateTime($quiz['end_time']);
                                if ($now < $start) {
                                    echo '<span class="badge bg-info-pale text-info border border-info-subtle px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>Upcoming</span>';
                                } elseif ($now >= $start && $now <= $end) {
                                    echo '<span class="badge bg-success-pale text-success border border-success-subtle px-2 py-1"><i class="bi bi-play-circle-fill me-1"></i>Active</span>';
                                } else {
                                    echo '<span class="badge bg-light text-muted border px-2 py-1">Closed</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state-modern">
            <div class="empty-state-icon-box">
                <i class="bi bi-journal-plus"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">No Quizzes Created Yet</h5>
            <p class="text-muted small mb-4" style="max-width: 440px; margin-inline: auto;">
                Launch your first interactive assessment, configure questions, set timers, and track student scores seamlessly.
            </p>
            <a href="<?= BASE_PATH ?>/faculty/create_quiz.php" class="btn-create-quiz-cta">
                <i class="bi bi-plus-lg"></i>
                <span>Create First Quiz</span>
                <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
