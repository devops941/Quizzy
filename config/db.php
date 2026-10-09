<?php
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'quizzy';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_charset = 'utf8mb4';

// Suppress errors in production
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', 0);
    error_reporting(0);
}

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=$db_charset",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
