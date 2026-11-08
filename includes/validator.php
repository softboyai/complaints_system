<?php
// Centralised validation rules.
// Every rule returns ['valid' => bool, 'error' => string].
// These same rules are mirrored in assets/js/validation.js.

// Disposable/throwaway email domains to block.
const DISPOSABLE_DOMAINS = [
    'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwam.com',
    'yopmail.com', 'sharklasers.com', 'guerrillamailblock.com', 'grr.la',
    'guerrillamail.info', 'trashmail.com', 'dispostable.com', 'fakeinbox.com',
    'maildrop.cc', 'spamgourmet.com', '10minutemail.com', 'mailnull.com',
];

function validate_name(string $value): array {
    $value = trim($value);
    if ($value === '') {
        return ['valid' => false, 'error' => 'This field is required.'];
    }
    if (strlen($value) < 2 || strlen($value) > 50) {
        return ['valid' => false, 'error' => 'Must be between 2 and 50 characters.'];
    }
    // Letters, spaces, hyphens, apostrophes only. No numbers or symbols.
    if (!preg_match("/^[a-zA-ZÀ-ÿ\s'\-]+$/u", $value)) {
        return ['valid' => false, 'error' => 'Only letters, spaces, hyphens and apostrophes are allowed.'];
    }
    return ['valid' => true, 'error' => ''];
}

function validate_reg_number(string $value, PDO $pdo, int $exclude_id = 0): array {
    $value = trim($value);
    if ($value === '') {
        return ['valid' => false, 'error' => 'Registration number is required.'];
    }
    if (strlen($value) < 6 || strlen($value) > 20) {
        return ['valid' => false, 'error' => 'Registration number must be between 6 and 20 characters.'];
    }
    // Letters, numbers, slashes, hyphens only.
    if (!preg_match('/^[a-zA-Z0-9\/\-]+$/', $value)) {
        return ['valid' => false, 'error' => 'Use letters, numbers, slashes and hyphens only.'];
    }
    // Uniqueness check.
    $stmt = $pdo->prepare('SELECT id FROM users WHERE reg_number = ? AND id != ?');
    $stmt->execute([$value, $exclude_id]);
    if ($stmt->fetch()) {
        return ['valid' => false, 'error' => 'This registration number is already registered.'];
    }
    return ['valid' => true, 'error' => ''];
}

function validate_email(string $value, PDO $pdo, int $exclude_id = 0): array {
    $value = strtolower(trim($value));
    if ($value === '') {
        return ['valid' => false, 'error' => 'Email address is required.'];
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => 'Enter a valid email address.'];
    }
    // Block disposable domains.
    $domain = substr(strrchr($value, '@'), 1);
    if (in_array($domain, DISPOSABLE_DOMAINS, true)) {
        return ['valid' => false, 'error' => 'Disposable email addresses are not allowed.'];
    }
    // Uniqueness check.
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$value, $exclude_id]);
    if ($stmt->fetch()) {
        return ['valid' => false, 'error' => 'This email is already registered.'];
    }
    return ['valid' => true, 'error' => ''];
}

function validate_phone(string $value): array {
    $value = trim($value);
    if ($value === '') {
        return ['valid' => true, 'error' => '']; // Phone is optional
    }
    // Allow +250XXXXXXXXX or 07XXXXXXXX formats common in Rwanda.
    if (!preg_match('/^\+?[0-9]{9,15}$/', $value)) {
        return ['valid' => false, 'error' => 'Enter a valid phone number, for example +250788123456.'];
    }
    return ['valid' => true, 'error' => ''];
}

function validate_password(string $value, string $full_name = '', string $email = ''): array {
    if ($value === '') {
        return ['valid' => false, 'error' => 'Password is required.'];
    }
    if (strlen($value) < 8) {
        return ['valid' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    if (str_contains($value, ' ')) {
        return ['valid' => false, 'error' => 'Password must not contain spaces.'];
    }
    if (!preg_match('/[A-Z]/', $value)) {
        return ['valid' => false, 'error' => 'Include at least one uppercase letter.'];
    }
    if (!preg_match('/[a-z]/', $value)) {
        return ['valid' => false, 'error' => 'Include at least one lowercase letter.'];
    }
    if (!preg_match('/[0-9]/', $value)) {
        return ['valid' => false, 'error' => 'Include at least one number.'];
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $value)) {
        return ['valid' => false, 'error' => 'Include at least one special character.'];
    }
    // Must not contain the user's first name or email prefix.
    $first_name = strtolower(explode(' ', trim($full_name))[0] ?? '');
    $email_prefix = strtolower(explode('@', trim($email))[0] ?? '');
    $lower = strtolower($value);
    if ($first_name && strlen($first_name) > 2 && str_contains($lower, $first_name)) {
        return ['valid' => false, 'error' => 'Password must not contain your name.'];
    }
    if ($email_prefix && strlen($email_prefix) > 2 && str_contains($lower, $email_prefix)) {
        return ['valid' => false, 'error' => 'Password must not contain your email address.'];
    }
    return ['valid' => true, 'error' => ''];
}

function validate_passwords_match(string $password, string $confirm): array {
    if ($password !== $confirm) {
        return ['valid' => false, 'error' => 'Passwords do not match.'];
    }
    return ['valid' => true, 'error' => ''];
}

function validate_complaint_title(string $value): array {
    $value = trim($value);
    if ($value === '') {
        return ['valid' => false, 'error' => 'Title is required.'];
    }
    $len = mb_strlen($value);
    if ($len < 5 || $len > 100) {
        return ['valid' => false, 'error' => 'Title must be between 5 and 100 characters.'];
    }
    return ['valid' => true, 'error' => ''];
}

function validate_complaint_description(string $value): array {
    $value = trim($value);
    if ($value === '') {
        return ['valid' => false, 'error' => 'Description is required.'];
    }
    $len = mb_strlen($value);
    if ($len < 20 || $len > 2000) {
        return ['valid' => false, 'error' => 'Description must be between 20 and 2000 characters.'];
    }
    return ['valid' => true, 'error' => ''];
}

function validate_required(string $value, string $field_label = 'This field'): array {
    if (trim($value) === '') {
        return ['valid' => false, 'error' => $field_label . ' is required.'];
    }
    return ['valid' => true, 'error' => ''];
}

// Validate a single uploaded file. Returns error string or empty string on success.
function validate_attachment(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The file is too large (exceeds server limit).',
            UPLOAD_ERR_FORM_SIZE  => 'The file is too large.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
        ];
        $msg = $messages[$file['error']] ?? 'Unknown upload error.';
        return ['valid' => false, 'error' => $msg];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'error' => 'Each file must be under 5 MB.'];
    }

    // Check real MIME type, not just the extension.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_MIMES, true)) {
        return ['valid' => false, 'error' => 'Only PDF, JPG and PNG files are allowed.'];
    }

    // Also check extension.
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTS, true)) {
        return ['valid' => false, 'error' => 'Only PDF, JPG and PNG files are allowed.'];
    }

    return ['valid' => true, 'error' => ''];
}

// Collect all field errors from a result map.
// Returns true if all fields are valid, false otherwise.
function all_valid(array $results): bool {
    foreach ($results as $r) {
        if (!$r['valid']) {
            return false;
        }
    }
    return true;
}
