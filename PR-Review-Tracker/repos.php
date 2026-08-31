<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current_user = require_login();
$isAdmin = $current_user['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($isAdmin && isset($_POST['action']) && $_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $fullName = trim($_POST['repo_full_name'] ?? '');
        $token = trim($_POST['sync_token'] ?? '');

        if ($name === '' || $fullName === '') {
            flash_set('danger', 'Repository name and "owner/repo" are required.');
        } else {
            db()->prepare(
                'INSERT INTO repositories (name, provider, repo_full_name, owner_id, sync_token) VALUES (?, "github", ?, ?, ?)'
            )->execute([$name, $fullName, $current_user['id'], $token ?: null]);
            flash_set('success', 'Repository "' . $name . '" added. Click Sync to fetch PRs.');
        }
    } elseif ($isAdmin && isset($_POST['action']) && $_POST['action'] === 'delete') {
        $repoId = (int) ($_POST['repo_id'] ?? 0);
        db()->prepare('DELETE FROM repositories WHERE id = ?')->execute([$repoId]);
        flash_set('success', 'Repository removed.');
    }

    redirect('repos.php');
}

$repos = db()->query(
    'SELECT r.*, u.name AS owner_name,
            (SELECT COUNT(*) FROM pull_requests pr WHERE pr.repo_id = r.id AND pr.state = "open") AS open_prs
     FROM repositories r JOIN users u ON u.id = r.owner_id
     ORDER BY r.name'
)->fetchAll();

$page_title = 'Repositories';
require __DIR__ . '/includes/header.php';
?>

<?php if ($isAdmin): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Add GitHub Repository</div>
    <div class="card-body">
        <form method="post" action="<?= base_url('repos.php') ?>" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="col-md-3">
                <input type="text" name="name" class="form-control" placeholder="Display name (e.g. Core App)" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="repo_full_name" class="form-control" placeholder="owner/repo (e.g. octocat/Hello-World)" required>
            </div>
            <div class="col-md-4">
                <input type="text" name="sync_token" class="form-control" placeholder="GitHub Personal Access Token (optional)">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary">Add repository</button>
            </div>
            <div class="col-12">
                <div class="form-text">
                    Token guide: GitHub → Settings → Developer settings → Personal access tokens → Generate new token.
                    Give it the <code>repo</code> scope (read). Public repos work without a token.
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Tracked Repositories (<?= count($repos) ?>)</span>
        <form method="post" action="<?= base_url('sync.php') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-success">Sync all PRs now</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Repository</th>
                    <th>Provider</th>
                    <th>Open PRs</th>
                    <th>Last synced</th>
                    <th>Added by</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($repos as $repo): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($repo['name']) ?></div>
                        <small class="text-muted"><?= e($repo['repo_full_name']) ?></small>
                    </td>
                    <td><span class="badge bg-dark"><?= e($repo['provider']) ?></span></td>
                    <td><span class="badge bg-info"><?= $repo['open_prs'] ?></span></td>
                    <td><?= $repo['synced_at'] ? time_ago($repo['synced_at']) : '<span class="text-muted">never</span>' ?></td>
                    <td><?= e($repo['owner_name']) ?></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <form method="post" action="<?= base_url('sync.php') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="repo_id" value="<?= $repo['id'] ?>">
                                <button class="btn btn-outline-success">Sync</button>
                            </form>
                            <?php if ($isAdmin): ?>
                            <form method="post" action="<?= base_url('repos.php') ?>" class="d-inline" onsubmit="return confirm('Delete this repository and its PRs?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="repo_id" value="<?= $repo['id'] ?>">
                                <button class="btn btn-outline-danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$repos): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No repositories yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
