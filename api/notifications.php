<?php
// API endpoint: fetch and manage notifications.
// Returns JSON. Requires authentication.
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

$pdo    = get_db();
$user   = get_logged_in_user();
$uid    = $user['id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST') {
    csrf_verify();

    if ($action === 'mark_read') {
        $nid = (int)($_POST['id'] ?? 0);
        if ($nid) {
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
                ->execute([$nid, $uid]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'mark_all_read') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
            ->execute([$uid]);
        echo json_encode(['ok' => true]);
        exit;
    }
}

// GET: return latest notifications + unread count.
$stmt = $pdo->prepare(
    'SELECT id, message, link, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 20'
);
$stmt->execute([$uid]);
$items = $stmt->fetchAll();

$unread = 0;
foreach ($items as &$n) {
    $n['id']      = (int) $n['id'];
    $n['is_read'] = (bool) $n['is_read'];
    if (is_string($n['link']) && str_starts_with($n['link'], '/') && !str_starts_with($n['link'], '//')) {
        $n['link'] = rtrim(APP_URL, '/') . $n['link'];
    }
    if (!$n['is_read']) $unread++;
}

echo json_encode([
    'unread'        => $unread,
    'notifications' => $items,
]);
