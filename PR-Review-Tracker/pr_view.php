<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$current_user = require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM pull_requests WHERE id = ?');
$stmt->execute([$id]);
$pr = $stmt->fetch();

if (!$pr) {
    flash_set('danger', 'Pull request not found.');
    redirect('prs.php');
}

$repo = db()->prepare('SELECT * FROM repositories WHERE id = ?');
$repo->execute([$pr['repo_id']]);
$repo = $repo->fetch();

$reviews = db()->prepare('
    SELECT rv.*, u.name AS reviewer_name
    FROM reviews rv JOIN users u ON u.id = rv.reviewer_id
    WHERE rv.pr_id = ? ORDER BY rv.reviewed_at ASC
');
$reviews->execute([$id]);
$reviews = $reviews->fetchAll();

$latestDecision = $reviews ? $reviews[count($reviews) - 1]['decision'] : null;
$status = pr_status($pr, $latestDecision);
$canReview = in_array($current_user['role'], ['admin', 'reviewer'], true) && $pr['state'] === 'open';

$page_title = 'PR #' . $pr['github_pr_number'];
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">#<?= $pr['github_pr_number'] ?> <?= e($pr['title']) ?></h4>
                        <div class="text-muted small">
                            <span class="badge <?= $status['badge'] ?>"><?= $status['label'] ?></span>
                            by <strong><?= e($pr['author']) ?></strong> in
                            <a href="<?= e($pr['url']) ?>" target="_blank"><?= e($repo['repo_full_name']) ?></a>
                        </div>
                    </div>
                    <?php if ($pr['url']): ?>
                        <a href="<?= e($pr['url']) ?>" target="_blank" class="btn btn-sm btn-outline-dark">Open on GitHub</a>
                    <?php endif; ?>
                </div>
                <hr>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?= date('d M Y, h:i A', strtotime($pr['created_at'])) ?></dd>
                    <dt class="col-sm-4">Last activity</dt><dd class="col-sm-8"><?= $pr['last_activity_at'] ? date('d M Y, h:i A', strtotime($pr['last_activity_at'])) : '—' ?></dd>
                    <dt class="col-sm-4">Repository</dt><dd class="col-sm-8"><?= e($repo['name']) ?></dd>
                    <dt class="col-sm-4">Last sync</dt><dd class="col-sm-8"><?= $repo['synced_at'] ? time_ago($repo['synced_at']) : 'never' ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($canReview): ?>
        <div class="card shadow-sm mb-3 border-primary">
            <div class="card-header fw-semibold">Submit your review</div>
            <div class="card-body">
                <form method="post" action="<?= base_url('review.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pr_id" value="<?= $pr['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Decision</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="decision" value="approved" class="btn btn-success">Approve</button>
                            <button type="submit" name="decision" value="changes" class="btn btn-warning">Request changes</button>
                            <button type="submit" name="decision" value="rejected" class="btn btn-danger">Reject</button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Comment</label>
                        <textarea name="comment" class="form-control" rows="3" placeholder="Add a review comment (optional)"></textarea>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($pr['state'] === 'open'): ?>
            <div class="alert alert-info">
                Your role is <strong><?= e($current_user['role']) ?></strong>. Only reviewers and admins can submit reviews.
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Review History (<?= count($reviews) ?>)</div>
            <div class="card-body p-0">
                <?php if (!$reviews): ?>
                    <p class="text-center text-muted py-4 mb-0">No reviews yet.</p>
                <?php endif; ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong><?= e($review['reviewer_name']) ?></strong>
                            <?php
                                $b = $review['decision'] === 'approved' ? 'bg-success' : ($review['decision'] === 'changes' ? 'bg-warning text-dark' : 'bg-danger');
                            ?>
                            <span class="badge <?= $b ?>"><?= ucfirst(e($review['decision'])) ?></span>
                        </div>
                        <div class="small text-muted mt-1"><?= date('d M Y, h:i A', strtotime($review['reviewed_at'])) ?></div>
                        <?php if ($review['comment']): ?>
                            <p class="small mt-2 mb-0"><?= nl2br(e($review['comment'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
