<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';

requireRole('admin');

$role = $_GET['role'] ?? '';
if ($role === 'faculty' || $role === 'admin') {
    header('Location: faculty.php');
} else {
    header('Location: students.php');
}
exit;
