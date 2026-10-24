<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// Direct logged-in users straight to their portal
if (isLoggedIn()) {
    $curr = currentUser();
    $r = $curr['role'] ?? '';
    if ($r === 'admin') {
        header('Location: ' . BASE_PATH . '/admin/index.php');
        exit;
    } elseif ($r === 'faculty') {
        header('Location: ' . BASE_PATH . '/faculty/index.php');
        exit;
    } elseif ($r === 'student') {
        header('Location: ' . BASE_PATH . '/student/dashboard.php');
        exit;
    }
}

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-5">
    <div class="col-lg-11 col-xl-10 mx-auto">
        <div class="card border-0 text-center py-5 px-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4f46e5 100%); border-radius: 28px; box-shadow: 0 20px 50px -10px rgba(79, 70, 229, 0.45); border: 1px solid rgba(255,255,255,0.12);">
            <!-- Subtle ambient background glow -->
            <div style="position: absolute; top: -40px; right: -40px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(168, 85, 247, 0.35) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="position: absolute; bottom: -50px; left: -50px; width: 250px; height: 250px; background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>

            <div class="position-relative" style="z-index: 2;">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 mb-3 rounded-pill" style="background: rgba(255,255,255,0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); color: #ffffff; font-size: 0.85rem; font-weight: 500;">
                    <i class="bi bi-stars text-warning"></i> Next-Gen Smart Assessment Platform
                </div>

                <h1 class="mb-3 text-white fw-800" style="font-size: 2.75rem; letter-spacing: -0.03em;">Welcome to Quizzy</h1>
                <p class="lead mb-4 mx-auto" style="color: rgba(255,255,255,0.85); max-width: 650px; font-size: 1.15rem; font-weight: 400;">The intelligent online quiz & evaluation engine tailored for students, faculty, and academic institutions.</p>

                <?php if (!isset($_SESSION['user']) || empty($_SESSION['user'])): ?>
                    <div class="d-flex gap-3 justify-content-center flex-wrap mt-2">
                        <a href="<?= BASE_PATH ?>/login.php" class="btn btn-lg shadow-sm" style="background: #ffffff; color: #4f46e5; font-weight: 700; border-radius: 12px; padding: 0.85rem 2.25rem;">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to Get Started
                        </a>
                    </div>
                <?php else: ?>
                    <?php $user = $_SESSION['user']; ?>
                    <p class="mb-4" style="color: rgba(255,255,255,0.92); font-size: 1.1rem;">
                        Hello, <strong><?php echo htmlspecialchars($user['name']); ?></strong>! Ready to jump back in?
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="<?= BASE_PATH ?>/admin/students.php" class="btn btn-lg shadow-sm" style="background: #ffffff; color: #4f46e5; font-weight: 700; border-radius: 12px;">
                                <i class="bi bi-mortarboard me-2"></i>Students Directory
                            </a>
                            <a href="<?= BASE_PATH ?>/admin/faculty.php" class="btn btn-outline-light btn-lg" style="border-radius: 12px; font-weight: 600;">
                                <i class="bi bi-person-badge me-2"></i>Faculty & Staff
                            </a>
                            <a href="<?= BASE_PATH ?>/admin/subjects.php" class="btn btn-outline-light btn-lg" style="border-radius: 12px; font-weight: 600;">
                                <i class="bi bi-book me-2"></i>Subjects
                            </a>
                        <?php elseif ($user['role'] === 'faculty'): ?>
                            <a href="<?= BASE_PATH ?>/faculty/questions.php" class="btn btn-lg shadow-sm" style="background: #ffffff; color: #4f46e5; font-weight: 700; border-radius: 12px;">
                                <i class="bi bi-question-circle me-2"></i>Question Bank
                            </a>
                            <a href="<?= BASE_PATH ?>/faculty/create_quiz.php" class="btn btn-outline-light btn-lg" style="border-radius: 12px; font-weight: 600;">
                                <i class="bi bi-pencil-square me-2"></i>Create New Quiz
                            </a>
                        <?php elseif ($user['role'] === 'student'): ?>
                            <a href="<?= BASE_PATH ?>/student/dashboard.php" class="btn btn-lg shadow-sm" style="background: #ffffff; color: #4f46e5; font-weight: 700; border-radius: 12px; padding: 0.85rem 2.25rem;">
                                <i class="bi bi-grid me-2"></i>Go to My Quizzes
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="text-center mb-4">
    <h3 class="fw-700 mb-2">Designed for High Performance & Integrity</h3>
    <p class="text-muted">Explore how Quizzy powers seamless, automated academic assessments</p>
</div>

<div id="features" class="row g-4 mb-5">
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 p-3 shadow-sm">
            <div class="card-body">
                <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 shadow-sm" style="width:56px;height:56px;background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;">
                    <i class="bi bi-lightning-charge-fill" style="font-size:1.5rem;"></i>
                </div>
                <h5 class="fw-700 mb-2">Instant Automated Evaluation</h5>
                <p class="text-muted small mb-0">Experience real-time grading and instant score calculation immediately upon quiz submission.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 p-3 shadow-sm">
            <div class="card-body">
                <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 shadow-sm" style="width:56px;height:56px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;">
                    <i class="bi bi-bar-chart-fill" style="font-size:1.5rem;"></i>
                </div>
                <h5 class="fw-700 mb-2">Analytics & Detailed Reports</h5>
                <p class="text-muted small mb-0">Track performance over time with accuracy percentages, question breakdowns, and leaderboards.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 p-3 shadow-sm">
            <div class="card-body">
                <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 shadow-sm" style="width:56px;height:56px;background:linear-gradient(135deg,#0284c7,#06b6d4);color:#fff;">
                    <i class="bi bi-shield-check-fill" style="font-size:1.5rem;"></i>
                </div>
                <h5 class="fw-700 mb-2">Enterprise Security</h5>
                <p class="text-muted small mb-0">Strict role-based access control, hashed credentials, and server-side authorization guard every test.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 p-3 shadow-sm">
            <div class="card-body">
                <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 shadow-sm" style="width:56px;height:56px;background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;">
                    <i class="bi bi-clock-fill" style="font-size:1.5rem;"></i>
                </div>
                <h5 class="fw-700 mb-2">Precise Countdown Timers</h5>
                <p class="text-muted small mb-0">Built-in synchronized countdown timer with auto-submission guarantees strict time enforcement.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 p-3 shadow-sm">
            <div class="card-body">
                <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 shadow-sm" style="width:56px;height:56px;background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;">
                    <i class="bi bi-shuffle" style="font-size:1.5rem;"></i>
                </div>
                <h5 class="fw-700 mb-2">Question & Option Shuffling</h5>
                <p class="text-muted small mb-0">Dynamic per-student randomization minimizes cheating and ensures authentic individual results.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 p-3 shadow-sm">
            <div class="card-body">
                <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 shadow-sm" style="width:56px;height:56px;background:linear-gradient(135deg,#dc2626,#f43f5e);color:#fff;">
                    <i class="bi bi-people-fill" style="font-size:1.5rem;"></i>
                </div>
                <h5 class="fw-700 mb-2">Dedicated Portals</h5>
                <p class="text-muted small mb-0">Specialized dashboards for administrators, faculty educators, and student examinees.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
