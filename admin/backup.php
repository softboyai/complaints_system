<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('admin');

$message  = '';
$is_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_backup') {
    csrf_verify();

    $filename = 'mku_backup_' . date('Y-m-d_His') . '.sql';

    // Build mysqldump command.
    $mysqldump = 'mysqldump';
    // On XAMPP for Windows, mysqldump is typically in C:\xampp\mysql\bin\
    if (PHP_OS_FAMILY === 'Windows') {
        $win_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        if (file_exists($win_path)) {
            $mysqldump = '"' . $win_path . '"';
        }
    }

    $cmd = sprintf(
        '%s --user=%s --password=%s --host=%s --single-transaction --routines --triggers %s',
        $mysqldump,
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_NAME)
    );

    $output = null;
    $return_code = null;
    exec($cmd . ' 2>&1', $output, $return_code);

    if ($return_code !== 0) {
        $is_error = true;
        $message  = 'Backup failed. Make sure mysqldump is accessible. See logs for details.';
        log_error('Backup failed: ' . implode("\n", $output));
    } else {
        $sql_content = implode("\n", $output);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql_content));
        header('Cache-Control: no-cache');
        echo $sql_content;
        exit;
    }
}

$page_title = 'Database Backup';
$active_nav = 'backup';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Database Backup</h2>
    <p class="page-subtitle">Download a full backup of the database as a .sql file.</p>
  </div>
</div>

<?php if ($message): ?>
  <div class="alert alert--<?= $is_error ? 'error' : 'success' ?>" role="alert"><?= e($message) ?></div>
<?php endif; ?>

<div class="card form-card">
  <h3 class="card__section-title">Manual backup</h3>
  <p>Click the button below to download a complete backup of the <strong><?= e(DB_NAME) ?></strong> database.
     The file will be named <code>mku_backup_YYYY-MM-DD_HHMMSS.sql</code>.</p>

  <form method="POST" action="" id="backup-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="download_backup">
    <button type="submit" class="btn btn--primary" id="backup-btn">
      <span class="btn__label"><?= icon('database') ?> Download database backup</span>
      <span class="btn__spinner" hidden aria-hidden="true"></span>
    </button>
  </form>

  <hr style="margin:24px 0">

  <h3 class="card__section-title">Scheduled backups</h3>
  <p>To schedule automatic backups on Windows, add the following command to Windows Task Scheduler:</p>
  <pre class="code-block"><code>php C:\xampp\htdocs\mku-complaints\scripts\backup.php</code></pre>
  <p>The script saves dated .sql files to the <code>backups/</code> folder inside the project.
     Run it daily or weekly depending on how active the system is.</p>

  <h3 class="card__section-title" style="margin-top:24px">mysqldump path</h3>
  <p>This button uses <code>mysqldump</code>, which is included with XAMPP at:</p>
  <pre class="code-block"><code>C:\xampp\mysql\bin\mysqldump.exe</code></pre>
  <p>If the download fails, make sure XAMPP MySQL is running and the path is correct.</p>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
