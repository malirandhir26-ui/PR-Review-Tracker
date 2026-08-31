<?php
// Stale PR reminder script.
// Run it periodically (cron / Task Scheduler / web host cron):
//   php api/reminders.php
// Sends a summary email (via PHP mail()) about stale and blocked PRs to
// reviewers and admins. If mail() is unavailable, the script just logs.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$prs = fetch_prs();
$stale = array_values(array_filter($prs, fn($p) => pr_status($p, $p['latest_decision'])['key'] === 'stale'));
$blocked = array_values(array_filter($prs, fn($p) => pr_status($p, $p['latest_decision'])['key'] === 'blocked'));

if (!$stale && !$blocked) {
    echo "[" . date('Y-m-d H:i:s') . "] No stale or blocked PRs.\n";
    exit;
}

$lines = [];
$lines[] = 'PR Review Tracker - Daily review alert';
$lines[] = str_repeat('-', 50);

if ($stale) {
    $lines[] = 'STALE PRs (no activity for > ' . STALE_DAYS . ' days):';
    foreach ($stale as $pr) {
        $lines[] = '  #' . $pr['github_pr_number'] . ' ' . $pr['title'] . ' (' . $pr['repo_name'] . ', last activity ' . time_ago($pr['last_activity_at']) . ')';
    }
    $lines[] = '';
}
if ($blocked) {
    $lines[] = 'BLOCKED PRs (waiting on changes):';
    foreach ($blocked as $pr) {
        $lines[] = '  #' . $pr['github_pr_number'] . ' ' . $pr['title'] . ' (' . $pr['repo_name'] . ')';
    }
}

$body = implode("\n", $lines);
$recipients = db()->query("SELECT email FROM users WHERE role IN ('admin','reviewer')")->fetchAll();
$subject = '[' . APP_NAME . '] ' . (count($stale) + count($blocked)) . ' PRs need attention';

foreach ($recipients as $r) {
    $sent = @mail($r['email'], $subject, $body, 'From: ' . APP_NAME . ' <no-reply@localhost>');
    echo "[" . date('Y-m-d H:i:s') . "] " . ($sent ? 'Sent to ' : 'Failed for ') . $r['email'] . "\n";
}
