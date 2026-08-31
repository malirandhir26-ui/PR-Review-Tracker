<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

start_session();
$current_user = current_user();
$flash = flash_get();

$notifCount = $current_user ? unread_notification_count((int) $current_user['id']) : 0;
$htmlTheme = userTheme();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= e($htmlTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($page_title) ? e($page_title) . ' | ' : '' ?><?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= base_url('index.php') ?>"><?= e(APP_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <?php if ($current_user): ?>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= base_url('index.php') ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('prs.php') ?>">Pull Requests</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('repos.php') ?>">Repositories</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('reports.php') ?>">Reports</a></li>
            </ul>
            <ul class="navbar-nav">
                <?php if ($current_user): ?>
                <li class="nav-item text-nowrap me-2">
                    <a class="nav-link position-relative p-2" href="<?= base_url('notifications.php') ?>" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($notifCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item text-nowrap me-2">
                    <button type="button" class="btn btn-sm btn-outline-light mt-1" id="themeToggle"
                            data-csrf="<?= e(csrf_token()) ?>" data-current="<?= e($htmlTheme) ?>" aria-label="Toggle dark mode">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                </li>
                <li class="nav-item text-nowrap">
                    <span class="navbar-text me-3">
                        <?= e($current_user['name']) ?>
                        <span class="badge rounded-pill text-bg-<?= $current_user['role'] === 'admin' ? 'danger' : ($current_user['role'] === 'reviewer' ? 'primary' : 'secondary') ?> ms-1"><?= e($current_user['role']) ?></span>
                    </span>
                </li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('logout.php') ?>">Logout</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</nav>

<div class="container my-4">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
