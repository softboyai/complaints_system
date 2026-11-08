<?php
// API endpoint: chart data for dashboards.
// Returns JSON. Requires staff or admin role.
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role(['staff', 'admin']);
header('Content-Type: application/json; charset=utf-8');

$pdo  = get_db();
$user = get_logged_in_user();
$type = $_GET['type'] ?? 'status';
$dept = (int)($_GET['dept'] ?? 0);

// Staff see their department only; admin sees all.
$dept_where  = '';
$dept_params = [];
if ($user['role'] === 'staff' && $user['department_id']) {
    $dept_where  = 'AND department_id = ?';
    $dept_params = [$user['department_id']];
} elseif ($user['role'] === 'admin' && $dept > 0) {
    $dept_where  = 'AND department_id = ?';
    $dept_params = [$dept];
}

switch ($type) {

    case 'status':
        $stmt = $pdo->prepare(
            "SELECT status, COUNT(*) AS count
             FROM complaints
             WHERE 1=1 {$dept_where}
             GROUP BY status ORDER BY count DESC"
        );
        $stmt->execute($dept_params);
        $rows = $stmt->fetchAll();

        $color_map = [
            'Submitted'    => '#0052FF',
            'Under Review' => '#F4A100',
            'In Progress'  => '#FF8C00',
            'Resolved'     => '#05B169',
            'Closed'       => '#9CA3AF',
            'Rejected'     => '#CF202F',
            'Escalated'    => '#7B2FBE',
        ];

        $labels = array_column($rows, 'status');
        $data   = array_map('intval', array_column($rows, 'count'));
        $colors = array_map(fn($l) => $color_map[$l] ?? '#dee1e6', $labels);

        echo json_encode(compact('labels', 'data', 'colors'));
        break;

    case 'category':
        $cats = complaint_categories();
        $placeholders = rtrim(str_repeat('?,', count($cats)), ',');
        $stmt = $pdo->prepare(
            "SELECT category, COUNT(*) AS count
             FROM complaints
             WHERE category IN ({$placeholders}) {$dept_where}
             GROUP BY category"
        );
        $stmt->execute(array_merge($cats, $dept_params));
        $raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $labels = $cats;
        $data   = array_map(fn($c) => (int)($raw[$c] ?? 0), $cats);
        $colors = array_fill(0, count($cats), '#0052FF');

        echo json_encode(compact('labels', 'data', 'colors'));
        break;

    case 'trend':
        // Last 12 months.
        $months = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts        = mktime(0, 0, 0, (int)date('m') - $i, 1, (int)date('Y'));
            $months[]  = date('Y-m', $ts);
            $labels[]  = date('M Y', $ts);
        }

        $placeholders = rtrim(str_repeat('?,', count($months)), ',');
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS count
             FROM complaints
             WHERE DATE_FORMAT(created_at, '%Y-%m') IN ({$placeholders}) {$dept_where}
             GROUP BY ym"
        );
        $stmt->execute(array_merge($months, $dept_params));
        $raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $data   = array_map(fn($m) => (int)($raw[$m] ?? 0), $months);
        $colors = array_fill(0, count($months), '#0052FF');

        echo json_encode(compact('labels', 'data', 'colors'));
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown chart type.']);
}
