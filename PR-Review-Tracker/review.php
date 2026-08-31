<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current_user = require_login();

if (!in_array($current_user['role'], ['admin', 'reviewer'], true)) {
    flash_set('danger', 'Only reviewers and admins can submit reviews.');
    redirect('prs.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('prs.php');
}

verify_csrf();

$prId = (int) ($_POST['pr_id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$comment = trim($_POST['comment'] ?? '');

if (!in_array($decision, ['approved', 'changes', 'rejected'], true)) {
    flash_set('danger', 'Invalid review decision.');
    redirect('pr_view.php?id=' . $prId);
}

$stmt = db()->prepare('SELECT * FROM pull_requests WHERE id = ?');
$stmt->execute([$prId]);
$pr = $stmt->fetch();

if (!$pr || $pr['state'] !== 'open') {
    flash_set('danger', 'This pull request is not open for review.');
    redirect('prs.php');
}

db()->prepare(
    'INSERT INTO reviews (pr_id, reviewer_id, decision, comment) VALUES (?, ?, ?, ?)'
)->execute([$prId, $current_user['id'], $decision, $comment]);

// Optionally push the decision back to GitHub (best-effort; needs repo write token).
$gh = set_github_review($pr, $decision, $comment);

// Notify admins/reviewers and send an alert.
$label = $decision === 'approved' ? 'Approved' : ($decision === 'changes' ? 'Requested changes' : 'Rejected');
$notifType = $decision === 'approved' ? 'success' : ($decision === 'changes' ? 'warning' : 'danger');
notify_all_admins_and_reviewers(
    $current_user['name'] . ' ' . $label . ' PR #' . $pr['github_pr_number'] . ' (' . $pr['title'] . ')',
    $notifType,
    $prId
);

$msg = "Review submitted: $label.";
if (!$gh['ok']) {
    $msg .= ' ' . $gh['message'];
}
flash_set('success', $msg);
redirect('pr_view.php?id=' . $prId);
