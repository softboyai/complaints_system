<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('admin');
$pdo = get_db();

$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to']   ?? '');
$status    = trim($_GET['status']    ?? '');
$category  = trim($_GET['category']  ?? '');
$priority  = trim($_GET['priority']  ?? '');
$dept_id   = (int)($_GET['dept'] ?? 0);

$where  = ['1=1'];
$params = [];

if ($date_from !== '') { $where[] = 'DATE(c.created_at) >= ?'; $params[] = $date_from; }
if ($date_to   !== '') { $where[] = 'DATE(c.created_at) <= ?'; $params[] = $date_to;   }
if ($status    !== '') { $where[] = 'c.status = ?';            $params[] = $status;    }
if ($category  !== '') { $where[] = 'c.category = ?';          $params[] = $category;  }
if ($priority  !== '') { $where[] = 'c.priority = ?';          $params[] = $priority;  }
if ($dept_id > 0)      { $where[] = 'c.department_id = ?';     $params[] = $dept_id;   }

$where_sql = 'WHERE ' . implode(' AND ', $where);

$list_stmt = $pdo->prepare(
    "SELECT c.id, c.tracking_id, c.title, c.category, c.status, c.priority,
            c.is_anonymous, c.created_at, c.updated_at, c.rating,
            u.full_name AS student_name, u.reg_number,
            d.name AS dept_name
     FROM complaints c
     JOIN users u ON u.id = c.student_id
     LEFT JOIN departments d ON d.id = c.department_id
     {$where_sql}
     ORDER BY c.created_at DESC LIMIT 500"
);
$list_stmt->execute($params);
$complaints = $list_stmt->fetchAll();

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

$query = http_build_query(array_filter([
    'date_from' => $date_from, 'date_to' => $date_to,
    'status'    => $status,    'category' => $category,
    'priority'  => $priority,  'dept' => $dept_id ?: '',
]));

$page_title = 'Reports';
$active_nav = 'reports';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <h2 class="page-title">System Reports</h2>
</div>

<div class="card" style="margin-bottom:24px">
  <h3 class="card__section-title">Filter report</h3>
  <form method="GET" action="" class="filters-bar__form filters-bar__form--wrap">
    <div class="form-row form-row--half">
      <div class="form-group">
        <label class="form-label" for="date_from">From</label>
        <input type="date" id="date_from" name="date_from" class="form-input" value="<?= e($date_from) ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="date_to">To</label>
        <input type="date" id="date_to" name="date_to" class="form-input" value="<?= e($date_to) ?>">
      </div>
    </div>
    <div class="form-row form-row--four">
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-input form-select">
          <option value="">All</option>
          <?php foreach (['Submitted','Under Review','In Progress','Resolved','Closed','Rejected','Escalated'] as $st): ?>
            <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Category</label>
        <select name="category" class="form-input form-select">
          <option value="">All</option>
          <?php foreach (complaint_categories() as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Priority</label>
        <select name="priority" class="form-input form-select">
          <option value="">All</option>
          <?php foreach (['Low','Medium','High'] as $p): ?>
            <option value="<?= $p ?>" <?= $priority === $p ? 'selected' : '' ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Department</label>
        <select name="dept" class="form-input form-select">
          <option value="">All departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $dept_id === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn--secondary"><?= icon('filter') ?> Apply</button>
      <a href="?" class="btn btn--ghost">Clear</a>
    </div>
  </form>
</div>

<div class="card report-download-bar" style="margin-bottom:24px">
  <span><?= count($complaints) ?> records match the current filters.</span>
  <div class="report-download-bar__actions">
    <a href="<?= APP_URL ?>/reports/pdf.php?<?= $query ?>" target="_blank"
       class="btn btn--primary btn--sm report-download-btn">
      <?= icon('download') ?> Download PDF
    </a>
    <a href="<?= APP_URL ?>/reports/excel.php?<?= $query ?>" target="_blank"
       class="btn btn--secondary btn--sm report-download-btn">
      <?= icon('download') ?> Download Excel
    </a>
  </div>
</div>

<?php if (empty($complaints)): ?>
  <div class="empty-state card"><?= icon('bar-chart') ?><h4>No data matches these filters</h4></div>
<?php else: ?>
  <div class="card table-card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Tracking ID</th><th>Student</th><th>Title</th><th>Department</th>
            <th>Category</th><th>Priority</th><th>Status</th><th>Date</th><th>Rating</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($complaints as $c): ?>
          <tr>
            <td><code class="tracking-id"><?= e($c['tracking_id']) ?></code></td>
            <td><?= $c['is_anonymous'] ? '<em>Anonymous</em>' : e($c['student_name']) ?></td>
            <td><?= e($c['title']) ?></td>
            <td><?= $c['dept_name'] ? e($c['dept_name']) : '--' ?></td>
            <td><?= e($c['category']) ?></td>
            <td><span class="badge badge--<?= priority_badge_class($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
            <td><span class="badge badge--<?= status_badge_class($c['status']) ?>"><?= e($c['status']) ?></span></td>
            <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
            <td><?= $c['rating'] ? $c['rating'] . '/5' : '--' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
