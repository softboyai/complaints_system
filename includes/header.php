<?php
// Shared header partial.
// Variables expected from the including page:
//   $page_title    (string)  - page title shown in <title> and sidebar header
//   $active_nav    (string)  - nav item to mark active, e.g. 'dashboard', 'complaints'
//   $load_charts   (bool)    - set true on dashboard pages to include charts.js
//   $body_class    (string)  - optional extra class on <body>

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title  = $page_title  ?? APP_NAME;
$active_nav  = $active_nav  ?? '';
$load_charts = $load_charts ?? false;
$body_class  = $body_class  ?? '';

// Determine if we are on a public page (no sidebar).
$is_public = !isset($_SESSION['user_id']);
$current_user = $is_public ? null : get_logged_in_user();
$role = $current_user['role'] ?? '';

// Build the role-appropriate base path for nav links.
$base = match($role) {
    'student' => APP_URL . '/student',
    'staff'   => APP_URL . '/staff',
    'admin'   => APP_URL . '/admin',
    default   => APP_URL,
};

// Nav items per role.
$nav_items = [];
if ($role === 'student') {
    $nav_items = [
        ['key' => 'dashboard',  'label' => 'Dashboard',       'href' => $base . '/dashboard.php',        'icon' => 'home'],
        ['key' => 'submit',     'label' => 'Submit Complaint', 'href' => $base . '/submit-complaint.php', 'icon' => 'plus-circle'],
        ['key' => 'complaints', 'label' => 'My Complaints',    'href' => $base . '/my-complaints.php',    'icon' => 'file-text'],
        ['key' => 'feedback',   'label' => 'Give Feedback',    'href' => $base . '/feedback.php',         'icon' => 'star'],
    ];
} elseif ($role === 'staff') {
    $nav_items = [
        ['key' => 'dashboard',  'label' => 'Dashboard',  'href' => $base . '/dashboard.php',  'icon' => 'home'],
        ['key' => 'complaints', 'label' => 'Complaints',  'href' => $base . '/complaints.php', 'icon' => 'file-text'],
        ['key' => 'reports',    'label' => 'Reports',     'href' => $base . '/reports.php',    'icon' => 'bar-chart'],
    ];
} elseif ($role === 'admin') {
    $nav_items = [
        ['key' => 'dashboard',   'label' => 'Dashboard',    'href' => $base . '/dashboard.php',   'icon' => 'home'],
        ['key' => 'complaints',  'label' => 'Complaints',   'href' => $base . '/complaints.php',  'icon' => 'file-text'],
        ['key' => 'users',       'label' => 'Users',        'href' => $base . '/users.php',       'icon' => 'users'],
        ['key' => 'departments', 'label' => 'Departments',  'href' => $base . '/departments.php', 'icon' => 'grid'],
        ['key' => 'reports',     'label' => 'Reports',      'href' => $base . '/reports.php',     'icon' => 'bar-chart'],
        ['key' => 'backup',      'label' => 'Backup',       'href' => $base . '/backup.php',      'icon' => 'database'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?> - MKU Complaints</title>
  <meta name="description" content="Mount Kigali University Complaints and Feedback Management System">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<script>
  window.APP_URL    = '<?= APP_URL ?>';
  window.CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<body class="<?= e($body_class) ?>" data-role="<?= e($role) ?>">

<?php if ($is_public): ?>
  <!-- ========================================================
       PUBLIC TOP NAV
  ========================================================= -->
  <header class="public-nav" id="public-nav">
    <div class="public-nav__inner container">
      <a href="<?= APP_URL ?>/" class="public-nav__logo" aria-label="MKU Complaints home">
        <img class="public-nav__logo-image" src="<?= APP_URL ?>/assets/images/MKUR-logo.png" alt="Mount Kigali University">
        <span class="public-nav__logo-label">Complaints &amp; Feedback</span>
      </a>
      <nav class="public-nav__links" aria-label="Main navigation">
        <a href="<?= APP_URL ?>/#how-it-works">How it works</a>
        <a href="<?= APP_URL ?>/#features">Features</a>
      </nav>
      <div class="public-nav__actions">
        <a href="<?= APP_URL ?>/login.php" class="btn btn--ghost btn--sm">Log in</a>
        <a href="<?= APP_URL ?>/register.php" class="btn btn--primary btn--sm">Get started</a>
      </div>
      <button class="hamburger" id="public-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

<?php else: ?>
  <!-- ========================================================
       AUTHENTICATED LAYOUT: SIDEBAR + TOPBAR
  ========================================================= -->
  <div class="app-layout" id="app-layout">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">
      <div class="sidebar__header">
        <a href="<?= $base ?>/dashboard.php" class="sidebar__logo" aria-label="Dashboard">
          <img class="sidebar__logo-image" src="<?= APP_URL ?>/assets/images/MKUR-logo.png" alt="Mount Kigali University">
          <span class="logo-mark sidebar__logo-mark" aria-hidden="true">MKU</span>
          <span class="logo-text sidebar__logo-text">Complaints</span>
        </a>
        <button class="sidebar__close" id="sidebar-close" aria-label="Close menu">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <nav class="sidebar__nav" aria-label="App navigation">
        <?php foreach ($nav_items as $item): ?>
          <a href="<?= e($item['href']) ?>"
             class="sidebar__link <?= $active_nav === $item['key'] ? 'sidebar__link--active' : '' ?>"
             aria-current="<?= $active_nav === $item['key'] ? 'page' : 'false' ?>">
            <span class="sidebar__icon" aria-hidden="true"><?= icon($item['icon']) ?></span>
            <span class="sidebar__label"><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="sidebar__footer">
        <a href="<?= $base ?>/profile.php" class="sidebar__link <?= $active_nav === 'profile' ? 'sidebar__link--active' : '' ?>">
          <span class="sidebar__icon" aria-hidden="true"><?= icon('user') ?></span>
          <span class="sidebar__label">Profile</span>
        </a>
        <a href="<?= APP_URL ?>/logout.php" class="sidebar__link sidebar__link--logout">
          <span class="sidebar__icon" aria-hidden="true"><?= icon('log-out') ?></span>
          <span class="sidebar__label">Log out</span>
        </a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

    <!-- Main content area -->
    <div class="app-main">
      <!-- Top bar -->
      <header class="topbar" role="banner">
        <button class="topbar__hamburger" id="sidebar-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <div class="topbar__title">
          <h1><?= e($page_title) ?></h1>
        </div>

        <div class="topbar__actions">
          <!-- Dark mode toggle -->
          <button class="topbar__icon-btn" id="dark-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
            <svg class="icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg class="icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          </button>

          <!-- Notification bell -->
          <div class="notifications-wrap" id="notifications-wrap">
            <button class="topbar__icon-btn topbar__bell" id="notifications-btn"
                    aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
              <?= icon('bell') ?>
              <span class="notif-badge" id="notif-badge" aria-live="polite" style="display:none">0</span>
            </button>
            <div class="notifications-dropdown" id="notifications-dropdown" role="dialog" aria-label="Notifications" hidden>
              <div class="notif-header">
                <span>Notifications</span>
                <button class="notif-mark-all" id="notif-mark-all">Mark all as read</button>
              </div>
              <div class="notif-list" id="notif-list" role="list">
                <div class="notif-loading">Loading...</div>
              </div>
            </div>
          </div>

          <!-- Avatar dropdown -->
          <div class="avatar-wrap" id="avatar-wrap">
            <button class="avatar-btn" id="avatar-btn"
                    aria-haspopup="true" aria-expanded="false"
                    aria-label="Account menu">
              <span class="avatar-initials" aria-hidden="true">
                <?= e(strtoupper(substr($current_user['full_name'], 0, 1))) ?>
              </span>
              <span class="avatar-name"><?= e(explode(' ', $current_user['full_name'])[0]) ?></span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="avatar-dropdown" id="avatar-dropdown" role="menu" hidden>
              <div class="avatar-dropdown__user">
                <strong><?= e($current_user['full_name']) ?></strong>
                <small><?= e($current_user['email']) ?></small>
              </div>
              <a href="<?= $base ?>/profile.php" class="avatar-dropdown__item" role="menuitem">
                <?= icon('user') ?> My profile
              </a>
              <hr>
              <a href="<?= APP_URL ?>/logout.php" class="avatar-dropdown__item avatar-dropdown__item--danger" role="menuitem">
                <?= icon('log-out') ?> Log out
              </a>
            </div>
          </div>
        </div>
      </header>

      <!-- Page content starts here -->
      <main class="app-content" id="main-content">

<?php endif; // end authenticated layout check ?>

<?php
// Show flash messages as visible alerts.
$flash_types = ['flash_success', 'flash_error', 'flash_warning', 'flash_info'];
foreach ($flash_types as $key):
    $msg = flash($key);
    if ($msg):
        $type = str_replace('flash_', '', $key);
?>
<div class="alert alert--<?= e($type) ?>" role="alert">
  <?= e($msg) ?>
  <button class="alert__close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
</div>
<?php
    endif;
endforeach;
?>

<?php
// Inline SVG icon helper used in this file and across templates.
// Returns an SVG string for a Feather-style icon name.
function icon(string $name): string {
    $icons = [
        'home'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'file-text'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'plus-circle'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
        'star'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'users'        => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'grid'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'bar-chart'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>',
        'database'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
        'help-circle'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'user'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'log-out'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'bell'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'download'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'check-circle' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        'alert-circle' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        'paperclip'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
        'send'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
        'edit'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'trash'        => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
        'search'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
        'filter'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>',
        'eye'          => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'eye-off'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>',
        'chevron-right'=> '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>',
        'chevron-left' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>',
        'x'            => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        'clock'        => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'lock'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'mail'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    ];
    return $icons[$name] ?? '';
}
