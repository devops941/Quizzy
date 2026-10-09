<?php
$pageTitle = 'Quiz Results';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('faculty');

$user = currentUser();
$selected_quiz = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$export_csv = isset($_GET['export']) && $_GET['export'] === 'csv';

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
                SELECT a.attempt_id, a.quiz_id, u.name as student_name, u.roll_no, a.started_at, a.submitted_at, a.score
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
                WHERE a.quiz_id = ? AND a.submitted_at IS NOT NULL
                GROUP BY q.question_id, q.question_text
                ORDER BY q.question_id ASC
            ');
            $stmt->execute([$selected_quiz]);
            $question_accuracy = $stmt->fetchAll();

            if ($export_csv) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="quiz_results_' . date('Y-m-d_H-i-s') . '.csv"');

                $output = fopen('php://output', 'w');
                fputcsv($output, ['Name', 'Roll No.', 'Score', 'Submitted At', 'Time Taken (minutes)']);

                foreach ($attempts as $attempt) {
                    $start = new DateTime($attempt['started_at']);
                    $end = new DateTime($attempt['submitted_at']);
                    $time_taken = $start->diff($end)->i + ($start->diff($end)->h * 60);

                    fputcsv($output, [
                        $attempt['student_name'],
                        $attempt['roll_no'] ?? 'N/A',
                        number_format($attempt['score'], 2),
                        date('M d, Y H:i', strtotime($attempt['submitted_at'])),
                        $time_taken
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

<div class="row">
    <div class="col-lg-12">
        <h2 class="mb-4">
            <i class="bi bi-bar-chart"></i> Quiz Results
        </h2>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <label class="form-label">Select Quiz</label>
        <select class="form-select" id="quizSelect" onchange="window.location.href = '?quiz_id=' + this.value;">
            <option value="0">Choose a quiz...</option>
            <?php foreach ($quizzes as $q): ?>
                <option value="<?php echo $q['quiz_id']; ?>" <?php echo $selected_quiz === $q['quiz_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($q['subject_name'] . ' - ' . $q['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($selected_quiz > 0 && $summary_stats): ?>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Attempts</h6>
                    <h3 class="text-primary"><?php echo $summary_stats['total_attempts']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Average Score</h6>
                    <h3 class="text-success"><?php echo number_format($summary_stats['average_score'], 2); ?> / <?php echo number_format($summary_stats['max_score'], 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Pass Rate (50%)</h6>
                    <h3 class="text-info"><?php echo number_format($summary_stats['pass_rate'], 1); ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Score Range</h6>
                    <?php if ($summary_stats['total_attempts'] > 0): ?>
                    <h3 class="text-warning"><?php echo number_format($summary_stats['highest_score'], 2); ?> / <?php echo number_format($summary_stats['lowest_score'], 2); ?></h3>
                    <small class="text-muted">High / Low</small>
                    <?php else: ?>
                    <h3 class="text-muted">—</h3>
                    <small class="text-muted">No attempts yet</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-12">
            <a href="?quiz_id=<?php echo $selected_quiz; ?>&export=csv" class="btn btn-success mb-3">
                <i class="bi bi-download"></i> Export to CSV
            </a>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-trophy"></i> Student Leaderboard
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">Rank</th>
                                <th>Student Name</th>
                                <th>Roll No.</th>
                                <th>Score</th>
                                <th>Submitted At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attempts)): ?>
                                <?php foreach ($attempts as $index => $a):
                                    $rank = $index + 1;
                                    $row_class = ($rank <= 3) ? ' table-active' : '';
                                ?>
                                    <tr<?php echo $row_class; ?>>
                                        <td>
                                            <?php if ($rank === 1): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> 1st</span>
                                            <?php elseif ($rank === 2): ?>
                                                <span class="badge bg-secondary"><i class="bi bi-star-fill"></i> 2nd</span>
                                            <?php elseif ($rank === 3): ?>
                                                <span class="badge bg-warning"><i class="bi bi-star-fill"></i> 3rd</span>
                                            <?php else: ?>
                                                <?php echo $rank; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($a['student_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($a['roll_no'] ?? 'N/A'); ?></td>
                                        <td>
                                            <strong class="text-primary"><?php echo number_format($a['score'], 2); ?></strong> / <?php echo number_format($summary_stats['max_score'], 2); ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y H:i', strtotime($a['submitted_at'])); ?></small>
                                        </td>
                                        <td>
                                            <a href="/faculty/attempt_detail.php?attempt=<?php echo $a['attempt_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No completed attempts yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-question-circle"></i> Question-wise Analysis
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Question</th>
                                <th class="text-center" style="width: 100px;">Correct</th>
                                <th class="text-center" style="width: 100px;">Wrong</th>
                                <th class="text-center" style="width: 100px;">Skipped</th>
                                <th class="text-center" style="width: 120px;">Accuracy</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($question_accuracy)): ?>
                                <?php foreach ($question_accuracy as $qa):
                                    $total_responses = ($qa['correct_count'] + $qa['wrong_count'] + $qa['skipped_count']);
                                    $accuracy = $total_responses > 0 ? ($qa['correct_count'] / $total_responses * 100) : 0;
                                    $text_length = strlen($qa['question_text']);
                                    $display_text = $text_length > 80 ? substr($qa['question_text'], 0, 77) . '...' : $qa['question_text'];
                                ?>
                                    <tr>
                                        <td title="<?php echo htmlspecialchars($qa['question_text']); ?>">
                                            <small><?php echo htmlspecialchars($display_text); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?php echo $qa['correct_count']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?php echo $qa['wrong_count']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo $qa['skipped_count']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $accuracy; ?>%" aria-valuenow="<?php echo $accuracy; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <small><?php echo number_format($accuracy, 1); ?>%</small>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No question data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($selected_quiz > 0 && !$summary_stats): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle"></i> No completed attempts for this quiz yet. Results will appear once students submit their attempts.
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
