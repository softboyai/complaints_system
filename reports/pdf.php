<?php
// PDF report generator. Protected: staff and admin only.
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role(['staff', 'admin']);
$pdo  = get_db();
$user = get_logged_in_user();

// Filters from query string.
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to']   ?? '');
$status    = trim($_GET['status']    ?? '');
$category  = trim($_GET['category']  ?? '');
$priority  = trim($_GET['priority']  ?? '');
$dept_id   = (int)($_GET['dept'] ?? 0);

$where  = ['1=1'];
$params = [];

// Staff only see their department.
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
    "SELECT c.id, c.tracking_id, c.title, c.category, c.status, c.priority,
            c.is_anonymous, c.created_at, c.updated_at, c.rating,
            u.full_name AS student_name,
            d.name AS dept_name
     FROM complaints c
     JOIN users u ON u.id = c.student_id
     LEFT JOIN departments d ON d.id = c.department_id
     {$where_sql}
     ORDER BY c.created_at DESC LIMIT 1000"
);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Summary figures.
$total     = count($complaints);
$resolved  = count(array_filter($complaints, fn($c) => $c['status'] === 'Resolved'));
$pending   = count(array_filter($complaints, fn($c) => !in_array($c['status'], ['Resolved','Closed','Rejected'])));
$ratings   = array_filter(array_column($complaints, 'rating'));
$avg_rating = $ratings ? round(array_sum($ratings) / count($ratings), 1) : null;

// Build filter description.
$filter_desc = [];
if ($date_from) $filter_desc[] = 'From: ' . $date_from;
if ($date_to)   $filter_desc[] = 'To: ' . $date_to;
if ($status)    $filter_desc[] = 'Status: ' . $status;
if ($category)  $filter_desc[] = 'Category: ' . $category;
if ($priority)  $filter_desc[] = 'Priority: ' . $priority;
$filter_str = $filter_desc ? implode(' | ', $filter_desc) : 'All records';

// Build HTML for PDF.
$rows_html = '';
foreach ($complaints as $c) {
    $rows_html .= '<tr>
        <td>' . htmlspecialchars($c['tracking_id'], ENT_QUOTES, 'UTF-8') . '</td>
        <td>' . ($c['is_anonymous'] ? 'Anonymous' : htmlspecialchars($c['student_name'], ENT_QUOTES, 'UTF-8')) . '</td>
        <td>' . htmlspecialchars(mb_substr($c['title'], 0, 50), ENT_QUOTES, 'UTF-8') . (mb_strlen($c['title']) > 50 ? '...' : '') . '</td>
        <td>' . htmlspecialchars($c['category'], ENT_QUOTES, 'UTF-8') . '</td>
        <td>' . ($c['dept_name'] ? htmlspecialchars($c['dept_name'], ENT_QUOTES, 'UTF-8') : '--') . '</td>
        <td>' . htmlspecialchars($c['status'], ENT_QUOTES, 'UTF-8') . '</td>
        <td>' . date('d M Y', strtotime($c['created_at'])) . '</td>
    </tr>';
}

$html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 11px; color: #0a0b0d; margin:0; }
  .header { background:#0052FF; color:#fff; padding:16px 24px; }
  .header h1 { margin:0; font-size:18px; }
  .header p  { margin:4px 0 0; font-size:11px; opacity:0.85; }
  .body { padding: 0 24px 24px; }
  .meta { display:flex; justify-content:space-between; margin:16px 0 8px; font-size:11px; color:#5b616e; }
  .summary { display:flex; gap:16px; margin:12px 0 16px; }
  .summary-box { border:1px solid #dee1e6; border-radius:6px; padding:8px 16px; min-width:100px; text-align:center; }
  .summary-box .val { font-size:20px; font-weight:700; color:#0052FF; }
  .summary-box .lbl { font-size:10px; color:#5b616e; }
  table { width:100%; border-collapse:collapse; margin-top:8px; }
  th { background:#f5f8ff; text-align:left; padding:6px 8px; font-size:10px; font-weight:600; border-bottom:2px solid #dee1e6; }
  td { padding:5px 8px; border-bottom:1px solid #f0f0f0; font-size:10px; vertical-align:top; }
  tr:nth-child(even) td { background:#fafafa; }
  .footer { text-align:center; color:#5b616e; font-size:10px; margin-top:16px; border-top:1px solid #dee1e6; padding-top:8px; }
  @page { margin: 15mm; }
</style>
</head>
<body>
<div class="header">
  <h1>Mount Kigali University</h1>
  <p>Complaints and Feedback Management System - Complaints Report</p>
</div>
<div class="body">
  <div class="meta">
    <span>Generated: ' . date('d F Y, H:i') . '</span>
    <span>Filters: ' . htmlspecialchars($filter_str, ENT_QUOTES, 'UTF-8') . '</span>
  </div>
  <div class="summary">
    <div class="summary-box"><div class="val">' . $total . '</div><div class="lbl">Total</div></div>
    <div class="summary-box"><div class="val">' . $resolved . '</div><div class="lbl">Resolved</div></div>
    <div class="summary-box"><div class="val">' . $pending . '</div><div class="lbl">Pending</div></div>
    <div class="summary-box"><div class="val">' . ($avg_rating ?: '--') . '</div><div class="lbl">Avg. rating</div></div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Tracking ID</th><th>Student</th><th>Title</th>
        <th>Category</th><th>Department</th><th>Status</th><th>Date</th>
      </tr>
    </thead>
    <tbody>' . $rows_html . '</tbody>
  </table>
</div>
<div class="footer">Mount Kigali University, Kigali, Rwanda | Page <span class="pagenum"></span></div>
</body></html>';

// Use Dompdf if available.
$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendor)) {
    require_once $vendor;

  $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', false);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('mku-complaints-report-' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
} else {
    // Fallback: serve as HTML if Dompdf not installed.
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
}
exit;
