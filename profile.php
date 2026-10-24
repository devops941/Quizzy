<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireRole(['admin', 'faculty', 'student']);

$user = currentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($current_password)) {
        $error = 'Please enter your current password.';
    } elseif (empty($new_password)) {
        $error = 'Please enter a new password.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($current_password, $row['password_hash'])) {
                $error = 'Current password is incorrect.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } elseif (strlen($new_password) < 8) {
                $error = 'New password must be at least 8 characters long.';
            } else {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                $stmt->execute([$password_hash, $user['id']]);
                $message = 'Password updated successfully!';
            }
        } catch (PDOException $e) {
            error_log('Profile password update error: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge bg-primary-pale text-primary text-uppercase px-2 py-1 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">Account</span>
        <span class="text-muted small">&bull;</span>
        <span class="text-muted small">Personal Settings</span>
    </div>
    <h2 class="mb-1 fw-800 text-dark d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
        <i class="bi bi-person-circle text-primary"></i> My Profile
    </h2>
    <p class="text-muted small mb-0">Manage your personal credentials, contact details, and account security.</p>
</div>

<div class="row g-4">
    <!-- Left Column: User Overview Card -->
    <div class="col-lg-4 col-xl-4">
        <div class="card border-0 shadow-sm overflow-hidden mb-4">
            <div class="card-body p-4 text-center">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold shadow mb-3" 
                     style="width: 76px; height: 76px; font-size: 1.85rem; background: linear-gradient(135deg, #10b981, #059669);">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <h4 class="mb-1 fw-800 text-dark"><?php echo htmlspecialchars($user['name']); ?></h4>
                <div class="mb-3">
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; font-size: 0.78rem;">Platform Administrator</span>
                    <?php elseif ($user['role'] === 'faculty'): ?>
                        <span class="badge" style="background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; font-size: 0.78rem;">Faculty Member</span>
                    <?php else: ?>
                        <span class="badge" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; font-size: 0.78rem;">Registered Student</span>
                    <?php endif; ?>
                </div>
                <div class="p-3 bg-light rounded-3 text-start small">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Email</span>
                        <span class="fw-semibold text-dark text-truncate ps-2" style="max-width: 170px;" title="<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Account Tier</span>
                        <span class="fw-semibold text-dark text-uppercase"><?php echo htmlspecialchars($user['role']); ?></span>
                    </div>
                    <?php if (!empty($user['roll_no'])): ?>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Student ID</span>
                        <span class="fw-semibold text-dark font-monospace">#<?php echo htmlspecialchars($user['roll_no']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Security & Password Form -->
    <div class="col-lg-8 col-xl-7">
        <div class="card border-0 shadow-sm overflow-hidden mb-4">
            <div class="card-header bg-white border-bottom p-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock-fill text-primary fs-5"></i>
                    <span class="fw-bold text-dark fs-6">Security & Password</span>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Enter your current password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="At least 8 characters" required>
                        <div class="form-text">Choose a strong password containing letters and numbers.</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Retype new password" required>
                    </div>

                    <button type="submit" class="btn btn-primary shadow-sm px-4">
                        <i class="bi bi-check2-circle me-1"></i> Update Security Credentials
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
