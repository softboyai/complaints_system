<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('admin');
$pdo = get_db();

// Run escalation check on every admin dashboard load.
$escalated_count = check_escalations($pdo);

// Stat cards.
$stats = $pdo->query(
    'SELECT
       COUNT(*) AS total,
       SUM(status NOT IN (\'Resolved\',\'Closed\',\'Rejected\')) AS open_count,
       SUM(status = \'Resolved\') AS resolved_count,
       ROUND(AVG(CASE WHEN status IN (\'Resolved\',\'Closed\') THEN DATEDIFF(updated_at, created_at) END), 1) AS avg_days
     FROM complaints'
)->fetch();

$avg_rating = $pdo->query(
    'SELECT ROUND(AVG(rating), 1) AS avg FROM complaints WHERE rating IS NOT NULL'
)->fetchColumn();

$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();

// Recent complaints.
$recent = $pdo->query(
    'SELECT c.id, c.tracking_id, c.title, c.category, c.status, c.priority,
            c.is_anonymous, c.created_at, u.full_name AS student_name,
            d.name AS dept_name
     FROM complaints c
     JOIN users u ON u.id = c.student_id
     LEFT JOIN departments d ON d.id = c.department_id
     ORDER BY c.created_at DESC LIMIT 10'
)->fetchAll();

$page_title  = 'Admin Dashboard';
$active_nav  = 'dashboard';
$load_charts = true;
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Admin Dashboard</h2>
    <p class="page-subtitle">Full system overview for all departments and complaints.</p>
  </div>
</div>

<?php if ($escalated_count > 0): ?>
  <div class="alert alert--warning" role="alert">
    <?= icon('alert-circle') ?>
    <?= $escalated_count ?> complaint<?= $escalated_count > 1 ? 's were' : ' was' ?> auto-escalated due to no action after 7 days.
  </div>
<?php endif; ?>

<!-- Stat cards -->
<div class="stat-grid stat-grid--4">
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--blue"><?= icon('file-text') ?></div>
    <div class="stat-card__value"><?= (int)$stats['total'] ?></div>
    <div class="stat-card__label">Total complaints</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--orange"><?= icon('clock') ?></div>
    <div class="stat-card__value"><?= (int)$stats['open_count'] ?></div>
    <div class="stat-card__label">Open</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--green"><?= icon('check-circle') ?></div>
    <div class="stat-card__value"><?= (int)$stats['resolved_count'] ?></div>
    <div class="stat-card__label">Resolved</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--yellow"><?= icon('clock') ?></div>
    <div class="stat-card__value"><?= $stats['avg_days'] ? $stats['avg_days'] . 'd' : '--' ?></div>
    <div class="stat-card__label">Avg. resolution time</div>
  </div>
</div>

<div class="stat-grid stat-grid--2" style="margin-top:16px">
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--blue"><?= icon('users') ?></div>
    <div class="stat-card__value"><?= (int)$total_users ?></div>
    <div class="stat-card__label">Registered students</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--yellow"><?= icon('star') ?></div>
    <div class="stat-card__value"><?= $avg_rating ?: '--' ?></div>
    <div class="stat-card__label">Avg. satisfaction rating</div>
  </div>
</div>

<!-- Charts -->
<div class="chart-grid" data-role="admin">
  <div class="card chart-card">
    <h3 class="card__section-title">Complaints by status</h3>
    <div class="chart-wrap chart-wrap--doughnut">
      <canvas id="chart-status" aria-label="Complaints by status" role="img"></canvas>
    </div>
  </div>
  <div class="card chart-card">
    <h3 class="card__section-title">Complaints by category</h3>
    <div class="chart-wrap">
      <canvas id="chart-category" aria-label="Complaints by category" role="img"></canvas>
    </div>
  </div>
</div>
<div class="card chart-card chart-card--full" style="margin-top:16px">
  <h3 class="card__section-title">Monthly trend (last 12 months)</h3>
  <div class="chart-wrap">
    <canvas id="chart-trend" aria-label="Monthly complaint trend" role="img"></canvas>
  </div>
</div>

<!-- Recent complaints table -->
<div class="card" style="margin-top:24px">
  <div class="card__header">
    <h3>Recent complaints</h3>
    <a href="<?= APP_URL ?>/admin/complaints.php" class="btn btn--ghost btn--sm">View all</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th scope="col">Tracking ID</th>
          <th scope="col">Student</th>
          <th scope="col">Title</th>
          <th scope="col">Department</th>
          <th scope="col">Priority</th>
          <th scope="col">Status</th>
          <th scope="col">Date</th>
          <th scope="col"><span class="sr-only">Action</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $c): ?>
        <tr>
          <td><code class="tracking-id"><?= e($c['tracking_id']) ?></code></td>
          <td><?= $c['is_anonymous'] ? '<em>Anonymous</em>' : e($c['student_name']) ?></td>
          <td class="table__title"><?= e($c['title']) ?></td>
          <td><?= $c['dept_name'] ? e($c['dept_name']) : '<em>Unassigned</em>' ?></td>
          <td><span class="badge badge--<?= priority_badge_class($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
          <td><span class="badge badge--<?= status_badge_class($c['status']) ?>"><?= e($c['status']) ?></span></td>
          <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
          <td>
            <a href="<?= APP_URL ?>/admin/complaints.php?id=<?= (int)$c['id'] ?>"
               class="btn btn--ghost btn--xs">Manage</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
