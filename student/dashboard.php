<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('student');

$userId = $_SESSION['user']['id'];
$pageTitle = 'My Quizzes';

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

require_once __DIR__.'/../includes/header.php';
?>

<!-- Student Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary-pale text-primary text-uppercase px-2 py-1 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Student Portal</span>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Academic Assessments</span>
        </div>
        <h2 class="mb-1 fw-800 text-dark d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
            <i class="bi bi-grid-1x2-fill text-primary"></i> My Quizzes & Examinations
        </h2>
        <p class="text-muted small mb-0">View scheduled tests, start active examinations, and review your grading history.</p>
    </div>
</div>

<!-- 3 Student Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-indigo">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Active & Ready to Take</div>
                    <div class="stat-value"><?php echo count($availableQuizzes); ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-lightning-charge-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-emerald">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Completed Tests</div>
                    <div class="stat-value"><?php echo count($pastAttempts); ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-patch-check-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-violet">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Upcoming Scheduled</div>
                    <div class="stat-value"><?php echo count($upcomingQuizzes); ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Section 1: Available Quizzes -->
<div class="card border-0 shadow-sm overflow-hidden mb-5">
    <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-lightning-charge-fill text-warning fs-5"></i>
            <span class="fw-bold text-dark fs-6">Available Quizzes — Ready to Start</span>
            <span class="badge bg-primary-pale text-primary"><?php echo count($availableQuizzes); ?> Live</span>
        </div>
        <span class="text-muted small">Auto-timed with live submission</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Quiz Title & Subject</th>
                    <th>Questions</th>
                    <th>Allowed Duration</th>
                    <th>Marking Scheme</th>
                    <th>Expires At</th>
                    <th class="text-end pe-4" style="width: 140px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($availableQuizzes)): ?>
                    <?php foreach ($availableQuizzes as $quiz): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-pale text-primary fw-bold" 
                                         style="width: 40px; height: 40px; font-size: 1.1rem; flex-shrink: 0;">
                                        <i class="bi bi-journal-text"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($quiz['title']); ?></div>
                                        <small class="text-muted"><i class="bi bi-book me-1"></i><?php echo htmlspecialchars($quiz['subject_name']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo (int)$quiz['num_questions']; ?> Questions
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">
                                    <i class="bi bi-clock me-1 text-primary"></i><?php echo (int)$quiz['duration_min']; ?> mins
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success-pale text-success border border-success-subtle">
                                    +<?php echo number_format($quiz['marks_correct'], 1); ?>
                                </span>
                                <?php if ($quiz['marks_negative'] > 0): ?>
                                    <span class="badge bg-danger-pale text-danger border border-danger-subtle ms-1">
                                        -<?php echo number_format($quiz['marks_negative'], 1); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted font-monospace">
                                    <?php echo date('M d, h:i A', strtotime($quiz['end_time'])); ?>
                                </small>
                            </td>
                            <td class="text-end pe-4">
                                <a href="attempt.php?quiz_id=<?php echo (int)$quiz['quiz_id']; ?>" class="btn btn-sm btn-primary shadow-sm px-3">
                                    <i class="bi bi-play-fill me-1"></i> Start Exam
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="empty-state py-3">
                                <i class="bi bi-check2-circle mb-2" style="font-size: 2.5rem; color: #10b981;"></i>
                                <h6 class="fw-bold text-dark">All Caught Up!</h6>
                                <p class="text-muted small mb-0">No active quizzes are waiting for you right now.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Section 2: Completed Quizzes -->
<?php if (!empty($pastAttempts)): ?>
<div class="card border-0 shadow-sm overflow-hidden mb-5">
    <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary fs-5"></i>
            <span class="fw-bold text-dark fs-6">Completed Quizzes & Scorecards</span>
            <span class="badge bg-light text-dark border"><?php echo count($pastAttempts); ?> Attempts</span>
        </div>
        <span class="text-muted small">Graded results</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Quiz Title & Subject</th>
                    <th>Submitted On</th>
                    <th>Final Score</th>
                    <th>Accuracy Percentage</th>
                    <th class="text-end pe-4" style="width: 140px;">Scorecard</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pastAttempts as $attempt):
                    $maxScore = (int)$attempt['num_questions'] * $attempt['marks_correct'];
                    $percentage = $maxScore > 0 ? ($attempt['score'] / $maxScore) * 100 : 0;
                    $badgeClass = $percentage >= 60 ? 'bg-success-pale text-success' : 'bg-danger-pale text-danger';
                ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($attempt['title']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($attempt['subject_name']); ?></small>
                        </td>
                        <td>
                            <small class="text-muted font-monospace">
                                <i class="bi bi-calendar-check me-1"></i><?php echo date('M d, Y h:i A', strtotime($attempt['submitted_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <strong class="text-primary fs-6"><?php echo number_format($attempt['score'], 1); ?></strong>
                            <span class="text-muted small">/ <?php echo number_format($maxScore, 1); ?></span>
                        </td>
                        <td>
                            <span class="badge <?php echo $badgeClass; ?> border px-2 py-1">
                                <?php echo number_format($percentage, 1); ?>%
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <a href="result.php?attempt_id=<?php echo (int)$attempt['attempt_id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-bar-chart me-1"></i> View Result
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Section 3: Upcoming Quizzes -->
<?php if (!empty($upcomingQuizzes)): ?>
<div class="card border-0 shadow-sm overflow-hidden mb-4">
    <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar3 text-info fs-5"></i>
            <span class="fw-bold text-dark fs-6">Upcoming Scheduled Quizzes</span>
        </div>
        <span class="text-muted small">Opens at scheduled start time</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Quiz Title</th>
                    <th>Subject</th>
                    <th>Questions</th>
                    <th>Duration</th>
                    <th>Scheduled Start Time</th>
                    <th class="text-end pe-4">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcomingQuizzes as $upcoming): ?>
                    <tr>
                        <td class="ps-4"><strong><?php echo htmlspecialchars($upcoming['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($upcoming['subject_name']); ?></td>
                        <td><?php echo (int)$upcoming['num_questions']; ?> Questions</td>
                        <td><?php echo (int)$upcoming['duration_min']; ?> mins</td>
                        <td>
                            <small class="text-primary font-monospace fw-bold">
                                <i class="bi bi-alarm me-1"></i><?php echo date('M d, Y h:i A', strtotime($upcoming['start_time'])); ?>
                            </small>
                        </td>
                        <td class="text-end pe-4">
                            <span class="badge bg-light text-muted border">
                                <i class="bi bi-hourglass-split me-1"></i> Locked
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
