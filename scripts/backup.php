<?php
// Database backup script. Run from CLI or Windows Task Scheduler.
// Usage: php C:\xampp\htdocs\mku-complaints\scripts\backup.php
//
// Saves a dated .sql file to the backups/ folder.

define('RUNNING_FROM_CLI', php_sapi_name() === 'cli');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$backup_dir = ROOT_PATH . '/backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$filename = $backup_dir . '/mku_backup_' . date('Y-m-d_His') . '.sql';

$mysqldump = 'mysqldump';
if (PHP_OS_FAMILY === 'Windows') {
    $win_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    if (file_exists($win_path)) {
        $mysqldump = '"' . $win_path . '"';
    }
}

$cmd = sprintf(
    '%s --user=%s --password=%s --host=%s --single-transaction --routines --triggers %s > %s 2>&1',
    $mysqldump,
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_NAME),
    escapeshellarg($filename)
);

$return_code = null;
system($cmd, $return_code);

if ($return_code === 0 && file_exists($filename) && filesize($filename) > 0) {
    $msg = sprintf('[%s] Backup saved: %s (%s)',
        date('Y-m-d H:i:s'), basename($filename), format_bytes(filesize($filename))
    );
} else {
    $msg = sprintf('[%s] Backup FAILED. Return code: %d. Check that mysqldump is accessible.',
        date('Y-m-d H:i:s'), $return_code
    );
    log_error($msg);
}

if (RUNNING_FROM_CLI) {
    echo $msg . PHP_EOL;
} else {
    if (!DEV_MODE) {
        http_response_code(403);
        die('Access denied.');
    }
    header('Content-Type: text/plain');
    echo $msg;
}
