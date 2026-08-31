<?php

require_once __DIR__ . '/db.php';

function github_sync_repository(array $repo): array
{
    $fullName = trim((string) $repo['repo_full_name'], '/');
    if (!preg_match('/^[\w.-]+\/[\w.-]+$/', $fullName)) {
        return ['ok' => false, 'message' => 'Invalid repository name. Use the format "owner/repo".'];
    }

    $api = 'https://api.github.com/repos/' . $fullName . '/pulls?state=open&per_page=100';

    $headers = [
        'User-Agent: PR-Review-Tracker',
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if (!empty($repo['sync_token'])) {
        $headers[] = 'Authorization: Bearer ' . $repo['sync_token'];
    }

    $ch = curl_init($api);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $reason = '';
        if ($httpCode === 404) {
            $reason = 'Repository not found. Check the "owner/repo" name.';
        } elseif ($httpCode === 401 || $httpCode === 403) {
            $reason = 'Invalid or expired access token, or rate limit hit.';
        }
        return ['ok' => false, 'message' => 'GitHub returned HTTP ' . $httpCode . '. ' . $reason];
    }

    $prs = json_decode($response, true);
    if (!is_array($prs)) {
        return ['ok' => false, 'message' => 'Invalid JSON received from GitHub.'];
    }

    $db = db();
    $upsert = $db->prepare(
        "INSERT INTO pull_requests (repo_id, github_pr_number, title, author, url, state, last_activity_at, created_at)
         VALUES (?, ?, ?, ?, ?, 'open', ?, ?)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            author = VALUES(author),
            url = VALUES(url),
            state = VALUES(state),
            last_activity_at = VALUES(last_activity_at)"
    );

    $seen = [];
    foreach ($prs as $pr) {
        $number = (int) ($pr['number'] ?? 0);
        if ($number <= 0) {
            continue;
        }
        $seen[] = $number;
        $upsert->execute([
            $repo['id'],
            $number,
            mb_substr((string) ($pr['title'] ?? ''), 0, 500),
            mb_substr((string) ($pr['user']['login'] ?? 'unknown'), 0, 100),
            $pr['html_url'] ?? null,
            isset($pr['updated_at']) ? date('Y-m-d H:i:s', strtotime($pr['updated_at'])) : null,
            isset($pr['created_at']) ? date('Y-m-d H:i:s', strtotime($pr['created_at'])) : null,
        ]);
    }

    if ($seen) {
        $placeholders = implode(',', array_fill(0, count($seen), '?'));
        $close = $db->prepare(
            "UPDATE pull_requests SET state = 'closed'
             WHERE repo_id = ? AND state = 'open' AND github_pr_number NOT IN ($placeholders)"
        );
        $close->execute(array_merge([$repo['id']], $seen));
    }

    $db->prepare('UPDATE repositories SET synced_at = NOW() WHERE id = ?')->execute([$repo['id']]);

    return ['ok' => true, 'message' => 'Synced ' . count($prs) . ' open pull request(s).', 'count' => count($prs)];
}

function github_sync_all(): array
{
    $repos = db()->query('SELECT * FROM repositories ORDER BY name')->fetchAll();
    if (!$repos) {
        return ['ok' => false, 'message' => 'No repositories added yet.'];
    }
    $messages = [];
    $total = 0;
    foreach ($repos as $repo) {
        $result = github_sync_repository($repo);
        if ($result['ok']) {
            $total += $result['count'];
            $messages[] = $repo['name'] . ': ' . $result['message'];
        } else {
            $messages[] = $repo['name'] . ': ' . $result['message'];
        }
    }
    return ['ok' => true, 'message' => implode('<br>', $messages), 'count' => $total];
}
