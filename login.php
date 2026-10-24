<?php
$pageTitle = 'Login';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
    header('Location: ' . BASE_PATH . '/');
    exit;
}

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
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'     => $user['user_id'],
                    'name'   => $user['name'],
                    'email'  => $user['email'],
                    'role'   => $user['role'],
                    'roll_no'=> $user['roll_no']
                ];

                if ($user['role'] === 'admin') {
                    header('Location: ' . BASE_PATH . '/admin/index.php');
                } elseif ($user['role'] === 'faculty') {
                    header('Location: ' . BASE_PATH . '/faculty/index.php');
                } else {
                    header('Location: ' . BASE_PATH . '/student/dashboard.php');
                }
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log('Login DB error: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzy — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500;1,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: #0f0c29;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            overflow: hidden;
            position: relative;
        }

        /* ── Animated mesh background ── */
        .bg-mesh {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(120, 40, 200, 0.35) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(79, 70, 229, 0.4)  0%, transparent 55%),
                radial-gradient(ellipse at 60% 80%, rgba(16, 185, 129, 0.2) 0%, transparent 50%),
                linear-gradient(135deg, #0f0c29 0%, #1a1040 50%, #0f0c29 100%);
            animation: meshShift 10s ease-in-out infinite alternate;
        }
        @keyframes meshShift {
            0%   { filter: hue-rotate(0deg) brightness(1); }
            100% { filter: hue-rotate(20deg) brightness(1.1); }
        }

        /* ── Floating particles ── */
        .particles { position: fixed; inset: 0; pointer-events: none; }
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            animation: floatUp linear infinite;
        }
        @keyframes floatUp {
            0%   { transform: translateY(110vh) scale(0); opacity: 0; }
            10%  { opacity: 1; }
            90%  { opacity: 0.6; }
            100% { transform: translateY(-10vh) scale(1.2); opacity: 0; }
        }

        /* ── Card wrapper ── */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 460px;
            padding: 1rem;
            animation: slideUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0)   scale(1); }
        }

        /* ── Glass card ── */
        .glass-card {
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            padding: 2.5rem 2.25rem 2rem;
            box-shadow: 0 32px 64px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.15);
        }

        /* ── Logo ── */
        .logo-ring {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            box-shadow: 0 0 0 8px rgba(99,102,241,0.15), 0 8px 24px rgba(99,102,241,0.4);
            animation: pulse 3s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 8px rgba(99,102,241,0.15), 0 8px 24px rgba(99,102,241,0.4); }
            50%       { box-shadow: 0 0 0 16px rgba(99,102,241,0.08), 0 8px 32px rgba(99,102,241,0.6); }
        }
        .logo-ring i { font-size: 2rem; color: #fff; }

        .brand-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .brand-sub {
            font-size: 0.82rem;
            color: rgba(255,255,255,0.45);
            margin-top: 2px;
        }

        /* ── Error alert ── */
        .alert-glass {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.35);
            color: #fca5a5;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            display: flex; align-items: center; gap: 0.5rem;
            animation: shake 0.4s ease;
        }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%      { transform: translateX(-6px); }
            40%      { transform: translateX(6px); }
            60%      { transform: translateX(-4px); }
            80%      { transform: translateX(4px); }
        }

        /* ── Floating label inputs ── */
        .field-wrap {
            position: relative;
            margin-bottom: 1.25rem;
        }
        .field-wrap input {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            padding: 1.05rem 1rem 0.4rem 2.75rem;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;
        }
        .field-wrap input::placeholder { color: transparent; }
        .field-wrap input:focus {
            border-color: #6366f1;
            background: rgba(99,102,241,0.08);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
        }
        .field-wrap label {
            position: absolute;
            left: 2.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.4);
            font-size: 0.9rem;
            pointer-events: none;
            transition: all 0.2s ease;
        }
        .field-wrap input:focus ~ label,
        .field-wrap input:not(:placeholder-shown) ~ label {
            top: 0.45rem;
            font-size: 0.72rem;
            color: #a5b4fc;
            transform: none;
        }
        .field-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.3);
            font-size: 1rem;
            pointer-events: none;
            transition: color 0.25s;
        }
        .field-wrap input:focus ~ label ~ .field-icon,
        .field-wrap:focus-within .field-icon { color: #a5b4fc; }

        .toggle-pw {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none; border: none; padding: 4px;
            color: rgba(255,255,255,0.3);
            cursor: pointer;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: #a5b4fc; }

        /* ── Login button ── */
        .btn-signin {
            width: 100%;
            padding: 0.85rem;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 20px rgba(99,102,241,0.45);
        }
        .btn-signin::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #fff2, transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .btn-signin:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(99,102,241,0.55); }
        .btn-signin:hover::after { opacity: 1; }
        .btn-signin:active { transform: translateY(0); }
        .btn-signin.loading { pointer-events: none; opacity: 0.8; }
        .btn-signin .spinner {
            display: none;
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin: 0 auto;
        }
        .btn-signin.loading .spinner { display: inline-block; }
        .btn-signin.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Divider ── */
        .divider {
            display: flex; align-items: center; gap: 0.75rem;
            margin: 1.5rem 0 1.1rem;
            color: rgba(255,255,255,0.2);
            font-size: 0.78rem;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.1);
        }

        /* ── Quick Login role cards ── */
        .role-cards { display: grid; grid-template-columns: repeat(3,1fr); gap: 0.6rem; }
        .role-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 0.85rem 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, transform 0.2s;
            position: relative;
            overflow: hidden;
        }
        .role-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--role-color);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .role-card:hover { transform: translateY(-3px); border-color: var(--role-color); }
        .role-card:hover::before { opacity: 0.1; }
        .role-card:active { transform: translateY(0); }
        .role-card .rc-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            background: var(--role-color);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: 1.1rem;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .role-card .rc-name {
            font-size: 0.72rem;
            font-weight: 600;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .role-card .rc-hint {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.3);
            margin-top: 2px;
        }

        .role-card[data-role="admin"]   { --role-color: #ef4444; }
        .role-card[data-role="faculty"] { --role-color: #6366f1; }
        .role-card[data-role="student"] { --role-color: #10b981; }

        /* ── Fill animation ── */
        .role-card.filled .rc-icon { animation: popIn 0.3s cubic-bezier(0.34,1.56,0.64,1); }
        @keyframes popIn {
            0%   { transform: scale(0.7); }
            100% { transform: scale(1); }
        }

        /* ── Back link ── */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255,255,255,0.35);
            font-size: 0.82rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: rgba(255,255,255,0.7); }
    </style>
</head>
<body>

<!-- Animated background -->
<div class="bg-mesh"></div>
<div class="particles" id="particles"></div>

<div class="login-wrapper">
    <div class="glass-card">

        <!-- Logo -->
        <div class="text-center mb-4">
            <div class="logo-ring">
                <i class="bi bi-patch-question-fill"></i>
            </div>
            <div class="brand-title">Quizzy</div>
            <div class="brand-sub">Online Quiz System</div>
        </div>

        <!-- Error -->
        <?php if (!empty($error)): ?>
        <div class="alert-glass mb-4">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Login form -->
        <form method="POST" action="" id="loginForm" autocomplete="off">

            <!-- Email -->
            <div class="field-wrap">
                <input type="email" id="email" name="email"
                       placeholder="Email Address"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       required>
                <label for="email">Email Address</label>
                <i class="bi bi-envelope field-icon"></i>
            </div>

            <!-- Password -->
            <div class="field-wrap">
                <input type="password" id="password" name="password"
                       placeholder="Password"
                       required>
                <label for="password">Password</label>
                <i class="bi bi-lock field-icon"></i>
                <button type="button" class="toggle-pw" id="togglePw" title="Show password">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>

            <button type="submit" class="btn-signin" id="submitBtn">
                <span class="btn-text"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</span>
                <span class="spinner"></span>
            </button>
        </form>

        <!-- Quick login -->
        <div class="divider">Quick Login</div>
        <div class="role-cards">
            <div class="role-card" data-role="admin"
                 onclick="fillLogin(this,'admin@quizzy.com','admin123')">
                <div class="rc-icon"><i class="bi bi-shield-lock-fill"></i></div>
                <div class="rc-name">Admin</div>
                <div class="rc-hint">admin123</div>
            </div>
            <div class="role-card" data-role="faculty"
                 onclick="fillLogin(this,'faculty@quizzy.com','faculty123')">
                <div class="rc-icon"><i class="bi bi-person-workspace"></i></div>
                <div class="rc-name">Faculty</div>
                <div class="rc-hint">faculty123</div>
            </div>
            <div class="role-card" data-role="student"
                 onclick="fillLogin(this,'student@quizzy.com','student123')">
                <div class="rc-icon"><i class="bi bi-mortarboard-fill"></i></div>
                <div class="rc-name">Student</div>
                <div class="rc-hint">student123</div>
            </div>
        </div>
    </div>

    <a href="<?= BASE_PATH ?>/" class="back-link">
        <i class="bi bi-arrow-left me-1"></i>Back to Home
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Particles ──
(function() {
    const container = document.getElementById('particles');
    const count = 22;
    for (let i = 0; i < count; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        const size = Math.random() * 6 + 3;
        p.style.cssText = `
            width:${size}px; height:${size}px;
            left:${Math.random()*100}%;
            animation-duration:${Math.random()*18+10}s;
            animation-delay:${Math.random()*12}s;
        `;
        container.appendChild(p);
    }
})();

// ── Password toggle ──
document.getElementById('togglePw').addEventListener('click', function() {
    const pw = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pw.type = 'password';
        icon.className = 'bi bi-eye';
    }
});

// ── Quick login fill ──
function fillLogin(card, email, pass) {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('filled'));
    card.classList.add('filled');
    const emailEl = document.getElementById('email');
    const passEl  = document.getElementById('password');
    emailEl.value = email;
    passEl.value  = pass;
    // Trigger floating label
    emailEl.dispatchEvent(new Event('input'));
    passEl.dispatchEvent(new Event('input'));
    // Flash submit button
    const btn = document.getElementById('submitBtn');
    btn.style.background = 'linear-gradient(135deg,#10b981,#059669)';
    setTimeout(() => btn.style.background = '', 600);
}

// ── Loading state on submit ──
document.getElementById('loginForm').addEventListener('submit', function() {
    document.getElementById('submitBtn').classList.add('loading');
});
</script>
</body>
</html>
