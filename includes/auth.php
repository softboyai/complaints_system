<?php
// Authentication and session helpers.
// Include this file at the top of every protected page.

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Start session with secure settings if not already started.
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,    // Set to true when using HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// Check session timeout. Destroys session if idle too long.
function check_session_timeout(): void {
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $last = $_SESSION['last_activity'] ?? 0;
    if ((time() - $last) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        start_secure_session();
        $_SESSION['flash_warning'] = 'Your session expired. Please log in again.';
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

// Redirect to login if not authenticated.
function require_login(): void {
    start_secure_session();
    check_session_timeout();

    if (empty($_SESSION['user_id'])) {
        $_SESSION['flash_warning'] = 'Please log in to continue.';
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

// Require a specific role. Accepts a string or an array of roles.
function require_role(string|array $allowed_roles): void {
    require_login();

    $role = $_SESSION['user_role'] ?? '';
    $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];

    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        require dirname(__DIR__) . '/includes/error403.php';
        exit;
    }
}

// Returns the current user's session data as an array.
function get_logged_in_user(): array {
    return [
        'id'            => $_SESSION['user_id']   ?? 0,
        'full_name'     => $_SESSION['user_name']  ?? '',
        'email'         => $_SESSION['user_email'] ?? '',
        'role'          => $_SESSION['user_role']  ?? '',
        'department_id' => $_SESSION['dept_id']    ?? null,
    ];
}
// Log a user in: set session variables, regenerate session ID.
function login_user(array $user): void {
    start_secure_session();
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['user_name']     = $user['full_name'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['dept_id']       = $user['department_id'] ?? null;
    $_SESSION['last_activity'] = time();
}

// Generate or return the current CSRF token.
function csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Output a hidden CSRF input field.
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// Verify the submitted CSRF token. Exits with 403 on failure.
function csrf_verify(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if (!hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('Invalid or missing security token. Please go back and try again.');
    }
}

// Retrieve and clear a flash message from the session.
function flash(string $key): string {
    $msg = $_SESSION[$key] ?? '';
    unset($_SESSION[$key]);
    return $msg;
}

// Set a flash message.
function set_flash(string $key, string $message): void {
    start_secure_session();
    $_SESSION[$key] = $message;
}

start_secure_session();
