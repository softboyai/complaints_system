<?php
// Secure file download handler.
// Checks that the requesting user has permission to access the attachment.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

require_login();
$pdo  = get_db();
$user = get_logged_in_user();

$aid = (int)($_GET['id'] ?? 0);
if (!$aid) {
    http_response_code(400);
    die('Invalid request.');
}

// Fetch attachment and associated complaint.
$stmt = $pdo->prepare(
    'SELECT a.*, c.student_id, c.department_id
     FROM attachments a
     JOIN complaints c ON c.id = a.complaint_id
     WHERE a.id = ? LIMIT 1'
);
$stmt->execute([$aid]);
$att = $stmt->fetch();

if (!$att) {
    http_response_code(404);
    die('File not found.');
}

// Permission check.
$allowed = false;
if ($user['role'] === 'admin') {
    $allowed = true;
} elseif ($user['role'] === 'staff' && (int)$att['department_id'] === (int)$user['department_id']) {
    $allowed = true;
} elseif ($user['role'] === 'student' && (int)$att['student_id'] === (int)$user['id']) {
    $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    die('You do not have permission to download this file.');
}

$filepath = UPLOAD_PATH . '/' . $att['stored_name'];
if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found on server.');
}

// Serve the file.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $filepath) ?: 'application/octet-stream';
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $att['original_name'] . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, no-store');
readfile($filepath);
exit;
