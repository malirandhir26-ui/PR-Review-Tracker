<?php

require_once __DIR__ . '/db.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function current_user(): ?array
{
    start_session();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): array
{
    start_session();
    $user = current_user();
    if (!$user) {
        flash_set('warning', 'Please login to continue.');
        redirect('login.php');
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        flash_set('danger', 'You do not have permission to view that page.');
        redirect('index.php');
    }
    return $user;
}

function login_user(int $userId): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    start_session();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        exit('Invalid security token. Please go back and try again.');
    }
}

// Simple per-session brute-force throttle for login/registration.
// Allows MAX_ATTEMPTS within a window, then requires a short wait.
function throttle_check(string $key, int $max = 5, int $windowSec = 300): void
{
    start_session();
    $now = time();
    $bucket = $_SESSION['throttle'][$key] ?? ['count' => 0, 'first' => $now];

    if ($now - $bucket['first'] > $windowSec) {
        $bucket = ['count' => 0, 'first' => $now];
    }

    if ($bucket['count'] >= $max) {
        $wait = $windowSec - ($now - $bucket['first']);
        $_SESSION['throttle'][$key] = $bucket;
        http_response_code(429);
        exit("Too many attempts. Please wait " . max(1, (int) ceil($wait / 60)) . " minute(s) and try again.");
    }

    $bucket['count']++;
    $_SESSION['throttle'][$key] = $bucket;
}
