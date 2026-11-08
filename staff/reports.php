<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('staff');
$pdo  = get_db();
$user = get_logged_in_user();
$dept = (int)($user['department_id'] ?? 0);

$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to']   ?? '');
$status    = trim($_GET['status']    ?? '');
$category  = trim($_GET['category']  ?? '');
$priority  = trim($_GET['priority']  ?? '');

$where  = ['c.department_id = ?'];
$params = [$dept];

if ($date_from !== '') { $where[] = 'DATE(c.created_at) >= ?'; $params[] = $date_from; }
if ($date_to   !== '') { $where[] = 'DATE(c.created_at) <= ?'; $params[] = $date_to;   }
if ($status    !== '') { $where[] = 'c.status = ?';            $params[] = $status;    }
if ($category  !== '') { $where[] = 'c.category = ?';          $params[] = $category;  }
if ($priority  !== '') { $where[] = 'c.priority = ?';          $params[] = $priority;  }

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
     ORDER BY c.created_at DESC
     LIMIT 200"
);
$list_stmt->execute($params);
$complaints = $list_stmt->fetchAll();

// Build query string for download links.
$query = http_build_query(array_filter([
    'date_from'  => $date_from,
    'date_to'    => $date_to,
    'status'     => $status,
    'category'   => $category,
    'priority'   => $priority,
    'dept'       => $dept,
]));

$page_title = 'Reports';
$active_nav = 'reports';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <h2 class="page-title">Reports</h2>
</div>

<!-- Filter form -->
<div class="card" style="margin-bottom:24px">
  <h3 class="card__section-title">Filter report</h3>
  <form method="GET" action="" class="filters-bar__form filters-bar__form--wrap" id="report-filter">
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
    <div class="form-row form-row--three">
      <div class="form-group">
        <label class="form-label" for="r_status">Status</label>
        <select id="r_status" name="status" class="form-input form-select">
          <option value="">All statuses</option>
          <?php foreach (['Submitted','Under Review','In Progress','Resolved','Closed','Rejected','Escalated'] as $st): ?>
            <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="r_category">Category</label>
        <select id="r_category" name="category" class="form-input form-select">
          <option value="">All categories</option>
          <?php foreach (complaint_categories() as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="r_priority">Priority</label>
        <select id="r_priority" name="priority" class="form-input form-select">
          <option value="">All priorities</option>
          <?php foreach (['Low','Medium','High'] as $p): ?>
            <option value="<?= $p ?>" <?= $priority === $p ? 'selected' : '' ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn--secondary"><?= icon('filter') ?> Apply filters</button>
      <a href="?" class="btn btn--ghost">Clear</a>
    </div>
  </form>
</div>

<!-- Download buttons -->
<div class="card report-download-bar" style="margin-bottom:24px">
  <span><?= count($complaints) ?> records match the current filters.</span>
  <div class="report-download-bar__actions">
    <a href="<?= APP_URL ?>/reports/pdf.php?<?= $query ?>" target="_blank"
       class="btn btn--primary btn--sm report-download-btn" data-format="pdf">
      <?= icon('download') ?> <span class="btn__label">Download PDF</span>
      <span class="btn__spinner" hidden aria-hidden="true"></span>
    </a>
    <a href="<?= APP_URL ?>/reports/excel.php?<?= $query ?>" target="_blank"
       class="btn btn--secondary btn--sm report-download-btn" data-format="csv">
      <?= icon('download') ?> <span class="btn__label">Download Excel</span>
      <span class="btn__spinner" hidden aria-hidden="true"></span>
    </a>
  </div>
</div>

<!-- Preview table -->
<?php if (empty($complaints)): ?>
  <div class="empty-state card">
    <?= icon('bar-chart') ?>
    <h4>No data matches these filters</h4>
    <p>Try changing the date range or clearing some filters.</p>
  </div>
<?php else: ?>
  <div class="card table-card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th scope="col">Tracking ID</th>
            <th scope="col">Student</th>
            <th scope="col">Title</th>
            <th scope="col">Category</th>
            <th scope="col">Priority</th>
            <th scope="col">Status</th>
            <th scope="col">Submitted</th>
            <th scope="col">Rating</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($complaints as $c): ?>
          <tr>
            <td><code class="tracking-id"><?= e($c['tracking_id']) ?></code></td>
            <td><?= $c['is_anonymous'] ? '<em>Anonymous</em>' : e($c['student_name']) ?></td>
            <td><?= e($c['title']) ?></td>
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
