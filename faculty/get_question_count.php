<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('faculty');

header('Content-Type: application/json');

$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$user = currentUser();

if ($subject_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid subject ID']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as count FROM questions q
        INNER JOIN subjects s ON q.subject_id = s.subject_id
        WHERE q.subject_id = ? AND s.faculty_id = ?
    ');
    $stmt->execute([$subject_id, $user['id']]);
    $result = $stmt->fetch();

    echo json_encode(['count' => $result['count']]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
