<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
requireRole(['admin', 'faculty', 'student']);

$user = currentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            try {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                $stmt->execute([$password_hash, $user['id']]);
                $message = 'Password updated successfully!';
            } catch (PDOException $e) {
                $error = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    } else {
        $error = 'Please enter a new password.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <i class="bi bi-person-circle"></i> My Profile
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Name</h6>
                        <p class="fs-5 mb-0"><?php echo htmlspecialchars($user['name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Email</h6>
                        <p class="fs-5 mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small mb-2">Role</h6>
                        <p class="fs-5 mb-0">
                            <span class="badge badge-primary text-uppercase"><?php echo htmlspecialchars($user['role']); ?></span>
                        </p>
                    </div>
                    <?php if (!empty($user['roll_no'])): ?>
                        <div class="col-md-6">
                            <h6 class="text-muted text-uppercase small mb-2">Roll Number</h6>
                            <p class="fs-5 mb-0"><?php echo htmlspecialchars($user['roll_no']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <hr>

                <h5 class="mb-4">Change Password</h5>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
