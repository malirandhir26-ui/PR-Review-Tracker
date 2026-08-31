<?php
// GitHub webhook receiver.
// Setup in GitHub: repo Settings -> Webhooks -> Add webhook
//   URL: https://your-site.com/api/webhook.php
//   Content type: application/json
//   Events: Pull requests
// Optionally protect it with a secret token set in config.php (WEBHOOK_SECRET).

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/github.php';

if (defined('WEBHOOK_SECRET') && WEBHOOK_SECRET !== '') {
    $sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $body = file_get_contents('php://input');
    $expected = 'sha256=' . hash_hmac('sha256', $body, WEBHOOK_SECRET);
    if (!hash_equals($expected, $sig)) {
        http_response_code(401);
        exit('Invalid signature.');
    }
}

$payload = json_decode(file_get_contents('php://input'), true);
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

if (in_array($event, ['pull_request', 'pull_request_review'], true)) {
    $repoName = $payload['repository']['full_name'] ?? null;
    if ($repoName) {
        $stmt = db()->prepare('SELECT * FROM repositories WHERE repo_full_name = ?');
        $stmt->execute([$repoName]);
        $repo = $stmt->fetch();
        if ($repo) {
            github_sync_repository($repo);
            echo 'Synced ' . $repoName . '.';
            exit;
        }
    }
}

echo 'No action taken.';
