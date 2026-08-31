<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/github.php';

$current_user = require_login();

// Optional background auto-sync when data is stale (kept lightweight).
$autoSynced = false;
if (defined('AUTO_SYNC') && AUTO_SYNC && is_stale_sync_needed(60)) {
    $repos = db()->query('SELECT * FROM repositories')->fetchAll();
    foreach ($repos as $repo) {
        github_sync_repository($repo);
    }
    $autoSynced = true;
}

$stats = dashboard_stats();
$prs = fetch_prs();
$repos = db()->query('SELECT id, name, repo_full_name, synced_at FROM repositories ORDER BY name')->fetchAll();
$myUsername = $current_user['github_username'];
$myPrs = $myUsername
    ? array_values(array_filter($prs, fn($p) => strtolower($p['author']) === strtolower($myUsername)))
    : [];
$stalePrs = array_values(array_filter($prs, fn($p) => pr_status($p, $p['latest_decision'])['key'] === 'stale'));
$blockedPrs = array_values(array_filter($prs, fn($p) => pr_status($p, $p['latest_decision'])['key'] === 'blocked'));
$chart = analytics_chart_data();

$page_title = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<?php if ($autoSynced): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        Data was auto-synced with GitHub. 
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card stat-card text-white bg-success">
            <div class="card-body">
                <div class="stat-number"><?= $stats['open'] ?></div>
                <div class="stat-label">Open PRs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card text-white bg-warning">
            <div class="card-body">
                <div class="stat-number"><?= $stats['stale'] ?></div>
                <div class="stat-label">Stale (&gt;<?= STALE_DAYS ?>d)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card text-white bg-danger">
            <div class="card-body">
                <div class="stat-number"><?= $stats['blocked'] ?></div>
                <div class="stat-label">Blocked</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card text-white bg-secondary">
            <div class="card-body">
                <div class="stat-number"><?= $stats['closed'] ?></div>
                <div class="stat-label">Closed</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card text-white bg-primary">
            <div class="card-body">
                <div class="stat-number"><?= $stats['reviews'] ?></div>
                <div class="stat-label">Reviews Given</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card stat-card text-white bg-info">
            <div class="card-body">
                <div class="stat-number"><?= $stats['avg_review_hours'] ?>h</div>
                <div class="stat-label">Avg Review Time</div>
            </div>
        </div>
    </div>
</div>

