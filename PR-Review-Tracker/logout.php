<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logout_user();
flash_set('success', 'You have been logged out.');
redirect('login.php');
