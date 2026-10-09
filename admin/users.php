<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('admin');

$currentUser = currentUser();
$pageTitle = 'Manage Users';

$roleFilter = $_GET['role'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'student';
        $rollNo = $_POST['roll_no'] ?? null;

        if (empty($name) || empty($email) || empty($password)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Name, email, and password are required.'];
        } else {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role, roll_no)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $email, $passwordHash, $role, $rollNo ?: null]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'User added successfully.'];
                header('Location: users.php');
                exit;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Email already exists.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error adding user.'];
                }
            }
        }
    } elseif ($action === 'edit_user') {
        $userId = $_POST['user_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'student';
        $rollNo = $_POST['roll_no'] ?? null;

        if ($userId == $currentUser['id']) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Cannot edit your own account from here.'];
        } elseif (empty($name) || empty($email)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Name and email are required.'];
        } else {
            try {
                if (!empty($password)) {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users SET name = ?, email = ?, password_hash = ?, role = ?, roll_no = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$name, $email, $passwordHash, $role, $rollNo ?: null, $userId]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET name = ?, email = ?, role = ?, roll_no = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$name, $email, $role, $rollNo ?: null, $userId]);
                }
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'User updated successfully.'];
                header('Location: users.php');
                exit;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Email already exists.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error updating user.'];
                }
            }
        }
    } elseif ($action === 'delete_user') {
        $userId = $_POST['user_id'] ?? 0;

        if ($userId == $currentUser['id']) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Cannot delete your own account.'];
        } else {
            try {
                $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'User deleted successfully.'];
                header('Location: users.php');
                exit;
            } catch (Exception $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error deleting user.'];
            }
        }
    }
}

try {
    $query = "SELECT * FROM users WHERE 1=1";
    $params = [];

    if ($roleFilter !== 'all') {
        $query .= " AND role = ?";
        $params[] = $roleFilter;
    }

    if (!empty($searchQuery)) {
        $query .= " AND (name LIKE ? OR email LIKE ?)";
        $searchTerm = '%' . $searchQuery . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $query .= " ORDER BY role ASC, name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error loading users.'];
    $users = [];
}

require_once __DIR__.'/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Users</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-plus-circle me-2"></i>Add User
    </button>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <input type="text" class="form-control" id="searchInput" placeholder="Search by name or email...">
            </div>
            <div class="col-md-6">
                <select class="form-select" id="roleFilter">
                    <option value="all">All Roles</option>
                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="faculty" <?php echo $roleFilter === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                    <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Student</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Roll No</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge bg-<?php
                                echo $user['role'] === 'admin' ? 'danger' :
                                     ($user['role'] === 'faculty' ? 'info' : 'success');
                            ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo $user['roll_no'] ? htmlspecialchars($user['roll_no']) : '-'; ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                    onclick="loadUserData(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <?php if ($user['user_id'] != $currentUser['id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($users) === 0): ?>
        <div class="card-body text-center text-muted">
            <p>No users found.</p>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">

                    <div class="mb-3">
                        <label for="addName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="addName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="addEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="addEmail" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="addPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="addPassword" name="password" required>
                    </div>

                    <div class="mb-3">
                        <label for="addRole" class="form-label">Role</label>
                        <select class="form-select" id="addRole" name="role" onchange="toggleRollNoField('add')">
                            <option value="student">Student</option>
                            <option value="faculty">Faculty</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="mb-3" id="addRollNoContainer">
                        <label for="addRollNo" class="form-label">Roll No</label>
                        <input type="text" class="form-control" id="addRollNo" name="roll_no">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="editUserId">

                    <div class="mb-3">
                        <label for="editName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="editPassword" class="form-label">Password (leave blank to keep existing)</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                    </div>

                    <div class="mb-3">
                        <label for="editRole" class="form-label">Role</label>
                        <select class="form-select" id="editRole" name="role" onchange="toggleRollNoField('edit')">
                            <option value="student">Student</option>
                            <option value="faculty">Faculty</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="mb-3" id="editRollNoContainer">
                        <label for="editRollNo" class="form-label">Roll No</label>
                        <input type="text" class="form-control" id="editRollNo" name="roll_no">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRollNoField(prefix) {
    const roleSelect = document.getElementById(prefix + 'Role');
    const rollNoContainer = document.getElementById(prefix + 'RollNoContainer');
    if (roleSelect.value === 'student') {
        rollNoContainer.style.display = 'block';
    } else {
        rollNoContainer.style.display = 'none';
    }
}

function loadUserData(user) {
    document.getElementById('editUserId').value = user.user_id;
    document.getElementById('editName').value = user.name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editRole').value = user.role;
    document.getElementById('editRollNo').value = user.roll_no || '';
    document.getElementById('editPassword').value = '';
    toggleRollNoField('edit');
}

document.getElementById('searchInput').addEventListener('keyup', function() {
    const search = this.value;
    const role = document.getElementById('roleFilter').value;
    window.location.href = 'users.php?search=' + encodeURIComponent(search) + '&role=' + encodeURIComponent(role);
});

document.getElementById('roleFilter').addEventListener('change', function() {
    const search = document.getElementById('searchInput').value;
    const role = this.value;
    window.location.href = 'users.php?search=' + encodeURIComponent(search) + '&role=' + encodeURIComponent(role);
});

toggleRollNoField('add');
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
