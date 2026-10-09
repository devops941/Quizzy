<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function isLoggedIn(): bool {
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'msg' => $msg
    ];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function requireRole(string|array $roles): void {
    if (!isLoggedIn()) {
        setFlash('danger', 'Please login first');
        header('Location: /login.php');
        exit;
    }

    $rolesArray = is_string($roles) ? [$roles] : $roles;
    $userRole = currentUser()['role'] ?? null;

    if (!in_array($userRole, $rolesArray, true)) {
        setFlash('danger', 'Access denied');
        header('Location: /login.php');
        exit;
    }
}
