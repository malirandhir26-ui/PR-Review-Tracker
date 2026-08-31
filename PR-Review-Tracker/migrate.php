<?php
// ============================================================
//  PR Review Tracker - Schema migration (idempotent)
//  Adds tables/columns introduced by the v2 feature set.
//
//  Usage:  php migrate.php
//  Safe to run multiple times; only missing objects are created.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$db = db();
$ran = 0;

// User preferences (dark mode, etc.)
$cols = $db->query('SHOW COLUMNS FROM users LIKE "theme"')->fetchAll();
if (!$cols) {
    $db->exec("ALTER TABLE users ADD COLUMN theme ENUM('light','dark') NOT NULL DEFAULT 'light'");
    echo "Added users.theme\n";
    $ran++;
}

// In-app notifications for every user
$tables = $db->query("SHOW TABLES LIKE 'notifications'")->fetchAll();
if (!$tables) {
    $db->exec("
        CREATE TABLE notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'info',
            message VARCHAR(500) NOT NULL,
            pr_id INT UNSIGNED DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_notif_pr FOREIGN KEY (pr_id) REFERENCES pull_requests(id) ON DELETE CASCADE,
            KEY idx_notif_user_read (user_id, is_read)
        ) ENGINE=InnoDB
    ");
    echo "Created notifications table\n";
    $ran++;
}

// Add missing index to reviews for faster trend queries
$idx = $db->query("SHOW INDEX FROM reviews WHERE Key_name = 'idx_reviews_time'")->fetchAll();
if (!$idx) {
    $db->exec('ALTER TABLE reviews ADD INDEX idx_reviews_time (reviewed_at)');
    echo "Added reviews.idx_reviews_time\n";
    $ran++;
}

if ($ran === 0) {
    echo "Schema already up to date.\n";
} else {
    echo "Migration complete: {$ran} change(s) applied.\n";
}
