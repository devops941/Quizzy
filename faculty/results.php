<?php
$pageTitle = 'Quiz Results';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('faculty');

$user = currentUser();
$selected_quiz = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$export_csv = isset($_GET['export']) && $_GET['export'] === 'csv';
$error = '';

try {
    $stmt = $pdo->prepare('
        SELECT q.quiz_id, q.title, s.name as subject_name, q.num_questions, q.marks_correct
        FROM quizzes q
        INNER JOIN subjects s ON q.subject_id = s.subject_id
        WHERE s.faculty_id = ?
        ORDER BY q.start_time DESC
    ');
    $stmt->execute([$user['id']]);
    $quizzes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    $quizzes = [];
}

$attempts = [];
$quiz_title = '';
$summary_stats = null;
$question_accuracy = [];

if ($selected_quiz > 0) {
    try {
        $stmt = $pdo->prepare('
            SELECT q.quiz_id, q.title, q.num_questions, q.marks_correct
            FROM quizzes q
            INNER JOIN subjects s ON q.subject_id = s.subject_id
            WHERE q.quiz_id = ? AND s.faculty_id = ?
        ');
        $stmt->execute([$selected_quiz, $user['id']]);
        $quiz = $stmt->fetch();

        if (!$quiz) {
            $error = 'Quiz not found or you do not have permission to view it.';
        } else {
            $quiz_title = $quiz['title'];
            $max_score = $quiz['num_questions'] * $quiz['marks_correct'];

            $stmt = $pdo->prepare('
                SELECT a.attempt_id, a.quiz_id, u.name as student_name, u.email as student_email, u.roll_no, a.started_at, a.submitted_at, a.score
                FROM attempts a
                INNER JOIN users u ON a.student_id = u.user_id
                WHERE a.quiz_id = ? AND a.submitted_at IS NOT NULL
                ORDER BY a.score DESC, a.submitted_at ASC
            ');
            $stmt->execute([$selected_quiz]);
            $attempts = $stmt->fetchAll();

            $total_attempts = count($attempts);
            $average_score = 0;
            $highest_score = 0;
            $lowest_score = 0;
            $pass_count = 0;
            $pass_threshold = ($max_score * 50) / 100;

            if ($total_attempts > 0) {
                $scores = array_map(fn($a) => (float)$a['score'], $attempts);
                $average_score = array_sum($scores) / $total_attempts;
                $highest_score = max($scores);
                $lowest_score = min($scores);
                $pass_count = count(array_filter($scores, fn($s) => $s >= $pass_threshold));
            }

            $summary_stats = [
                'total_attempts' => $total_attempts,
                'average_score' => $average_score,
                'highest_score' => $highest_score,
                'lowest_score' => $lowest_score,
                'pass_rate' => $total_attempts > 0 ? ($pass_count / $total_attempts * 100) : 0,
                'max_score' => $max_score
            ];

            $stmt = $pdo->prepare('
                SELECT q.question_id, q.question_text,
                       SUM(CASE WHEN aa.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                       SUM(CASE WHEN aa.is_correct = 0 AND aa.selected_option IS NOT NULL THEN 1 ELSE 0 END) as wrong_count,
                       SUM(CASE WHEN aa.selected_option IS NULL THEN 1 ELSE 0 END) as skipped_count
                FROM questions q
                LEFT JOIN attempt_answers aa ON q.question_id = aa.question_id
                LEFT JOIN attempts a ON aa.attempt_id = a.attempt_id
                    AND a.quiz_id = ? AND a.submitted_at IS NOT NULL
                WHERE q.subject_id = (SELECT subject_id FROM quizzes WHERE quiz_id = ?)
                GROUP BY q.question_id, q.question_text
                ORDER BY q.question_id ASC
            ');
            $stmt->execute([$selected_quiz, $selected_quiz]);
            $question_accuracy = $stmt->fetchAll();

            if ($export_csv) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="quiz_' . $selected_quiz . '_results.csv"');

                $output = fopen('php://output', 'w');
                fputcsv($output, ['Rank', 'Student Name', 'Roll No', 'Score', 'Max Score', 'Percentage', 'Submitted At']);

                foreach ($attempts as $index => $a) {
                    $rank = $index + 1;
                    $pct = $max_score > 0 ? round(($a['score'] / $max_score) * 100, 2) : 0;
                    fputcsv($output, [
                        $rank,
                        $a['student_name'],
                        $a['roll_no'] ?? 'N/A',
                        $a['score'],
                        $max_score,
                        $pct . '%',
                        $a['submitted_at']
                    ]);
                }

                fclose($output);
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary-pale text-primary text-uppercase px-2 py-1 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Faculty Portal</span>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Analytics & Reporting</span>
        </div>
        <h2 class="mb-1 fw-800 text-dark d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
            <i class="bi bi-bar-chart-fill text-primary"></i> Quiz Performance & Results
        </h2>
        <p class="text-muted small mb-0">Inspect evaluation metrics, examinee scores, item difficulty, and export gradebooks.</p>
    </div>
    <?php if ($selected_quiz > 0 && !empty($attempts)): ?>
        <div>
            <a href="?quiz_id=<?php echo $selected_quiz; ?>&export=csv" class="btn btn-success shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Gradebook (CSV)
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Quiz Selector Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <div class="row align-items-center g-3">
            <div class="col-md-3">
                <label for="quizSelect" class="form-label fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-funnel-fill text-primary"></i> Select Examination:
                </label>
            </div>
            <div class="col-md-9">
                <select class="form-select form-select-lg" id="quizSelect" onchange="window.location.href = '?quiz_id=' + this.value;">
                    <option value="0">Choose an active or completed quiz to view results...</option>
                    <?php foreach ($quizzes as $q): ?>
                        <option value="<?php echo $q['quiz_id']; ?>" <?php echo $selected_quiz === $q['quiz_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($q['subject_name'] . ' — ' . $q['title']); ?> (<?php echo $q['num_questions']; ?> questions)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<?php if ($selected_quiz > 0 && $summary_stats): ?>

    <!-- 4 Modern Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-indigo">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Submissions</div>
                        <div class="stat-value"><?php echo $summary_stats['total_attempts']; ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-emerald">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Average Score</div>
                        <div class="stat-value" style="font-size: 1.85rem;">
                            <?php echo number_format($summary_stats['average_score'], 1); ?>
                            <span style="font-size: 1rem; opacity: 0.8;">/ <?php echo number_format($summary_stats['max_score'], 0); ?></span>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-cyan">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Passing Rate (50%+)</div>
                        <div class="stat-value"><?php echo number_format($summary_stats['pass_rate'], 1); ?>%</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-amber">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Score Range</div>
                        <div class="stat-value" style="font-size: 1.85rem;">
                            <?php if ($summary_stats['total_attempts'] > 0): ?>
                                <?php echo number_format($summary_stats['highest_score'], 1); ?>
                                <span style="font-size: 1rem; opacity: 0.8;">high / <?php echo number_format($summary_stats['lowest_score'], 1); ?> low</span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Leaderboard Card -->
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-trophy-fill text-warning fs-5"></i>
                <span class="fw-bold text-dark fs-6">Examinee Leaderboard & Scores</span>
                <span class="badge bg-light text-dark border"><?php echo count($attempts); ?> Students</span>
            </div>
            <span class="text-muted small">Sorted by highest score</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 80px;">Rank</th>
                        <th>Student Name</th>
                        <th>Roll / ID Number</th>
                        <th>Earned Score</th>
                        <th>Percentage</th>
                        <th>Submitted At</th>
                        <th class="text-end pe-4" style="width: 130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($attempts)): ?>
                        <?php foreach ($attempts as $index => $a):
                            $rank = $index + 1;
                            $pct = $summary_stats['max_score'] > 0 ? ($a['score'] / $summary_stats['max_score']) * 100 : 0;
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <?php if ($rank === 1): ?>
                                        <span class="badge bg-warning text-dark fw-bold px-2 py-1 shadow-sm" style="border: 1px solid #d97706;">
                                            <i class="bi bi-trophy-fill text-dark me-1"></i> 1st
                                        </span>
                                    <?php elseif ($rank === 2): ?>
                                        <span class="badge bg-secondary text-white fw-bold px-2 py-1 shadow-sm">
                                            <i class="bi bi-award-fill me-1"></i> 2nd
                                        </span>
                                    <?php elseif ($rank === 3): ?>
                                        <span class="badge fw-bold px-2 py-1 shadow-sm" style="background: #b45309; color: #fff;">
                                            <i class="bi bi-award me-1"></i> 3rd
                                        </span>
                                    <?php else: ?>
                                        <span class="fw-bold text-muted ps-2">#<?php echo $rank; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-pale text-primary fw-bold" 
                                             style="width: 32px; height: 32px; font-size: 0.8rem; flex-shrink: 0;">
                                            <?php echo strtoupper(substr($a['student_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($a['student_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($a['student_email'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($a['roll_no'])): ?>
                                        <span class="badge bg-light text-dark border font-monospace">#<?php echo htmlspecialchars($a['roll_no']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-primary fs-6"><?php echo number_format($a['score'], 1); ?></strong>
                                    <span class="text-muted small">/ <?php echo number_format($summary_stats['max_score'], 1); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $pct >= 80 ? 'bg-success-pale text-success' : ($pct >= 50 ? 'bg-info-pale text-info' : 'bg-danger-pale text-danger'); ?> px-2 py-1 border">
                                        <?php echo number_format($pct, 1); ?>%
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($a['submitted_at'])); ?>
                                    </small>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_PATH ?>/faculty/attempt_detail.php?attempt=<?php echo $a['attempt_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View Full Question Breakdown">
                                        <i class="bi bi-eye-fill me-1"></i> Review
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <div class="empty-state py-3">
                                    <i class="bi bi-clock-history mb-2" style="font-size: 2.5rem; color: #94a3b8;"></i>
                                    <h6 class="fw-bold text-dark">No Student Submissions Yet</h6>
                                    <p class="text-muted small mb-0">Results will automatically appear here as students complete the exam.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Question Accuracy Breakdown Card -->
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-pie-chart-fill text-primary"></i>
                <span class="fw-bold text-dark">Question-by-Question Difficulty & Accuracy</span>
            </div>
            <span class="text-muted small">Evaluated across all submissions</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Question Prompt</th>
                        <th class="text-center" style="width: 100px;">Correct</th>
                        <th class="text-center" style="width: 100px;">Incorrect</th>
                        <th class="text-center" style="width: 100px;">Skipped</th>
                        <th class="pe-4 text-center" style="width: 160px;">Accuracy</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($question_accuracy)): ?>
                        <?php foreach ($question_accuracy as $qa):
                            $total_responses = ($qa['correct_count'] + $qa['wrong_count'] + $qa['skipped_count']);
                            $accuracy = $total_responses > 0 ? ($qa['correct_count'] / $total_responses * 100) : 0;
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-medium text-dark small">
                                        <?php echo htmlspecialchars($qa['question_text']); ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success-pale text-success border border-success-subtle px-2 py-1">
                                        <?php echo (int)$qa['correct_count']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger-pale text-danger border border-danger-subtle px-2 py-1">
                                        <?php echo (int)$qa['wrong_count']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-muted border px-2 py-1">
                                        <?php echo (int)$qa['skipped_count']; ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-center">
                                    <div class="d-flex align-items-center gap-2 justify-content-center">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar <?php echo $accuracy >= 70 ? 'bg-success' : ($accuracy >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                 role="progressbar" style="width: <?php echo $accuracy; ?>%;"></div>
                                        </div>
                                        <span class="fw-bold small" style="min-width: 45px;"><?php echo number_format($accuracy, 0); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No question accuracy data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($selected_quiz === 0): ?>
    <!-- Friendly Landing State when no quiz is chosen -->
    <div class="card border-0 shadow-sm text-center py-5 px-4 mb-4">
        <div class="py-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-pale text-primary mb-3" style="width: 70px; height: 70px; font-size: 2rem;">
                <i class="bi bi-bar-chart-steps"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">Select an Examination to View Analytics</h4>
            <p class="text-muted mx-auto" style="max-width: 480px;">
                Choose any of your created quizzes from the dropdown above to inspect real-time student scores, leaderboard rankings, and question-level difficulty breakdowns.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
