<?php
// Demo data seeder - inserts sample repositories, PRs and reviews so the
// dashboard looks alive even without a GitHub account.
// Usage:  php seed_demo.php   (then delete this file, or keep for re-seeding)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$db = db();

$adminId = (int) $db->query("SELECT id FROM users WHERE email = 'admin@example.com'")->fetchColumn();

$repos = [
    ['Core App', 'acme-software/core-app'],
    ['Mobile App', 'acme-software/mobile-app'],
];

foreach ($repos as $i => [$name, $fullName]) {
    $db->prepare('INSERT INTO repositories (name, provider, repo_full_name, owner_id, synced_at)
                  VALUES (?, "github", ?, ?, NOW())')->execute([$name, $fullName, $adminId]);
}

$repoIds = $db->query('SELECT id, name FROM repositories ORDER BY id')->fetchAll();

$samplePrs = [
    ['Fix login redirect bug', 'rahul_dev', 3, 5],
    ['Add dark mode support', 'priya_sharma', 1, 12],
    ['Update payment gateway API', 'rahul_dev', 8, 0],
    ['Refactor database queries', 'amit_kumar', 5, 2],
    ['Add unit tests for auth module', 'sneha_patel', 1, 0],
    ['Improve loading spinner UI', 'priya_sharma', 10, 1],
    ['Migrate to PHP 8.5', 'amit_kumar', 2, 6],
    ['Fix memory leak in sync job', 'rahul_dev', 4, 0],
];

$daysAgo = fn(int $d): string => date('Y-m-d H:i:s', time() - $d * 86400);

$prIds = [];
$i = 0;
foreach ($samplePrs as [$title, $author, $days, $lastDays]) {
    $repo = $repoIds[$i % count($repoIds)];
    $i++;
    $number = $i;
    $db->prepare(
        'INSERT INTO pull_requests (repo_id, github_pr_number, title, author, url, state, last_activity_at, created_at)
         VALUES (?, ?, ?, ?, ?, "open", ?, ?)'
    )->execute([
        $repo['id'],
        $number,
        $title,
        $author,
        'https://github.com/' . $repo['name'] . '/pull/' . $number,
        $daysAgo($lastDays),
        $daysAgo($days),
    ]);
    $prIds[] = (int) $db->lastInsertId();
}

$reviewers = ['Admin', 'Vikram Reviews'];
foreach ($reviewers as $rname) {
    $exists = $db->prepare('SELECT id FROM users WHERE name = ?');
    $exists->execute([$rname]);
    if (!$exists->fetch()) {
        $db->prepare('INSERT INTO users (name, email, role, github_username, password_hash)
                      VALUES (?, ?, "reviewer", ?, ?)')
            ->execute([$rname, strtolower(str_replace(' ', '.', $rname)) . '@example.com', strtolower(str_replace(' ', '', $rname)), password_hash('review123', PASSWORD_DEFAULT)]);
    }
}

$reviewerStmt = $db->prepare('SELECT id FROM users WHERE name = ?');
$reviewerStmt->execute(['Vikram Reviews']);
$reviewerId = (int) $reviewerStmt->fetchColumn();

$db->prepare('INSERT INTO reviews (pr_id, reviewer_id, decision, comment) VALUES (?, ?, "changes", "Please fix the indentation and add a test for the edge case.")')->execute([$prIds[0], $reviewerId]);
$db->prepare('INSERT INTO reviews (pr_id, reviewer_id, decision, comment) VALUES (?, ?, "approved", "Looks good, well done!")')->execute([$prIds[1], $reviewerId]);
$db->prepare('INSERT INTO reviews (pr_id, reviewer_id, decision, comment) VALUES (?, ?, "changes", "Use prepared statements instead of string concatenation.")')->execute([$prIds[2], $reviewerId]);

echo "Demo data seeded.\n";
echo "Logins:\n";
echo "  Admin:    admin@example.com / admin123\n";
echo "  Reviewer: vikram.reviews@example.com / review123\n";
