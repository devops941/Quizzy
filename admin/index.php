<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('admin');

$pageTitle = 'Admin Dashboard';
require_once __DIR__.'/../includes/header.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'faculty'");
    $totalFaculty = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
    $totalStudents = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM subjects");
    $totalSubjects = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM quizzes");
    $totalQuizzes = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM attempts");
    $totalAttempts = $stmt->fetch()['total'];

    $stmt = $pdo->query("
        SELECT a.attempt_id, u.name as student_name, q.title as quiz_title, a.score, a.submitted_at
        FROM attempts a
        JOIN users u ON a.student_id = u.user_id
        JOIN quizzes q ON a.quiz_id = q.quiz_id
        WHERE a.submitted_at IS NOT NULL
        ORDER BY a.submitted_at DESC
        LIMIT 5
    ");
    $recentAttempts = $stmt->fetchAll();

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error loading dashboard: ' . $e->getMessage()];
    $totalUsers = $totalFaculty = $totalStudents = $totalSubjects = $totalQuizzes = $totalAttempts = 0;
    $recentAttempts = [];
}
?>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #4f46e5;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Total Users</p>
                        <h3 class="mb-0"><?php echo $totalUsers; ?></h3>
                    </div>
                    <i class="bi bi-people-fill fs-4" style="color: #4f46e5;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #7c3aed;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Faculty Members</p>
                        <h3 class="mb-0"><?php echo $totalFaculty; ?></h3>
                    </div>
                    <i class="bi bi-person-badge fs-4" style="color: #7c3aed;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #0891b2;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Students</p>
                        <h3 class="mb-0"><?php echo $totalStudents; ?></h3>
                    </div>
                    <i class="bi bi-mortarboard-fill fs-4" style="color: #0891b2;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #059669;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Subjects</p>
                        <h3 class="mb-0"><?php echo $totalSubjects; ?></h3>
                    </div>
                    <i class="bi bi-book-fill fs-4" style="color: #059669;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #d97706;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Quizzes</p>
                        <h3 class="mb-0"><?php echo $totalQuizzes; ?></h3>
                    </div>
                    <i class="bi bi-file-earmark-check-fill fs-4" style="color: #d97706;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100 border-0 shadow-sm" style="border-left: 4px solid #dc2626;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Quiz Attempts</p>
                        <h3 class="mb-0"><?php echo $totalAttempts; ?></h3>
                    </div>
                    <i class="bi bi-clock-history fs-4" style="color: #dc2626;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0">Recent Quiz Attempts</h5>
    </div>
    <div class="card-body p-0">
        <?php if (count($recentAttempts) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student Name</th>
                            <th>Quiz Title</th>
                            <th>Score</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAttempts as $attempt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                                <td>
                                    <?php if ($attempt['score'] !== null): ?>
                                        <span class="badge bg-success"><?php echo number_format($attempt['score'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($attempt['submitted_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-4 text-center text-muted">
                <p>No quiz attempts yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
