<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('admin');

$pageTitle = 'Admin Dashboard';
require_once __DIR__.'/../includes/header.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'faculty'");
    $totalFaculty = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
    $totalStudents = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
    $totalAdmins = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM subjects");
    $totalSubjects = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM quizzes");
    $totalQuizzes = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM attempts");
    $totalAttempts = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT AVG(score) as avg_score FROM attempts WHERE submitted_at IS NOT NULL");
    $avgScore = round((float)($stmt->fetch()['avg_score'] ?? 0), 1);

    // Recent attempts
    $stmt = $pdo->query("
        SELECT a.attempt_id, u.name as student_name, u.email as student_email, q.title as quiz_title, a.score, a.submitted_at
        FROM attempts a
        JOIN users u ON a.student_id = u.user_id
        JOIN quizzes q ON a.quiz_id = q.quiz_id
        WHERE a.submitted_at IS NOT NULL
        ORDER BY a.submitted_at DESC
        LIMIT 6
    ");
    $recentAttempts = $stmt->fetchAll();

    // Query 7 days trend for Chart.js
    $days = [];
    $counts = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $days[$d] = date('M d', strtotime("-$i days"));
        $counts[$d] = 0;
    }

    $stmt = $pdo->query("
        SELECT DATE(submitted_at) as d, COUNT(*) as c 
        FROM attempts 
        WHERE submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(submitted_at)
    ");
    while ($row = $stmt->fetch()) {
        if (isset($counts[$row['d']])) {
            $counts[$row['d']] = (int)$row['c'];
        }
    }
    $chartLabels = array_values($days);
    $chartValues = array_values($counts);

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error loading dashboard: ' . $e->getMessage()];
    $totalUsers = $totalFaculty = $totalStudents = $totalAdmins = $totalSubjects = $totalQuizzes = $totalAttempts = 0;
    $avgScore = 0;
    $recentAttempts = [];
    $chartLabels = ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'];
    $chartValues = [0, 0, 0, 0, 0, 0, 0];
}
?>

<!-- Executive Header matching Reference -->
<div class="mb-4">
    <h2 class="mb-1 fw-800 text-dark" style="letter-spacing: -0.03em;">Administrative Overview</h2>
    <p class="text-muted small mb-0">Real-time telemetry, user management, and examination monitoring across all environments.</p>
</div>

<!-- 4 Modern Metric Cards (matching reference image top row) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="<?= BASE_PATH ?>/admin/students.php" class="text-decoration-none">
            <div class="metric-card-modern theme-emerald">
                <div class="metric-header">
                    <span class="metric-title">Enrolled Students</span>
                    <div class="metric-icon-box">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo $totalStudents; ?></div>
                <div class="metric-trend">
                    <i class="bi bi-arrow-up-right"></i> Active Candidates
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="<?= BASE_PATH ?>/admin/faculty.php" class="text-decoration-none">
            <div class="metric-card-modern theme-amber">
                <div class="metric-header">
                    <span class="metric-title">Faculty & Staff</span>
                    <div class="metric-icon-box">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo $totalFaculty + $totalAdmins; ?></div>
                <div class="metric-trend">
                    <i class="bi bi-people"></i> <?php echo $totalFaculty; ?> Faculty &bull; <?php echo $totalAdmins; ?> Admins
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="<?= BASE_PATH ?>/admin/subjects.php" class="text-decoration-none">
            <div class="metric-card-modern theme-indigo">
                <div class="metric-header">
                    <span class="metric-title">Active Subjects</span>
                    <div class="metric-icon-box">
                        <i class="bi bi-book-half"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo $totalSubjects; ?></div>
                <div class="metric-trend">
                    <i class="bi bi-layers-fill"></i> <?php echo $totalQuizzes; ?> Quizzes Active
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-lg-3">
        <a href="#recent-attempts" class="text-decoration-none">
            <div class="metric-card-modern theme-cyan">
                <div class="metric-header">
                    <span class="metric-title">Total Submissions</span>
                    <div class="metric-icon-box">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo $totalAttempts; ?></div>
                <div class="metric-trend">
                    <i class="bi bi-check-circle-fill"></i> Completed Tests
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Middle Row: Interactive Charts Section (Directly matching Reference Image!) -->
<div class="row g-3 mb-4">
    <!-- Left 8-cols: Spline Area Chart (like "Request Volume" in reference) -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Examination & Attempt Volume</h5>
                    <small class="text-muted">Daily candidate test submissions over the last 7 days</small>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="bi bi-broadcast me-1"></i> Live Metric
                </span>
            </div>
            <div class="card-body p-3">
                <div style="height: 260px; position: relative;">
                    <canvas id="attemptTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Right 4-cols: Donut Distribution Chart (like "Service Health" in reference) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">User Role Health</h5>
                    <small class="text-muted">Account tier distribution</small>
                </div>
                <span class="badge bg-light text-muted border"><?php echo $totalUsers; ?> Total</span>
            </div>
            <div class="card-body p-3 d-flex flex-column align-items-center justify-content-center">
                <div style="height: 180px; width: 180px; position: relative;">
                    <canvas id="roleDonutChart"></canvas>
                </div>
                <div class="w-100 mt-3 pt-2 border-top d-flex justify-content-around text-center small">
                    <div>
                        <div class="text-muted">Students</div>
                        <strong class="text-success"><?php echo $totalStudents; ?></strong>
                    </div>
                    <div>
                        <div class="text-muted">Faculty</div>
                        <strong class="text-primary"><?php echo $totalFaculty; ?></strong>
                    </div>
                    <div>
                        <div class="text-muted">Admins</div>
                        <strong class="text-danger"><?php echo $totalAdmins; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lower Section: Recent Submissions & System Status -->
