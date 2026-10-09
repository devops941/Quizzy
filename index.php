<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';
?>

<div class="row mb-5">
    <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-lg">
            <div class="card-body text-center py-5">
                <h1 class="mb-3">Welcome to Quizzy</h1>
                <p class="lead text-muted mb-4">The Online Quiz System for Smart Learning</p>
                <?php if (!isset($_SESSION['user']) || empty($_SESSION['user'])): ?>
                    <p class="mb-4">Login to your account to get started with quizzes.</p>
                    <a href="/login.php" class="btn btn-primary btn-lg me-2">Login</a>
                    <a href="#features" class="btn btn-outline-primary btn-lg">Learn More</a>
                <?php else: ?>
                    <?php $user = $_SESSION['user']; ?>
                    <p class="mb-4">Hello, <strong><?php echo htmlspecialchars($user['name']); ?></strong>!</p>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="/admin/users.php" class="btn btn-primary btn-lg me-2">Manage Users</a>
                        <a href="/admin/subjects.php" class="btn btn-outline-primary btn-lg">Manage Subjects</a>
                    <?php elseif ($user['role'] === 'faculty'): ?>
                        <a href="/faculty/questions.php" class="btn btn-primary btn-lg me-2">Manage Questions</a>
                        <a href="/faculty/create_quiz.php" class="btn btn-outline-primary btn-lg">Create Quiz</a>
                    <?php elseif ($user['role'] === 'student'): ?>
                        <a href="/student/dashboard.php" class="btn btn-primary btn-lg">Take Quiz</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="features" class="row">
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 h-100">
            <div class="card-body text-center">
                <i class="bi bi-lightning-charge text-primary" style="font-size: 3rem;"></i>
                <h5 class="card-title mt-3">Instant Evaluation</h5>
                <p class="card-text text-muted">Get your quiz results immediately with automated evaluation and detailed feedback.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 h-100">
            <div class="card-body text-center">
                <i class="bi bi-bar-chart text-success" style="font-size: 3rem;"></i>
                <h5 class="card-title mt-3">Analytics & Reports</h5>
                <p class="card-text text-muted">Track performance with comprehensive statistics and visualizations for both students and teachers.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 h-100">
            <div class="card-body text-center">
                <i class="bi bi-shield-check text-info" style="font-size: 3rem;"></i>
                <h5 class="card-title mt-3">Secure & Reliable</h5>
                <p class="card-text text-muted">Enterprise-grade security ensures your quiz data and results are always protected.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 h-100">
            <div class="card-body text-center">
                <i class="bi bi-clock text-warning" style="font-size: 3rem;"></i>
                <h5 class="card-title mt-3">Time Management</h5>
                <p class="card-text text-muted">Built-in timer with automatic submission ensures fair and controlled quiz taking.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 h-100">
            <div class="card-body text-center">
                <i class="bi bi-shuffle text-danger" style="font-size: 3rem;"></i>
                <h5 class="card-title mt-3">Randomization</h5>
                <p class="card-text text-muted">Questions and options are shuffled for each student to ensure authentic assessment.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 h-100">
            <div class="card-body text-center">
                <i class="bi bi-people text-secondary" style="font-size: 3rem;"></i>
                <h5 class="card-title mt-3">Role-Based Access</h5>
                <p class="card-text text-muted">Different interfaces for admins, faculty, and students with role-specific features.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
