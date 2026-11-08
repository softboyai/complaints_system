<?php
// Excel/CSV report generator. Protected: staff and admin only.
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role(['staff', 'admin']);
$pdo  = get_db();
$user = get_logged_in_user();

$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to']   ?? '');
$status    = trim($_GET['status']    ?? '');
$category  = trim($_GET['category']  ?? '');
$priority  = trim($_GET['priority']  ?? '');
$dept_id   = (int)($_GET['dept'] ?? 0);

$where  = ['1=1'];
$params = [];

if ($user['role'] === 'staff') {
    $where[]  = 'c.department_id = ?';
    $params[] = $user['department_id'];
} elseif ($dept_id > 0) {
    $where[]  = 'c.department_id = ?';
    $params[] = $dept_id;
}

if ($date_from !== '') { $where[] = 'DATE(c.created_at) >= ?'; $params[] = $date_from; }
if ($date_to   !== '') { $where[] = 'DATE(c.created_at) <= ?'; $params[] = $date_to;   }
if ($status    !== '') { $where[] = 'c.status = ?';            $params[] = $status;    }
if ($category  !== '') { $where[] = 'c.category = ?';          $params[] = $category;  }
if ($priority  !== '') { $where[] = 'c.priority = ?';          $params[] = $priority;  }

$where_sql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT c.tracking_id, c.title, c.category, c.status, c.priority,
            c.is_anonymous, c.created_at, c.updated_at, c.rating,
            u.full_name AS student_name, u.reg_number,
            d.name AS dept_name
     FROM complaints c
     JOIN users u ON u.id = c.student_id
     LEFT JOIN departments d ON d.id = c.department_id
     {$where_sql}
     ORDER BY c.created_at DESC LIMIT 5000"
);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

$filename = 'mku-complaints-report-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

// Output UTF-8 BOM so Excel opens correctly.
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row.
fputcsv($out, [
    'Tracking ID', 'Student Name', 'Reg Number', 'Title', 'Category',
    'Department', 'Priority', 'Status', 'Submitted Date', 'Last Updated', 'Rating',
]);

// Data rows.
foreach ($complaints as $c) {
    fputcsv($out, [
        $c['tracking_id'],
        $c['is_anonymous'] ? 'Anonymous' : $c['student_name'],
        $c['is_anonymous'] ? '--' : ($c['reg_number'] ?? '--'),
        $c['title'],
        $c['category'],
        $c['dept_name'] ?? '--',
        $c['priority'],
        $c['status'],
        date('d/m/Y', strtotime($c['created_at'])),
        date('d/m/Y', strtotime($c['updated_at'])),
        $c['rating'] ?? '--',
    ]);
}

fclose($out);
exit;
