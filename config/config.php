<?php
// ============================================================
// Application Configuration
// Copy this file and edit values before running the app.
// Do NOT commit real credentials to version control.
// ============================================================

// ---- Database -----------------------------------------------
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'mku_complaints');
define('DB_USER', 'root');
define('DB_PASS', '');           // Default XAMPP has no root password
define('DB_CHARSET', 'utf8mb4');

// ---- Application --------------------------------------------
define('APP_NAME', 'MKU Complaints');
define('APP_URL',  'http://localhost/mku-complaints'); // No trailing slash
define('APP_VERSION', '1.0.0');

// ---- Paths (server-side) ------------------------------------
define('ROOT_PATH',   dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH',    ROOT_PATH . '/logs');

// ---- Session ------------------------------------------------
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// ---- File Uploads -------------------------------------------
define('MAX_FILE_SIZE',  5 * 1024 * 1024); // 5 MB per file
define('MAX_FILES',      5);
define('ALLOWED_MIMES',  ['image/jpeg', 'image/png', 'application/pdf']);
define('ALLOWED_EXTS',   ['jpg', 'jpeg', 'png', 'pdf']);

// ---- Email (PHPMailer) --------------------------------------
// For Gmail: enable 2FA and create an App Password.
// For development, set DEV_MODE to true to skip sending and log to logs/mail.log.
define('DEV_MODE', true); // Set to false in production

define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_SECURE',     'tls');            // 'tls' or 'ssl'
define('SMTP_AUTH',       true);
define('SMTP_USER',       'your-email@gmail.com');
define('SMTP_PASS',       'your-app-password');
define('MAIL_FROM',       'noreply@mku.ac.rw');
define('MAIL_FROM_NAME',  'Mount Kigali University');

// ---- Security -----------------------------------------------
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINS', 15);
define('RESET_TOKEN_MINS',   15);

// ---- Error Handling -----------------------------------------
// Never show PHP errors to users in production.
// Errors are logged to logs/error.log.
if (DEV_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . '/error.log');

// Custom error handler so raw errors never reach the browser.
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $entry = sprintf('[%s] %s in %s on line %d', date('Y-m-d H:i:s'), $message, $file, $line);
    error_log($entry);
    return true;
});

set_exception_handler(function (Throwable $e): void {
    $entry = sprintf('[%s] Uncaught %s: %s in %s on line %d',
        date('Y-m-d H:i:s'), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()
    );
    error_log($entry);
    if (!DEV_MODE) {
        http_response_code(500);
        require ROOT_PATH . '/includes/error500.php';
        exit;
    }
});
