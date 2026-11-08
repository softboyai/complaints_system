<?php
// PDF receipt for a single complaint. Student ownership is verified.
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('student');
$pdo  = get_db();
$user = get_logged_in_user();
$uid  = $user['id'];

$cid = (int)($_GET['id'] ?? 0);
if (!$cid) {
    header('Location: ' . APP_URL . '/student/my-complaints.php');
    exit;
}

// Ownership check.
$stmt = $pdo->prepare('SELECT * FROM complaints WHERE id = ? AND student_id = ? LIMIT 1');
$stmt->execute([$cid, $uid]);
$complaint = $stmt->fetch();
if (!$complaint) {
    set_flash('flash_error', 'Receipt not available.');
    header('Location: ' . APP_URL . '/student/my-complaints.php');
    exit;
}

// Fetch related data.
$history = $pdo->prepare(
    'SELECT sh.*, u.full_name FROM complaint_status_history sh
     JOIN users u ON u.id = sh.changed_by
     WHERE sh.complaint_id = ? ORDER BY sh.changed_at ASC'
);
$history->execute([$cid]);
$timeline = $history->fetchAll();

$responses = $pdo->prepare(
    'SELECT r.*, u.full_name, u.role FROM responses r
     JOIN users u ON u.id = r.author_id
     WHERE r.complaint_id = ? AND r.is_internal = 0 ORDER BY r.created_at ASC'
);
$responses->execute([$cid]);
$resp_list = $responses->fetchAll();

$dept_name = '';
if ($complaint['department_id']) {
    $d = $pdo->prepare('SELECT name FROM departments WHERE id = ?');
    $d->execute([$complaint['department_id']]);
    $dept_name = $d->fetchColumn() ?: '';
}

// Try to use Dompdf. Fall back to a plain HTML page.
$vendor = dirname(__DIR__) . '/vendor/autoload.php';
$use_pdf = file_exists($vendor);
if ($use_pdf) {
    require_once $vendor;
}

// Build HTML content.
$html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #0a0b0d; margin: 0; padding: 0; }
  .header { background: #0052FF; color: #fff; padding: 24px 32px; margin-bottom: 24px; }
  .header h1 { margin: 0; font-size: 20px; }
  .header p  { margin: 4px 0 0; font-size: 12px; opacity: 0.85; }
  .body { padding: 0 32px 32px; }
  .receipt-title { font-size: 18px; font-weight: bold; margin-bottom: 8px; }
  .meta { color: #5b616e; font-size: 12px; margin-bottom: 24px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #dee1e6; }
  th { background: #f5f8ff; font-weight: 600; }
  .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
  .badge-blue   { background: #dce8ff; color: #0052FF; }
  .badge-green  { background: #d4f7e7; color: #05B169; }
  .badge-orange { background: #fff0d6; color: #F4A100; }
  .badge-red    { background: #fde0e0; color: #CF202F; }
  .badge-purple { background: #ede8ff; color: #7B2FBE; }
  .badge-grey   { background: #f0f0f0; color: #5b616e; }
  .section-title { font-size: 14px; font-weight: 700; margin: 24px 0 8px; border-bottom: 2px solid #0052FF; padding-bottom: 4px; }
  .desc { background: #f5f8ff; border: 1px solid #dee1e6; border-radius: 6px; padding: 12px; line-height: 1.6; }
  .timeline li { margin-bottom: 8px; }
  .response { border: 1px solid #dee1e6; border-radius: 6px; padding: 10px; margin-bottom: 8px; background: #fafafa; }
  .response-author { font-weight: 600; margin-bottom: 4px; }
  .footer { text-align: center; color: #5b616e; font-size: 11px; border-top: 1px solid #dee1e6; padding: 16px 32px; margin-top: 32px; }
</style>
</head>
<body>
<div class="header">
  <h1>Mount Kigali University</h1>
  <p>Complaints and Feedback Management System - Complaint Receipt</p>
</div>
<div class="body">
  <p class="receipt-title">' . htmlspecialchars($complaint['title'], ENT_QUOTES, 'UTF-8') . '</p>
  <p class="meta">Generated: ' . date('d F Y, H:i') . ' | Tracking ID: <strong>' . htmlspecialchars($complaint['tracking_id'], ENT_QUOTES, 'UTF-8') . '</strong></p>

  <div class="section-title">Complaint Information</div>
  <table>
    <tr><th>Tracking ID</th><td>' . htmlspecialchars($complaint['tracking_id'], ENT_QUOTES, 'UTF-8') . '</td></tr>
    <tr><th>Category</th><td>' . htmlspecialchars($complaint['category'], ENT_QUOTES, 'UTF-8') . '</td></tr>
    <tr><th>Priority</th><td>' . htmlspecialchars($complaint['priority'], ENT_QUOTES, 'UTF-8') . '</td></tr>
    <tr><th>Status</th><td>' . htmlspecialchars($complaint['status'], ENT_QUOTES, 'UTF-8') . '</td></tr>
    <tr><th>Department</th><td>' . ($dept_name ? htmlspecialchars($dept_name, ENT_QUOTES, 'UTF-8') : 'Not yet assigned') . '</td></tr>
    <tr><th>Submitted</th><td>' . date('d F Y, H:i', strtotime($complaint['created_at'])) . '</td></tr>
    <tr><th>Last updated</th><td>' . date('d F Y, H:i', strtotime($complaint['updated_at'])) . '</td></tr>
  </table>

  <div class="section-title">Description</div>
  <div class="desc">' . nl2br(htmlspecialchars($complaint['description'], ENT_QUOTES, 'UTF-8')) . '</div>

  <div class="section-title">Status History</div>
  <ul class="timeline">';

foreach ($timeline as $t) {
    $html .= '<li>' . date('d M Y, H:i', strtotime($t['changed_at'])) . ' - <strong>' .
        htmlspecialchars($t['new_status'], ENT_QUOTES, 'UTF-8') . '</strong>';
    if ($t['note']) $html .= ': ' . htmlspecialchars($t['note'], ENT_QUOTES, 'UTF-8');
    $html .= '</li>';
}

$html .= '</ul>';

if (!empty($resp_list)) {
    $html .= '<div class="section-title">Responses</div>';
    foreach ($resp_list as $r) {
        $html .= '<div class="response">
          <div class="response-author">' . htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8') .
                 ' <span style="font-weight:normal;color:#5b616e;">(' . ucfirst($r['role']) . ')</span> - ' .
                 date('d M Y, H:i', strtotime($r['created_at'])) . '</div>
          <div>' . nl2br(htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8')) . '</div>
        </div>';
    }
}

$html .= '
</div>
<div class="footer">Mount Kigali University, Kigali, Rwanda | complaints@mku.ac.rw | This document was generated automatically.</div>
</body></html>';

if ($use_pdf) {
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', false);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('receipt-' . $complaint['tracking_id'] . '.pdf', ['Attachment' => true]);
} else {
    // Dompdf not installed: serve the HTML directly.
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
}
exit;
