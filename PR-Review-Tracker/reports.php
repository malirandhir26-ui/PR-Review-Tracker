<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current_user = require_login();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $prs = fetch_prs();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pr-review-report-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['PR Number', 'Title', 'Repository', 'Author', 'Status', 'Last Decision', 'Created', 'Last Activity']);
    foreach ($prs as $pr) {
        $status = pr_status($pr, $pr['latest_decision']);
        fputcsv($out, [
            $pr['github_pr_number'],
            $pr['title'],
            $pr['repo_full_name'],
            $pr['author'],
            $status['label'],
            $pr['latest_decision'] ?: 'none',
            $pr['created_at'],
            $pr['last_activity_at'],
        ]);
    }
    fclose($out);
    exit;
}

// PDF export (simple printable HTML routed through a print-friendly page)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $prs = fetch_prs();
    $prsStatus = $_GET['status'] ?? 'all';
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>PR Review Report</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 20px; } h2 { font-size: 15px; margin-top: 22px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; font-size: 10px; }
        th { background: #f0f0f0; }
        .brand { color: #555; font-size: 11px; }
        .foot { margin-top: 24px; color: #777; font-size: 10px; }
    </style></head><body>
    <h1>PR Review Report</h1>
    <div class="brand">Generated <?= date('d M Y H:i') ?> — Status: <?= e(ucfirst($prsStatus)) ?></div>
    <h2>Pull Requests</h2>
    <table><thead><tr><th>#</th><th>Title</th><th>Repo</th><th>Author</th><th>Status</th><th>Decision</th><th>Created</th><th>Last activity</th></tr></thead>
    <tbody>
    <?php foreach ($prs as $pr): $s = pr_status($pr, $pr['latest_decision']); ?>
        <tr>
            <td><?= (int) $pr['github_pr_number'] ?></td>
            <td><?= e($pr['title']) ?></td>
            <td><?= e($pr['repo_name']) ?></td>
            <td><?= e($pr['author']) ?></td>
            <td><?= $s['label'] ?></td>
            <td><?= $pr['latest_decision'] ? e($pr['latest_decision']) : 'none' ?></td>
            <td><?= $pr['created_at'] ? e(date('d M Y', strtotime($pr['created_at']))) : '' ?></td>
            <td><?= $pr['last_activity_at'] ? e(date('d M Y', strtotime($pr['last_activity_at']))) : '' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table>
    <div class="foot"><?= e(APP_NAME) ?> — Automated Pull Request Review Monitoring System</div>
    </body></html>
    <?php
    exit;
}

$status = $_GET['status'] ?? 'all';
$prs = fetch_prs(null, $status);

$perReviewer = db()->query('
    SELECT u.name, u.role,
           COUNT(rv.id) AS total_reviews,
           SUM(rv.decision = "approved") AS approved,
           SUM(rv.decision = "changes") AS changes,
           SUM(rv.decision = "rejected") AS rejected
    FROM users u LEFT JOIN reviews rv ON rv.reviewer_id = u.id
    GROUP BY u.id ORDER BY total_reviews DESC
')->fetchAll();

$byRepo = db()->query('
    SELECT r.name,
           SUM(pr.state = "open") AS open_prs,
           SUM(pr.state = "closed") AS closed_prs
    FROM repositories r LEFT JOIN pull_requests pr ON pr.repo_id = r.id
    GROUP BY r.id ORDER BY r.name
')->fetchAll();

$byDecision = db()->query('SELECT decision, COUNT(*) c FROM reviews GROUP BY decision')->fetchAll();
$recentReviews = db()->query('
    SELECT rv.*, u.name AS reviewer, pr.github_pr_number, pr.title AS pr_title
    FROM reviews rv
    JOIN users u ON u.id = rv.reviewer_id
    JOIN pull_requests pr ON pr.id = rv.pr_id
    ORDER BY rv.reviewed_at DESC LIMIT 15
')->fetchAll();

$page_title = 'Reports';
require __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Pull Request Summary</span>
        <div class="d-flex gap-2">
            <a href="<?= base_url('reports.php?export=pdf&status=' . e($status)) ?>" class="btn btn-sm btn-danger" target="_blank">Export PDF</a>
            <a href="<?= base_url('reports.php?export=csv') ?>" class="btn btn-sm btn-success">Export CSV</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr><th>PR #</th><th>Title</th><th>Repo</th><th>Author</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($prs as $pr): ?>
                    <?php $s = pr_status($pr, $pr['latest_decision']); ?>
                    <tr>
                        <td><a href="<?= base_url('pr_view.php?id=' . $pr['id']) ?>">#<?= $pr['github_pr_number'] ?></a></td>
                        <td><?= e(mb_strimwidth($pr['title'], 0, 60, '...')) ?></td>
                        <td><?= e($pr['repo_name']) ?></td>
                        <td><?= e($pr['author']) ?></td>
                        <td><span class="badge <?= $s['badge'] ?>"><?= $s['label'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$prs): ?><tr><td colspan="5" class="text-center text-muted py-3">No pull requests.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Reviews by Reviewer</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Reviewer</th><th>Role</th><th>Approved</th><th>Changes</th><th>Rejected</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($perReviewer as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><?= e($row['role']) ?></td>
                            <td class="text-success fw-semibold"><?= (int) $row['approved'] ?></td>
                            <td class="text-warning fw-semibold"><?= (int) $row['changes'] ?></td>
                            <td class="text-danger fw-semibold"><?= (int) $row['rejected'] ?></td>
                            <td><?= (int) $row['total_reviews'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">PRs by Repository</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Repository</th><th>Open</th><th>Closed</th></tr></thead>
                    <tbody>
                    <?php foreach ($byRepo as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><span class="badge bg-info"><?= (int) $row['open_prs'] ?></span></td>
                            <td><span class="badge bg-secondary"><?= (int) $row['closed_prs'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Decision Breakdown</div>
            <div class="card-body">
                <canvas id="decisionChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Recent Review Activity</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>PR</th><th>Reviewer</th><th>Decision</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentReviews as $r): ?>
                        <tr>
                            <td><a href="<?= base_url('pr_view.php?id=' . $r['pr_id']) ?>">#<?= (int) $r['github_pr_number'] ?></a></td>
                            <td><?= e($r['reviewer']) ?></td>
                            <td>
                                <?php $b = $r['decision'] === 'approved' ? 'bg-success' : ($r['decision'] === 'changes' ? 'bg-warning text-dark' : 'bg-danger'); ?>
                                <span class="badge <?= $b ?>"><?= ucfirst(e($r['decision'])) ?></span>
                            </td>
                            <td><?= time_ago($r['reviewed_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentReviews): ?><tr><td colspan="4" class="text-center text-muted py-3">No reviews yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('decisionChart');
    if (!el || !window.Chart) return;
    var counts = { approved: 0, changes: 0, rejected: 0 };
    <?php foreach ($byDecision as $d): ?>
        counts['<?= $d['decision'] ?>'] = <?= (int) $d['c'] ?>;
    <?php endforeach; ?>
    var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    new Chart(el, {
        type: 'pie',
        data: {
            labels: ['Approved', 'Changes', 'Rejected'],
            datasets: [{
                data: [counts.approved, counts.changes, counts.rejected],
                backgroundColor: ['#198754', '#ffc107', '#dc3545']
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { color: isDark ? '#e9ecef' : '#495057' } } }
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
