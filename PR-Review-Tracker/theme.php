<?php
// Theme preference toggle. Sets the user's theme and returns the new state.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current_user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    db()->prepare('UPDATE users SET theme = ? WHERE id = ?')->execute([$theme, $current_user['id']]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'theme' => $theme]);
    exit;
}

redirect('index.php');
