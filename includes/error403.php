<?php
// 403 Forbidden partial - included by require_role() on access denial.
$page_title = 'Access Denied';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Denied - MKU Complaints</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
  <div class="error-page">
    <div class="error-page__icon">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
    </div>
    <h1>You do not have permission to view this page</h1>
    <p>If you think this is a mistake, please contact the system administrator.</p>
    <a href="<?= APP_URL ?>/" class="btn btn--primary">Go to home</a>
  </div>
</body>
</html>
