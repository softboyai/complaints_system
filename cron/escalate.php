<?php
// Escalation script - run from Windows Task Scheduler or CLI.
// Usage: php C:\xampp\htdocs\mku-complaints\cron\escalate.php
//
// Schedule with Task Scheduler:
// Action: C:\xampp\php\php.exe
// Arguments: C:\xampp\htdocs\mku-complaints\cron\escalate.php

define('RUNNING_FROM_CLI', php_sapi_name() === 'cli');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$pdo   = get_db();
$count = check_escalations($pdo);

$msg = sprintf('[%s] Escalation check complete. %d complaint%s escalated.',
    date('Y-m-d H:i:s'), $count, $count === 1 ? '' : 's'
);

if (RUNNING_FROM_CLI) {
    echo $msg . PHP_EOL;
} else {
    // Prevent running from browser in production.
    if (!DEV_MODE) {
        http_response_code(403);
        die('Access denied.');
    }
    header('Content-Type: text/plain');
    echo $msg;
}
