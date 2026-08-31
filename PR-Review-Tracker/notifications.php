<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current_user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$current_user['id']]);
    flash_set('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

$notifications = latest_notifications($current_user['id'], 50);
$unread = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unread->execute([$current_user['id']]);

$page_title = 'Notifications';
require __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Notifications <span class="badge bg-primary ms-1"><?= (int) $unread->fetchColumn() ?> unread</span></span>
        <?php if ($notifications): ?>
            <form method="post" action="<?= base_url('notifications.php') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-primary">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (!$notifications): ?>
            <p class="text-center text-muted py-5 mb-0">No notifications yet.</p>
        <?php endif; ?>
        <?php foreach ($notifications as $n): ?>
            <div class="border-bottom p-3 <?= $n['is_read'] ? 'bg-body' : 'bg-light' ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <?php
                            $icon = match ($n['type']) {
                                'success' => '<span class="text-success">✔</span>',
                                'danger'  => '<span class="text-danger">●</span>',
                                'warning' => '<span class="text-warning">▲</span>',
                                default   => '<span class="text-info">ℹ</span>',
                            };
                        ?>
                        <span><?= $icon ?></span>
                        <span class="ms-1"><?= e($n['message']) ?></span>
                        <?php if ($n['github_pr_number']): ?>
                            <a href="<?= base_url('pr_view.php?id=' . (int) $n['pr_id']) ?>" class="ms-2 small">View PR #<?= (int) $n['github_pr_number'] ?></a>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted text-nowrap ms-3"><?= time_ago($n['created_at']) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
