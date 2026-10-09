<?php
session_start();
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('admin');

$pageTitle = 'Manage Subjects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_subject') {
        $name = $_POST['name'] ?? '';
        $facultyId = $_POST['faculty_id'] ?? 0;

        if (empty($name) || $facultyId <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Subject name and faculty are required.'];
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO subjects (name, faculty_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$name, $facultyId]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Subject added successfully.'];
                header('Location: subjects.php');
                exit;
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error adding subject.'];
            }
        }
    } elseif ($action === 'edit_subject') {
        $subjectId = $_POST['subject_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $facultyId = $_POST['faculty_id'] ?? 0;

        if (empty($name) || $facultyId <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Subject name and faculty are required.'];
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE subjects SET name = ?, faculty_id = ?
                    WHERE subject_id = ?
                ");
                $stmt->execute([$name, $facultyId, $subjectId]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Subject updated successfully.'];
                header('Location: subjects.php');
                exit;
            } catch (PDOException $e) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error updating subject.'];
            }
        }
    } elseif ($action === 'delete_subject') {
        $subjectId = $_POST['subject_id'] ?? 0;

        try {
            $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?")->execute([$subjectId]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Subject deleted successfully.'];
            header('Location: subjects.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error deleting subject.'];
        }
    }
}

try {
    $stmt = $pdo->query("
        SELECT s.subject_id, s.name, s.faculty_id, u.name as faculty_name
        FROM subjects s
        INNER JOIN users u ON s.faculty_id = u.user_id
        ORDER BY s.name ASC
    ");
    $subjects = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT user_id, name FROM users WHERE role = 'faculty'
        ORDER BY name ASC
    ");
    $stmt->execute();
    $facultyUsers = $stmt->fetchAll();

} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Error loading subjects.'];
    $subjects = [];
    $facultyUsers = [];
}

require_once __DIR__.'/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Subjects</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
        <i class="bi bi-plus-circle me-2"></i>Add Subject
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Subject Name</th>
                    <th>Faculty</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($subject['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($subject['faculty_name']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSubjectModal"
                                    onclick="loadSubjectData(<?php echo htmlspecialchars(json_encode($subject)); ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Deleting this subject will remove all related questions and quizzes. Continue?');">
                                <input type="hidden" name="action" value="delete_subject">
                                <input type="hidden" name="subject_id" value="<?php echo $subject['subject_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($subjects) === 0): ?>
        <div class="card-body text-center text-muted">
            <p>No subjects found.</p>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_subject">

                    <div class="mb-3">
                        <label for="addName" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="addName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="addFacultyId" class="form-label">Faculty</label>
                        <select class="form-select" id="addFacultyId" name="faculty_id" required>
                            <option value="">Select Faculty</option>
                            <?php foreach ($facultyUsers as $faculty): ?>
                                <option value="<?php echo $faculty['user_id']; ?>">
                                    <?php echo htmlspecialchars($faculty['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_subject">
                    <input type="hidden" name="subject_id" id="editSubjectId">

                    <div class="mb-3">
                        <label for="editName" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="editFacultyId" class="form-label">Faculty</label>
                        <select class="form-select" id="editFacultyId" name="faculty_id" required>
                            <option value="">Select Faculty</option>
                            <?php foreach ($facultyUsers as $faculty): ?>
                                <option value="<?php echo $faculty['user_id']; ?>">
                                    <?php echo htmlspecialchars($faculty['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadSubjectData(subject) {
    document.getElementById('editSubjectId').value = subject.subject_id;
    document.getElementById('editName').value = subject.name;
    document.getElementById('editFacultyId').value = subject.faculty_id;
}
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
