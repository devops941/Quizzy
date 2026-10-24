<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$user = $isLoggedIn ? $_SESSION['user'] : null;
$role = $user['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzy — <?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- Google Fonts: Roboto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500;1,700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & Bootstrap Icons 1.11.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Chart.js for Executive Dashboard Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Quizzy Global Stylesheet with Cache-Busting -->
    <link href="<?= BASE_PATH ?>/assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>

<?php if ($isLoggedIn): ?>
    <!-- ── App Layout with Dark Sidebar (Like Reference UI) ── -->
    <div class="app-wrapper">
        <!-- Left Sidebar Navigation -->
        <aside class="app-sidebar" id="appSidebar">
            <!-- Sidebar Brand -->
            <div class="sidebar-header">
                <a class="sidebar-brand" href="<?= BASE_PATH ?>/<?= $role === 'admin' ? 'admin/index.php' : ($role === 'faculty' ? 'faculty/index.php' : ($role === 'student' ? 'student/dashboard.php' : '')) ?>">
                    <div class="brand-logo-box">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="brand-name">Quizzy</span>
                        <span class="brand-tag"><?= strtoupper(htmlspecialchars($role)) ?> CONSOLE</span>
                    </div>
                </a>
                <button class="btn btn-sm btn-link d-lg-none text-white ms-auto" id="sidebarCloseBtn" type="button">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <div class="sidebar-menu">
                <?php if ($role === 'admin'): ?>
                    <div class="sidebar-group-title">Platform Overview</div>
                    <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/admin/index.php') !== false || ($currentPath === BASE_PATH . '/admin/' || $currentPath === BASE_PATH . '/admin') ? 'active' : '' ?>" href="<?= BASE_PATH ?>/admin/index.php">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    </ul>

                    <div class="sidebar-group-title">Administration</div>
                    <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/admin/students.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/admin/students.php">
                                <i class="bi bi-mortarboard-fill"></i>
                                <span>Students</span>
                            </a>
                        </li>
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/admin/faculty.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/admin/faculty.php">
                                <i class="bi bi-person-badge-fill"></i>
                                <span>Faculty & Staff</span>
                            </a>
                        </li>
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/admin/subjects.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/admin/subjects.php">
                                <i class="bi bi-book-half"></i>
                                <span>Curriculum & Subjects</span>
                            </a>
                        </li>
                    </ul>

                    <div class="sidebar-group-title">Analytics & Audit</div>
                    <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/admin/index.php#audit') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/admin/index.php#recent-attempts">
                                <i class="bi bi-clock-history"></i>
                                <span>Exam Submissions</span>
                            </a>
                        </li>
                    </ul>

                <?php elseif ($role === 'faculty'): ?>
                    <div class="sidebar-group-title">Overview</div>
                    <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/faculty/index.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/faculty/index.php">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    </ul>

                    <div class="sidebar-group-title">Examinations</div>
                    <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/faculty/questions.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/faculty/questions.php">
                                <i class="bi bi-patch-question-fill"></i>
                                <span>Question Bank</span>
                            </a>
                        </li>
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/faculty/create_quiz.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/faculty/create_quiz.php">
                                <i class="bi bi-pencil-square"></i>
                                <span>Create / Manage Quiz</span>
                            </a>
                        </li>
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/faculty/upload_csv.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/faculty/upload_csv.php">
                                <i class="bi bi-cloud-arrow-up-fill"></i>
                                <span>Bulk CSV Upload</span>
                            </a>
                        </li>
                    </ul>

                    <div class="sidebar-group-title">Analytics</div>
                    <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/faculty/results.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/faculty/results.php">
                                <i class="bi bi-bar-chart-fill"></i>
                                <span>Results & Leaderboard</span>
                            </a>
                        </li>
                    </ul>

                <?php elseif ($role === 'student'): ?>
                    <div class="sidebar-group-title">Student Portal</div>
                    <ul class="sidebar-nav">
                        <li class="sidebar-nav-item">
                            <a class="sidebar-nav-link <?= strpos($currentPath, '/student/dashboard.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/student/dashboard.php">
                                <i class="bi bi-grid-fill"></i>
                                <span>My Quizzes</span>
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>

                <div class="sidebar-group-title">System & Account</div>
                <ul class="sidebar-nav">
                    <li class="sidebar-nav-item">
                        <a class="sidebar-nav-link <?= strpos($currentPath, '/profile.php') !== false ? 'active' : '' ?>" href="<?= BASE_PATH ?>/profile.php">
                            <i class="bi bi-person-circle"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Bottom User Card -->
            <div class="sidebar-footer">
                <div class="sidebar-user-card">
                    <div class="avatar-circle">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <div class="user-meta">
                        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="user-role"><?= htmlspecialchars($role) ?></div>
                    </div>
                    <a href="<?= BASE_PATH ?>/logout.php" class="btn-logout" title="Sign Out">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Shell -->
        <div class="app-main">
            <!-- Modern Topbar -->
            <header class="app-topbar">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-light d-lg-none border shadow-none" id="sidebarToggleBtn" type="button">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                </div>

                <!-- Right Topbar Controls -->
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <?php if ($role === 'admin'): ?>
                        <a href="<?= BASE_PATH ?>/admin/students.php" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm d-none d-sm-inline-flex align-items-center gap-1">
                            <i class="bi bi-mortarboard-fill"></i> Students
                        </a>
                        <a href="<?= BASE_PATH ?>/admin/faculty.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-none d-sm-inline-flex align-items-center gap-1">
                            <i class="bi bi-person-badge-fill"></i> Faculty & Staff
                        </a>
                        <a href="<?= BASE_PATH ?>/admin/subjects.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-none d-md-inline-flex align-items-center gap-1">
                            <i class="bi bi-book"></i> Subjects
                        </a>
                    <?php elseif ($role === 'faculty'): ?>
                        <a href="<?= BASE_PATH ?>/faculty/create_quiz.php" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm d-none d-sm-inline-flex align-items-center gap-1">
                            <i class="bi bi-plus-circle"></i> Create Quiz
                        </a>
                    <?php endif; ?>

                    <div class="dropdown ms-2">
                        <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle p-1 rounded-pill bg-light border" data-bs-toggle="dropdown">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white fw-bold" style="width:28px;height:28px;font-size:0.75rem;">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </span>
                            <span class="fw-semibold small text-dark d-none d-sm-inline pe-1"><?= htmlspecialchars($user['name']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($user['name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($user['email']) ?></div>
                            </li>
                            <li><a class="dropdown-item py-2" href="<?= BASE_PATH ?>/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_PATH ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Dashboard Body Content -->
            <main class="app-content-body">
                <?php
                $flash = getFlash();
                if ($flash) {
                    $alertClass = match($flash['type']) {
                        'success' => 'alert-success',
                        'danger'  => 'alert-danger',
                        'warning' => 'alert-warning',
                        default   => 'alert-info'
                    };
                    echo '<div class="alert ' . htmlspecialchars($alertClass) . ' alert-dismissible fade show mb-4" role="alert">';
                    echo '<i class="bi bi-' . ($flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle') . ' me-2"></i>';
                    echo htmlspecialchars($flash['msg']);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    echo '</div>';
                }
                ?>

<?php else: ?>
    <!-- ── Public Topnav Layout for Guests ── -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container px-4">
            <a class="navbar-brand" href="<?= BASE_PATH ?>/">
                <span class="brand-icon"><i class="bi bi-lightning-charge-fill"></i></span>
                <span>Quizzy</span>
            </a>
            <div class="ms-auto">
                <a class="btn btn-outline-light btn-sm px-3" href="<?= BASE_PATH ?>/login.php">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </a>
            </div>
        </div>
    </nav>
    <main class="container py-4">
        <?php
        $flash = getFlash();
        if ($flash) {
            $alertClass = match($flash['type']) {
                'success' => 'alert-success',
                'danger'  => 'alert-danger',
                'warning' => 'alert-warning',
                default   => 'alert-info'
            };
            echo '<div class="alert ' . htmlspecialchars($alertClass) . ' alert-dismissible fade show" role="alert">';
            echo '<i class="bi bi-' . ($flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle') . ' me-2"></i>';
            echo htmlspecialchars($flash['msg']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        ?>
<?php endif; ?>
