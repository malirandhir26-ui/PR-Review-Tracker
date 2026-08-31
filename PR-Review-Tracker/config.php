<?php

// ============================================================
//  PR Review Tracker - Configuration
//  Edit the values below to match your environment
// ============================================================

// ---- Database credentials (change these) ----
// Local project instance (start it with ./db_start.sh) runs on port 3307.
// For XAMPP or a web host, set DB_HOST to 'localhost' and DB_PORT to 3306.
// Cloud deploys (Render) override these via environment variables.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3307));
define('DB_NAME', getenv('DB_NAME') ?: 'pr_review_tracker');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ---- App settings ----
define('APP_NAME', 'PR Review Tracker');

// A pull request is "stale" if its last activity is older than this many days
define('STALE_DAYS', 2);

// When enabled, the dashboard auto-syncs with GitHub if the last sync is stale.
// Data is refreshed automatically on each visit (lightweight; good for small teams).
define('AUTO_SYNC', true);

// Optional secret for the GitHub webhook receiver (leave empty to disable auth).
// define('WEBHOOK_SECRET', '');

// ---- Base URL ----
// Leave empty to auto-detect. If the app lives in a subfolder of a domain
// (e.g. https://example.com/pr-review-tracker), set it manually, e.g.:
// define('BASE_URL', '/pr-review-tracker');
define('BASE_URL', '');
