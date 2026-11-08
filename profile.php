<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$profile_path = match ($_SESSION['user_role'] ?? '') {
    'student' => APP_URL . '/student/profile.php',
    'staff'   => APP_URL . '/staff/profile.php',
    'admin'   => APP_URL . '/admin/profile.php',
    default   => APP_URL . '/login.php',
};

header('Location: ' . $profile_path);
exit;