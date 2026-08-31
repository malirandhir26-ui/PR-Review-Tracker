<?php

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $base = (defined('BASE_URL') && BASE_URL !== '') ? rtrim(BASE_URL, '/') : '';
    if ($base === '') {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return 'never';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return 'unknown';
    }
    $diff = time() - $ts;
    $units = [
        31536000 => 'year',
        2592000  => 'month',
        604800   => 'week',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
    ];
    foreach ($units as $seconds => $label) {
        $value = floor($diff / $seconds);
        if ($value >= 1) {
            return $value . ' ' . $label . ($value > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function flash_set(string $type, string $message): void
{
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function pr_status(array $pr, ?string $latestDecision): array
{
    if ($pr['state'] !== 'open') {
        return ['key' => 'closed', 'label' => 'Closed', 'badge' => 'bg-secondary'];
    }
    if ($latestDecision === 'changes') {
        return ['key' => 'blocked', 'label' => 'Blocked', 'badge' => 'bg-danger'];
    }
    if ($pr['last_activity_at'] && (time() - strtotime($pr['last_activity_at'])) > STALE_DAYS * 86400) {
        return ['key' => 'stale', 'label' => 'Stale', 'badge' => 'bg-warning text-dark'];
    }
    return ['key' => 'open', 'label' => 'Open', 'badge' => 'bg-success'];
}

function fetch_prs(?int $repoId = null, ?string $status = null, ?string $author = null, string $search = ''): array
{
    $sql = "
        SELECT pr.*, r.name AS repo_name, r.repo_full_name,
               (SELECT decision FROM reviews WHERE pr_id = pr.id ORDER BY reviewed_at DESC LIMIT 1) AS latest_decision
        FROM pull_requests pr
        JOIN repositories r ON r.id = pr.repo_id
        WHERE 1=1
    ";
    $params = [];

    if ($repoId) {
        $sql .= ' AND pr.repo_id = ?';
        $params[] = $repoId;
    }
    if ($author) {
        $sql .= ' AND pr.author = ?';
        $params[] = $author;
    }
    if ($search !== '') {
        $sql .= ' AND (pr.title LIKE ? OR pr.author LIKE ? OR pr.github_pr_number LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }

    $rows = db()->prepare($sql);
    $rows->execute($params);
    $prs = $rows->fetchAll();

    if ($status && $status !== 'all') {
        $prs = array_filter($prs, function ($pr) use ($status) {
            return pr_status($pr, $pr['latest_decision'])['key'] === $status;
        });
    }

    return array_values($prs);
}

function dashboard_stats(): array
{
    $db = db();
    $prs = fetch_prs();
    $stats = [
        'open' => 0,
        'stale' => 0,
        'blocked' => 0,
        'closed' => 0,
        'reviews' => 0,
        'avg_review_hours' => 0,
    ];

    foreach ($prs as $pr) {
        $key = pr_status($pr, $pr['latest_decision'])['key'];
        $stats[$key]++;
    }
    $stats['reviews'] = (int) $db->query('SELECT COUNT(*) FROM reviews')->fetchColumn();

    $avg = $db->query(
        "SELECT AVG(TIMESTAMPDIFF(HOUR, pr.created_at, rv.reviewed_at))
         FROM reviews rv
         JOIN pull_requests pr ON pr.id = rv.pr_id
         WHERE pr.created_at IS NOT NULL"
    )->fetchColumn();
    $stats['avg_review_hours'] = round((float) $avg, 1);

    return $stats;
}

function github_username_of_current_user(): ?string
{
    $user = current_user();
    return $user['github_username'] ?: null;
}

// ============================================================
//  v2 additions: notifications, analytics, theme, auth helpers
// ============================================================

function notify(int $userId, string $message, string $type = 'info', ?int $prId = null): void
{
    try {
        db()->prepare(
            'INSERT INTO notifications (user_id, type, message, pr_id) VALUES (?, ?, ?, ?)'
        )->execute([$userId, $type, $message, $prId]);
    } catch (Throwable $e) {
        // notifications are best-effort; never block the main flow
    }
}

function notify_all_admins_and_reviewers(string $message, string $type = 'info', ?int $prId = null): void
{
    $rows = db()->query("SELECT id FROM users WHERE role IN ('admin','reviewer')")->fetchAll();
    foreach ($rows as $r) {
        notify((int) $r['id'], $message, $type, $prId);
    }
}

function unread_notification_count(int $userId): int
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function latest_notifications(int $userId, int $limit = 8): array
{
    try {
        $stmt = db()->prepare(
            'SELECT n.*, pr.github_pr_number FROM notifications n
             LEFT JOIN pull_requests pr ON pr.id = n.pr_id
             WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT ' . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function analytics_chart_data(): array
{
    $db = db();
    $statusCounts = ['open' => 0, 'stale' => 0, 'blocked' => 0, 'closed' => 0];
    foreach (fetch_prs() as $pr) {
        $k = pr_status($pr, $pr['latest_decision'])['key'];
        $statusCounts[$k] = ($statusCounts[$k] ?? 0) + 1;
    }

    // PRs created per day over last 14 days
    $daily = [];
    for ($i = 13; $i >= 0; $i--) {
        $daily[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    $rows = $db->query(
        "SELECT DATE(created_at) d, COUNT(*) c FROM pull_requests
         WHERE created_at IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
         GROUP BY DATE(created_at)"
    )->fetchAll();
    foreach ($rows as $r) {
        if (isset($daily[$r['d']])) {
            $daily[$r['d']] = (int) $r['c'];
        }
    }

    // Reviews per day over last 14 days
    $reviewDaily = [];
    for ($i = 13; $i >= 0; $i--) {
        $reviewDaily[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    $rows = $db->query(
        "SELECT DATE(reviewed_at) d, COUNT(*) c FROM reviews
         WHERE reviewed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
         GROUP BY DATE(reviewed_at)"
    )->fetchAll();
    foreach ($rows as $r) {
        if (isset($reviewDaily[$r['d']])) {
            $reviewDaily[$r['d']] = (int) $r['c'];
        }
    }

    // Decision breakdown
    $decisions = $db->query(
        "SELECT decision, COUNT(*) c FROM reviews GROUP BY decision"
    )->fetchAll();
    $decisionMap = ['approved' => 0, 'changes' => 0, 'rejected' => 0];
    foreach ($decisions as $r) {
        $decisionMap[$r['decision']] = (int) $r['c'];
    }

    return [
        'labels'      => array_keys($daily),
        'prsPerDay'   => array_values($daily),
        'reviewsPerDay' => array_values($reviewDaily),
        'status'      => $statusCounts,
        'decisions'   => $decisionMap,
    ];
}

function is_stale_sync_needed(int $maxMinutes = 60): bool
{
    $last = db()->query('SELECT MAX(synced_at) FROM repositories')->fetchColumn();
    if (!$last) {
        return true;
    }
    return (time() - strtotime($last)) > $maxMinutes * 60;
}

function userTheme(): string
{
    $u = current_user();
    if (!$u) {
        return 'light';
    }
    return (string) ($u['theme'] === 'dark' ? 'dark' : 'light');
}

function set_github_review(array $pr, string $decision, string $comment): array
{
    // Optionally write the decision back to GitHub (needs a token with 'repo' scope).
    $repo = db()->prepare('SELECT * FROM repositories WHERE id = ?');
    $repo->execute([$pr['repo_id']]);
    $repo = $repo->fetch();

    if (empty($repo['sync_token'])) {
        return ['ok' => false, 'message' => 'No sync token on this repository; review recorded locally only.'];
    }

    $state = $decision === 'approved' ? 'APPROVED' : ($decision === 'changes' ? 'CHANGES_REQUESTED' : 'REJECTED');
    $api = 'https://api.github.com/repos/' . $repo['repo_full_name'] . '/pulls/' . $pr['github_pr_number'] . '/reviews';

    $headers = [
        'User-Agent: PR-Review-Tracker',
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'Authorization: Bearer ' . $repo['sync_token'],
        'Content-Type: application/json',
    ];
    $body = json_encode([
        'event' => $state,
        'body'  => $comment !== '' ? $comment : 'Review decision: ' . str_replace('_', ' ', $state),
    ]);

    $ch = curl_init($api);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201 || $httpCode === 200) {
        return ['ok' => true, 'message' => 'Review submitted to GitHub.'];
    }
    $reason = $httpCode === 403 ? 'This token may lack the "repo" write scope or is rate-limited.' : 'HTTP ' . $httpCode;
    return ['ok' => false, 'message' => 'GitHub rejected the review. ' . $reason];
}
