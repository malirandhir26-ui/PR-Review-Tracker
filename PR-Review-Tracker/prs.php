<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current_user = require_login();

$repoId = (int) ($_GET['repo'] ?? 0);
$status = $_GET['status'] ?? 'all';
$author = trim($_GET['author'] ?? '');
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'created';
$dir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$repos = db()->query('SELECT id, name FROM repositories ORDER BY name')->fetchAll();
$prs = fetch_prs($repoId ?: null, $status, $author ?: null, $search);

// Sorting
$sortable = ['created' => 'created_at', 'number' => 'github_pr_number', 'repo' => 'repo_name', 'author' => 'author', 'status' => null];
if (isset($sortable[$sort])) {
    $col = $sortable[$sort];
    if ($sort === 'status') {
        usort($prs, function ($a, $b) use ($dir) {
            $ka = pr_status($a, $a['latest_decision'])['key'];
            $kb = pr_status($b, $b['latest_decision'])['key'];
            $r = strcmp($ka, $kb);
            return $dir === 'asc' ? $r : -$r;
        });
    } else {
        usort($prs, function ($a, $b) use ($col, $dir) {
            $r = strcmp((string) ($a[$col] ?? ''), (string) ($b[$col] ?? ''));
            return $dir === 'asc' ? $r : -$r;
        });
    }
}
// Always secondary-sort by newest number for stable lists
if ($sort !== 'number') {
    usort($prs, function ($a, $b) { return (int) $b['github_pr_number'] - (int) $a['github_pr_number']; });
}

// Pagination
$total = count($prs);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$prs = array_slice($prs, ($page - 1) * $perPage, $perPage);

$queryBase = http_build_query(array_filter(['repo' => $repoId, 'status' => $status, 'author' => $author, 'search' => $search]));
$page_title = 'Pull Requests';
require __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Pull Requests (<?= $total ?>)</span>
        <div>
            <a href="<?= base_url('prs.php?status=stale') ?>" class="btn btn-sm btn-outline-warning">Stale</a>
            <a href="<?= base_url('prs.php?status=blocked') ?>" class="btn btn-sm btn-outline-danger">Blocked</a>
            <a href="<?= base_url('prs.php') ?>" class="btn btn-sm btn-outline-secondary">All</a>
        </div>
    </div>

    <div class="card-body border-bottom bg-light">
        <form method="get" action="<?= base_url('prs.php') ?>" class="row g-2">
            <div class="col-md-3">
                <select name="repo" class="form-select form-select-sm">
                    <option value="">All repositories</option>
                    <?php foreach ($repos as $repo): ?>
                        <option value="<?= $repo['id'] ?>" <?= $repoId === (int) $repo['id'] ? 'selected' : '' ?>><?= e($repo['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <?php foreach (['all' => 'All statuses', 'open' => 'Open', 'stale' => 'Stale', 'blocked' => 'Blocked', 'closed' => 'Closed'] as $k => $label): ?>
                        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title / author / number..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="<?= base_url('prs.php') ?>" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-sortable>
            <thead class="table-light">
                <tr>
                    <th data-sort="number">PR</th>
                    <th data-sort="repo">Repository</th>
                    <th data-sort="author">Author</th>
                    <th data-sort="status">Status</th>
                    <th>Last review</th>
                    <th data-sort="created" data-dir="desc">Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($prs as $pr): ?>
                <?php $statusBadge = pr_status($pr, $pr['latest_decision']); ?>
                <tr>
                    <td>
                        <a href="<?= base_url('pr_view.php?id=' . $pr['id']) ?>" class="fw-semibold">#<?= $pr['github_pr_number'] ?></a>
                        <div class="small text-muted"><?= e(mb_strimwidth($pr['title'], 0, 70, '...')) ?></div>
                    </td>
                    <td><?= e($pr['repo_name']) ?></td>
                    <td><?= e($pr['author']) ?></td>
                    <td><span class="badge <?= $statusBadge['badge'] ?>"><?= $statusBadge['label'] ?></span></td>
                    <td><?= $pr['latest_decision'] ? e($pr['latest_decision']) : '<span class="text-muted">none</span>' ?></td>
                    <td><?= time_ago($pr['created_at']) ?></td>
                    <td><a href="<?= base_url('pr_view.php?id=' . $pr['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$prs): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No pull requests match your filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <span class="small text-muted">
            Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= $total ?>
        </span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= base_url('prs.php?' . $queryBase . '&sort=' . $sort . '&dir=' . $dir . '&page=' . ($page - 1)) ?>">&laquo;</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= base_url('prs.php?' . $queryBase . '&sort=' . $sort . '&dir=' . $dir . '&page=' . $i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= base_url('prs.php?' . $queryBase . '&sort=' . $sort . '&dir=' . $dir . '&page=' . ($page + 1)) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
