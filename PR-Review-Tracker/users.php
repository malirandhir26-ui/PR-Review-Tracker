<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current_user = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'role' && in_array($_POST['role'] ?? '', ['admin', 'reviewer', 'developer'], true)) {
        if ($userId !== (int) $current_user['id']) {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$_POST['role'], $userId]);
            flash_set('success', 'User role updated.');
        } else {
            flash_set('warning', 'You cannot change your own role.');
        }
    } elseif ($action === 'delete') {
        if ($userId !== (int) $current_user['id']) {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            flash_set('success', 'User removed.');
        } else {
            flash_set('warning', 'You cannot delete your own account.');
        }
    }
    redirect('users.php');
}

$users = db()->query('SELECT * FROM users ORDER BY role, name')->fetchAll();

$page_title = 'Users';
require __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Manage Users (<?= count($users) ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>GitHub username</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td class="fw-semibold"><?= e($user['name']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= $user['github_username'] ? e($user['github_username']) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <form method="post" action="<?= base_url('users.php') ?>" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <select name="role" class="form-select form-select-sm" <?= $user['id'] === $current_user['id'] ? 'disabled' : '' ?>>
                                <?php foreach (['admin', 'reviewer', 'developer'] as $r): ?>
                                    <option value="<?= $r ?>" <?= $user['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-primary" <?= $user['id'] === $current_user['id'] ? 'disabled' : '' ?>>Save</button>
                        </form>
                    </td>
                    <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                    <td class="text-end">
                        <?php if ($user['id'] !== $current_user['id']): ?>
                        <form method="post" action="<?= base_url('users.php') ?>" class="d-inline" onsubmit="return confirm('Delete this user?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
