<?php
$pageTitle = 'Bulk Upload Questions';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireRole('faculty');

$user = currentUser();
$userId = $user['id'];
$message = '';
$error = '';
$uploadResults = null;

try {
    $stmt = $pdo->prepare('SELECT subject_id, name FROM subjects WHERE faculty_id = ? ORDER BY name ASC');
    $stmt->execute([$userId]);
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    $subjects = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $selectedSubjectId = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;

    if ($selectedSubjectId <= 0) {
        $error = 'Please select a subject.';
    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error. Please try again.';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];

        if (!preg_match('/\.csv$/i', $fileName)) {
            $error = 'Please upload a valid CSV file.';
        } else {
            $handle = fopen($file, 'r');
            $uploadResults = [
                'added' => 0,
                'skipped' => 0,
                'errors' => []
            ];

            $rowNum = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;

                if ($rowNum === 1) {
                    continue;
                }

                if (empty($row[0])) {
                    $uploadResults['skipped']++;
                    continue;
                }

                if (count($row) < 7) {
                    $uploadResults['skipped']++;
                    $uploadResults['errors'][] = "Row $rowNum: Missing columns (need 7)";
                    continue;
                }

                $question_text = trim($row[0]);
                $option_a = trim($row[1]);
                $option_b = trim($row[2]);
                $option_c = trim($row[3]);
                $option_d = trim($row[4]);
                $correct_option = strtoupper(trim($row[5]));
                $difficulty = strtolower(trim($row[6]));

                $validationErrors = [];

                if (empty($question_text)) {
                    $validationErrors[] = 'Question text is empty';
                }
                if (empty($option_a)) {
                    $validationErrors[] = 'Option A is empty';
                }
                if (empty($option_b)) {
                    $validationErrors[] = 'Option B is empty';
                }
                if (empty($option_c)) {
                    $validationErrors[] = 'Option C is empty';
                }
                if (empty($option_d)) {
                    $validationErrors[] = 'Option D is empty';
                }
                if (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
                    $validationErrors[] = "Correct option must be A, B, C, or D (got: $correct_option)";
                }
                if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
                    $validationErrors[] = "Difficulty must be easy, medium, or hard (got: $difficulty)";
                }

                if (!empty($validationErrors)) {
                    $uploadResults['skipped']++;
                    $uploadResults['errors'][] = "Row $rowNum: " . implode('; ', $validationErrors);
                    continue;
                }

                try {
                    $stmt = $pdo->prepare('INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $selectedSubjectId,
                        $question_text,
                        $option_a,
                        $option_b,
                        $option_c,
                        $option_d,
                        $correct_option,
                        $difficulty
                    ]);
                    $uploadResults['added']++;
                } catch (PDOException $e) {
                    $uploadResults['skipped']++;
                    $uploadResults['errors'][] = "Row $rowNum: Database error - " . htmlspecialchars($e->getMessage());
                }
            }

            fclose($handle);

            if ($uploadResults['added'] > 0 || $uploadResults['skipped'] > 0) {
                if ($uploadResults['added'] > 0) {
                    $message = $uploadResults['added'] . ' question(s) added successfully!';
                    if ($uploadResults['skipped'] > 0) {
                        $message .= ' ' . $uploadResults['skipped'] . ' row(s) skipped due to errors.';
                    }
                } else {
                    $error = 'No questions were added. All rows had errors.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-lg-12">
        <h2 class="mb-1">
            <i class="bi bi-cloud-upload"></i> Bulk Upload Questions
        </h2>
        <p class="text-muted">Upload multiple questions at once using a CSV file</p>
    </div>
</div>

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

<?php if ($uploadResults !== null): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Upload Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-2">
                        <strong style="color: #10b981;">
                            <i class="bi bi-check-circle"></i> Questions Added:
                        </strong>
                        <?php echo $uploadResults['added']; ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <p class="mb-2">
                        <strong style="color: #f59e0b;">
                            <i class="bi bi-exclamation-triangle"></i> Rows Skipped:
                        </strong>
                        <?php echo $uploadResults['skipped']; ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($uploadResults['errors'])): ?>
                <div class="mt-3">
                    <h6>Error Details:</h6>
                    <div class="alert alert-warning" style="max-height: 300px; overflow-y: auto;">
                        <ul class="mb-0">
                            <?php foreach ($uploadResults['errors'] as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Upload CSV File</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="subject_id" class="form-label">Select Subject</label>
                        <select class="form-select" id="subject_id" name="subject_id" required>
                            <option value="">Choose a subject...</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['subject_id']; ?>">
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1">All questions in this CSV will be added to the selected subject</small>
                    </div>

                    <div class="mb-3">
                        <label for="csv_file" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <small class="text-muted d-block mt-1">Select a .csv file with 7 columns (see format below)</small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-cloud-upload"></i> Upload CSV
                        </button>
                        <a href="/faculty/questions.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Questions
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">CSV Format Guide</h5>
            </div>
            <div class="card-body">
                <p class="small mb-3">
                    Your CSV file must have exactly <strong>7 columns</strong> (no header row will be skipped, include headers):
                </p>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead style="background-color: #f3f4f6;">
                            <tr>
                                <th style="font-weight: 600;">Column</th>
                                <th style="font-weight: 600;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>1</strong></td>
                                <td>Question text</td>
                            </tr>
                            <tr>
                                <td><strong>2</strong></td>
                                <td>Option A</td>
                            </tr>
                            <tr>
                                <td><strong>3</strong></td>
                                <td>Option B</td>
                            </tr>
                            <tr>
                                <td><strong>4</strong></td>
                                <td>Option C</td>
                            </tr>
                            <tr>
                                <td><strong>5</strong></td>
                                <td>Option D</td>
                            </tr>
                            <tr>
                                <td><strong>6</strong></td>
                                <td>Correct option (A/B/C/D)</td>
                            </tr>
                            <tr>
                                <td><strong>7</strong></td>
                                <td>Difficulty (easy/medium/hard)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="small mb-3">
                    <strong>Example CSV content:</strong>
                </p>
                <div style="background-color: #f9fafb; padding: 1rem; border-radius: 0.5rem; font-family: monospace; font-size: 0.75rem; overflow-x: auto;">
                    <code>What is 2+2?,"A: 3","B: 4","C: 5","D: 6",B,easy<br>
What is the capital of France?,"A: London","B: Paris","C: Berlin","D: Madrid",B,easy<br>
Explain quantum entanglement.,"A: False correlation","B: Spooky action at a distance","C: Time travel","D: Parallel universes",B,hard</code>
                </div>

                <div class="mt-3">
                    <a href="#" id="downloadSampleBtn" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-download"></i> Download Sample CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('downloadSampleBtn').addEventListener('click', function(e) {
    e.preventDefault();

    const csvContent = `question_text,option_a,option_b,option_c,option_d,correct_option,difficulty
"What is 2+2?","A: 3","B: 4","C: 5","D: 6",B,easy
"What is the capital of France?","A: London","B: Paris","C: Berlin","D: Madrid",B,easy
"Which planet is closest to the sun?","A: Venus","B: Mercury","C: Earth","D: Mars",B,medium
"What is the largest ocean on Earth?","A: Atlantic","B: Indian","C: Pacific","D: Arctic",C,easy
"Who wrote Romeo and Juliet?","A: Jane Austen","B: William Shakespeare","C: Charles Dickens","D: Mark Twain",B,medium
"What is the chemical symbol for Gold?","A: Go","B: Gd","C: Au","D: Ag",C,medium
"Explain quantum entanglement in simple terms.","A: Particles connected by secret signals","B: Correlated states that seem instantaneous","C: Impossible to achieve in practice","D: Only works for electrons",B,hard
"Calculate the derivative of x^3","A: 3x^2","B: 3x","C: x^2","D: x^3/3",A,hard`;

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'sample_questions.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