<div class="row g-3" id="recent-attempts">
    <!-- Recent Submissions Table (8 cols) -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm overflow-hidden h-100">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span class="fw-bold text-dark">Recent Examination Submissions</span>
                </div>
                <span class="text-muted small">Real-time activity log</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Student</th>
                            <th>Quiz Title</th>
                            <th>Earned Score</th>
                            <th class="pe-4 text-end">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentAttempts) > 0): ?>
                            <?php foreach ($recentAttempts as $attempt): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-pale text-primary fw-bold" 
                                                 style="width: 32px; height: 32px; font-size: 0.8rem; flex-shrink: 0;">
                                                <?php echo strtoupper(substr($attempt['student_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark small"><?php echo htmlspecialchars($attempt['student_name']); ?></div>
                                                <small class="text-muted font-monospace" style="font-size: 0.75rem;"><?php echo htmlspecialchars($attempt['student_email'] ?? ''); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="small fw-semibold text-dark"><?php echo htmlspecialchars($attempt['quiz_title']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($attempt['score'] !== null): ?>
                                            <span class="badge bg-success-pale text-success border border-success-subtle px-2 py-1">
                                                <?php echo number_format($attempt['score'], 1); ?> pts
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <small class="text-muted font-monospace" style="font-size: 0.75rem;">
                                            <?php echo date('M d, H:i', strtotime($attempt['submitted_at'])); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="empty-state py-3">
                                        <i class="bi bi-inbox mb-2" style="font-size: 2.5rem; color: #94a3b8;"></i>
                                        <h6 class="fw-bold text-dark">No Submissions Recorded Yet</h6>
                                        <p class="text-muted small mb-0">Candidate quiz completions will automatically log here.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Quick Actions & System Info (4 cols) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom p-3">
                <span class="fw-bold text-dark">Quick Administration</span>
            </div>
            <div class="card-body p-3">
                <div class="d-grid gap-2">
                    <a href="<?= BASE_PATH ?>/admin/students.php" class="btn btn-outline-primary d-flex align-items-center justify-content-between p-2 text-start">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-mortarboard fs-5 text-primary"></i>
                            <div>
                                <div class="fw-bold small text-dark">Enroll Students</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Student roster & registration IDs</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?= BASE_PATH ?>/admin/faculty.php" class="btn btn-outline-primary d-flex align-items-center justify-content-between p-2 text-start">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-badge fs-5 text-primary"></i>
                            <div>
                                <div class="fw-bold small text-dark">Faculty & Staff</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Educators, instructors & admins</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?= BASE_PATH ?>/admin/subjects.php" class="btn btn-outline-secondary d-flex align-items-center justify-content-between p-2 text-start">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-book fs-5"></i>
                            <div>
                                <div class="fw-bold small">Manage Subjects & Catalog</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Assign teachers & curricula</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Chart.js Script matching Reference Visuals -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Spline Area Trend Chart
    const ctxTrend = document.getElementById('attemptTrendChart');
    if (ctxTrend) {
        const labels = <?php echo json_encode($chartLabels); ?>;
        const data = <?php echo json_encode($chartValues); ?>;
        
        // Gradient fill
        const gradient = ctxTrend.getContext('2d').createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
        gradient.addColorStop(1, 'rgba(16, 185, 129, 0.00)');

        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Quiz Submissions',
                    data: data,
                    borderColor: '#10b981',
                    borderWidth: 2.5,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0b0f17',
                        padding: 10,
                        titleFont: { size: 12 },
                        bodyFont: { size: 13, weight: 'bold' }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, color: '#94a3b8' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { size: 11 },
                            color: '#94a3b8'
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    }
                }
            }
        });
    }

    // 2. Role Donut Chart
    const ctxDonut = document.getElementById('roleDonutChart');
    if (ctxDonut) {
        const students = <?php echo $totalStudents; ?>;
        const faculty = <?php echo $totalFaculty; ?>;
        const admins = <?php echo $totalAdmins; ?>;

        const total = students + faculty + admins;
        const donutData = total === 0 ? [1] : [students, faculty, admins];
        const donutColors = total === 0 ? ['#e2e8f0'] : ['#10b981', '#4f46e5', '#ef4444'];

        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: total === 0 ? ['Empty'] : ['Students', 'Faculty', 'Admins'],
                datasets: [{
                    data: donutData,
                    backgroundColor: donutColors,
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: total > 0 }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
