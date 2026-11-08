<?php
// Password reset is now handled entirely in forgot-password.php.
// This file redirects anyone who has an old bookmarked link.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

start_secure_session();
header('Location: ' . APP_URL . '/forgot-password.php');
exit;
