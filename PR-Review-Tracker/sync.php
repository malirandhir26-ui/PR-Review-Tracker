<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/github.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf();

$repoId = (int) ($_POST['repo_id'] ?? 0);

if ($repoId) {
    $stmt = db()->prepare('SELECT * FROM repositories WHERE id = ?');
    $stmt->execute([$repoId]);
    $repo = $stmt->fetch();
    if (!$repo) {
        flash_set('danger', 'Repository not found.');
        redirect('repos.php');
    }
    $result = github_sync_repository($repo);
    flash_set($result['ok'] ? 'success' : 'danger', $result['message']);
} else {
    $result = github_sync_all();
    flash_set($result['ok'] ? 'success' : 'danger', $result['message']);
}

redirect('repos.php');
