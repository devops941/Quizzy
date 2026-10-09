<?php
$pageTitle = 'Login';
session_start();

if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT user_id, name, email, password_hash, role, roll_no FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user'] = [
                    'id' => $user['user_id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'roll_no' => $user['roll_no']
                ];

                if ($user['role'] === 'admin') {
                    header('Location: /admin/users.php');
                } elseif ($user['role'] === 'faculty') {
                    header('Location: /faculty/questions.php');
                } else {
                    header('Location: /student/dashboard.php');
                }
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Quizzy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .btn-login {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #3730a3 0%, #2d1e8f 100%);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h1 class="mb-2">
                        <i class="bi bi-question-circle text-primary"></i>
                    </h1>
                    <h3 class="mb-1">Welcome to Quizzy</h3>
                    <p class="text-muted small">Online Quiz System</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn btn-login btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </button>
                </form>

                <div class="mt-4 pt-3 border-top">
                    <p class="text-muted small text-center mb-2"><i class="bi bi-info-circle"></i> Demo Credentials</p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start px-3" onclick="fillLogin('admin@quizzy.com','admin123')">
                            <span class="badge bg-danger me-2">Admin</span> admin@quizzy.com &nbsp;·&nbsp; admin123
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start px-3" onclick="fillLogin('faculty@quizzy.com','faculty123')">
                            <span class="badge bg-primary me-2">Faculty</span> faculty@quizzy.com &nbsp;·&nbsp; faculty123
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start px-3" onclick="fillLogin('student@quizzy.com','student123')">
                            <span class="badge bg-success me-2">Student</span> student@quizzy.com &nbsp;·&nbsp; student123
                        </button>
                    </div>
                </div>
                <script>
                function fillLogin(email, pass) {
                    document.getElementById('email').value = email;
                    document.getElementById('password').value = pass;
                }
                </script>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="/" class="text-white text-decoration-none small">
                <i class="bi bi-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
