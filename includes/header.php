<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzy — <?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --quizzy-primary: #4f46e5;
        }
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: var(--quizzy-primary) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background-color: var(--quizzy-primary);
            border-color: var(--quizzy-primary);
        }
        .btn-primary:hover {
            background-color: #3f37d9;
            border-color: #3f37d9;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #4f46e5;">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/">
            <i class="bi bi-question-circle"></i> Quizzy
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php
                if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
                    $user = $_SESSION['user'];
                    $role = $user['role'];

                    if ($role === 'admin') {
                        echo '<li class="nav-item"><a class="nav-link" href="/admin/users.php">Users</a></li>';
                        echo '<li class="nav-item"><a class="nav-link" href="/admin/subjects.php">Subjects</a></li>';
                    } elseif ($role === 'faculty') {
                        echo '<li class="nav-item"><a class="nav-link" href="/faculty/questions.php">Questions</a></li>';
                        echo '<li class="nav-item"><a class="nav-link" href="/faculty/create_quiz.php">Create Quiz</a></li>';
                        echo '<li class="nav-item"><a class="nav-link" href="/faculty/results.php">Results</a></li>';
                    } elseif ($role === 'student') {
                        echo '<li class="nav-item"><a class="nav-link" href="/student/dashboard.php">My Quizzes</a></li>';
                    }

                    echo '<li class="nav-item dropdown">';
                    echo '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">';
                    echo '<i class="bi bi-person-circle"></i> ' . htmlspecialchars($user['name']);
                    echo '</a>';
                    echo '<ul class="dropdown-menu dropdown-menu-end">';
                    echo '<li><a class="dropdown-item" href="/profile.php">Profile</a></li>';
                    echo '<li><hr class="dropdown-divider"></li>';
                    echo '<li><a class="dropdown-item" href="/logout.php">Logout</a></li>';
                    echo '</ul>';
                    echo '</li>';
                } else {
                    echo '<li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>';
                }
                ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
    <?php
    $flash = getFlash();
    if ($flash) {
        $alertClass = match($flash['type']) {
            'success' => 'alert-success',
            'danger' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        echo '<div class="alert ' . htmlspecialchars($alertClass) . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($flash['msg']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    ?>