<?php if ($blockedPrs || $stalePrs): ?>
<div class="card shadow-sm border-danger mb-4" id="autoRefresh" data-seconds="60">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Needs Attention</span>
        <span class="small text-muted" id="refreshIndicator">Refreshes in 60s</span>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ($blockedPrs as $pr): ?>
            <a href="<?= base_url('pr_view.php?id=' . $pr['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span class="small"><span class="badge bg-danger me-2">Blocked</span><strong>#<?= $pr['github_pr_number'] ?></strong> <?= e(mb_strimwidth($pr['title'], 0, 60, '...')) ?></span>
                <span class="small text-muted"><?= e($pr['repo_name']) ?></span>
            </a>
        <?php endforeach; ?>
        <?php foreach (array_slice($stalePrs, 0, 5) as $pr): ?>
            <a href="<?= base_url('pr_view.php?id=' . $pr['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span class="small"><span class="badge bg-warning text-dark me-2">Stale</span><strong>#<?= $pr['github_pr_number'] ?></strong> <?= e(mb_strimwidth($pr['title'], 0, 60, '...')) ?></span>
                <span class="small text-muted"><?= time_ago($pr['last_activity_at']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">PR Status Overview</div>
            <div class="card-body">
                <canvas id="statusChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Activity (last 14 days)</div>
            <div class="card-body">
                <canvas id="activityChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Stale Pull Requests</span>
                <a href="<?= base_url('prs.php?status=stale') ?>" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$stalePrs): ?>
                    <p class="text-center text-muted py-4 mb-0">No stale PRs. Good job! </p>
                <?php else: ?>
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>PR</th><th>Repo</th><th>Author</th><th>Last activity</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($stalePrs, 0, 10) as $pr): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('pr_view.php?id=' . $pr['id']) ?>" class="fw-semibold">#<?= $pr['github_pr_number'] ?> <?= e(mb_strimwidth($pr['title'], 0, 60, '...')) ?></a>
                                </td>
                                <td><?= e($pr['repo_name']) ?></td>
                                <td><?= e($pr['author']) ?></td>
                                <td><span class="text-danger fw-semibold"><?= time_ago($pr['last_activity_at']) ?></span></td>
                                <td><span class="badge <?= pr_status($pr, $pr['latest_decision'])['badge'] ?>"><?= pr_status($pr, $pr['latest_decision'])['label'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Latest Pull Requests</span>
                <a href="<?= base_url('prs.php') ?>" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>PR</th><th>Repo</th><th>Author</th><th>Status</th><th>Age</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($prs, 0, 10) as $pr): ?>
                        <?php $status = pr_status($pr, $pr['latest_decision']); ?>
                        <tr>
                            <td><a href="<?= base_url('pr_view.php?id=' . $pr['id']) ?>" class="fw-semibold">#<?= $pr['github_pr_number'] ?> <?= e(mb_strimwidth($pr['title'], 0, 50, '...')) ?></a></td>
                            <td><?= e($pr['repo_name']) ?></td>
                            <td><?= e($pr['author']) ?></td>
                            <td><span class="badge <?= $status['badge'] ?>"><?= $status['label'] ?></span></td>
                            <td><?= time_ago($pr['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$prs): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No pull requests yet. Add a repository and click "Sync PRs".</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Repositories</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (!$repos): ?>
                        <li class="list-group-item text-muted">No repositories tracked yet.</li>
                    <?php endif; ?>
                    <?php foreach ($repos as $repo): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><?= e($repo['name']) ?></div>
                                <small class="text-muted"><?= e($repo['repo_full_name']) ?></small>
                            </div>
                            <span class="text-muted small">synced <?= time_ago($repo['synced_at']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card-footer d-grid">
                <a href="<?= base_url('repos.php') ?>" class="btn btn-sm btn-outline-primary">Manage repositories</a>
            </div>
        </div>

        <?php if ($current_user['role'] !== 'admin'): ?>
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">My Pull Requests</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (!$myPrs): ?>
                        <li class="list-group-item text-muted">No PRs authored by you.</li>
                    <?php endif; ?>
                    <?php foreach (array_slice($myPrs, 0, 8) as $pr): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="<?= base_url('pr_view.php?id=' . $pr['id']) ?>" class="small">#<?= $pr['github_pr_number'] ?> <?= e(mb_strimwidth($pr['title'], 0, 35, '...')) ?></a>
                            <span class="badge <?= pr_status($pr, $pr['latest_decision'])['badge'] ?>"><?= pr_status($pr, $pr['latest_decision'])['label'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var chart = <?= json_encode($chart) ?>;
    var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    var gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    var labelColor = isDark ? '#e9ecef' : '#495057';

    var statusEl = document.getElementById('statusChart');
    if (statusEl && window.Chart) {
        new Chart(statusEl, {
            type: 'doughnut',
            data: {
                labels: ['Open', 'Stale', 'Blocked', 'Closed'],
                datasets: [{
                    data: [chart.status.open, chart.status.stale, chart.status.blocked, chart.status.closed],
                    backgroundColor: ['#198754', '#ffc107', '#dc3545', '#6c757d']
                }]
            },
            options: {
                plugins: {
                    legend: { position: 'bottom', labels: { color: labelColor } }
                }
            }
        });
    }

    var activityEl = document.getElementById('activityChart');
    if (activityEl && window.Chart) {
        new Chart(activityEl, {
            type: 'line',
            data: {
                labels: chart.labels.map(function (d) { return d.slice(5); }),
                datasets: [
                    {
                        label: 'PRs created',
                        data: chart.prsPerDay,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25,135,84,0.15)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Reviews',
                        data: chart.reviewsPerDay,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.15)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                plugins: {
                    legend: { labels: { color: labelColor } }
                },
                scales: {
                    x: { ticks: { color: labelColor }, grid: { color: gridColor } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: labelColor }, grid: { color: gridColor } }
                }
            }
        });
    }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
