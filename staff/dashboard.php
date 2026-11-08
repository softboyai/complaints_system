<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('staff');
$pdo  = get_db();
$user = get_logged_in_user();
$dept = (int)($user['department_id'] ?? 0);

// Run escalation check on every staff dashboard load.
check_escalations($pdo);

// Stat cards for this department.
$stats = $pdo->prepare(
    'SELECT
       COUNT(*) AS total,
       SUM(status NOT IN (\'Resolved\',\'Closed\',\'Rejected\')) AS open_count,
       SUM(status = \'Resolved\' AND MONTH(updated_at) = MONTH(NOW()) AND YEAR(updated_at) = YEAR(NOW())) AS resolved_month,
       ROUND(AVG(CASE WHEN status = \'Resolved\' AND rating IS NOT NULL THEN rating END), 1) AS avg_rating
     FROM complaints
     WHERE department_id = ?'
);
$stats->execute([$dept]);
$s = $stats->fetch();

// Average resolution time (days).
$res_time = $pdo->prepare(
    'SELECT ROUND(AVG(DATEDIFF(updated_at, created_at)), 1) AS avg_days
     FROM complaints WHERE department_id = ? AND status IN (\'Resolved\',\'Closed\')'
);
$res_time->execute([$dept]);
$avg_days = $res_time->fetchColumn();

// Recent complaints (last 8).
$recent = $pdo->prepare(
    'SELECT c.id, c.tracking_id, c.title, c.category, c.status, c.priority,
            c.is_anonymous, c.created_at, u.full_name AS student_name
     FROM complaints c
     JOIN users u ON u.id = c.student_id
     WHERE c.department_id = ?
     ORDER BY c.updated_at DESC LIMIT 8'
);
$recent->execute([$dept]);
$recent_complaints = $recent->fetchAll();

$page_title  = 'Dashboard';
$active_nav  = 'dashboard';
$load_charts = true;
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Staff Dashboard</h2>
    <p class="page-subtitle">Complaints assigned to your department.</p>
  </div>
  <a href="<?= APP_URL ?>/staff/complaints.php" class="btn btn--secondary btn--sm">
    <?= icon('file-text') ?> All complaints
  </a>
</div>

<!-- Stat cards -->
<div class="stat-grid stat-grid--4">
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--blue"><?= icon('file-text') ?></div>
    <div class="stat-card__value"><?= (int)($s['total'] ?? 0) ?></div>
    <div class="stat-card__label">Total assigned</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--orange"><?= icon('clock') ?></div>
    <div class="stat-card__value"><?= (int)($s['open_count'] ?? 0) ?></div>
    <div class="stat-card__label">Open</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--green"><?= icon('check-circle') ?></div>
    <div class="stat-card__value"><?= (int)($s['resolved_month'] ?? 0) ?></div>
    <div class="stat-card__label">Resolved this month</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--yellow"><?= icon('clock') ?></div>
    <div class="stat-card__value"><?= $avg_days ? $avg_days . 'd' : '--' ?></div>
    <div class="stat-card__label">Avg. resolution time</div>
  </div>
</div>

<!-- Charts -->
<div class="chart-grid" data-role="staff" data-dept="<?= $dept ?>">
  <div class="card chart-card">
    <h3 class="card__section-title">Complaints by status</h3>
    <div class="chart-wrap chart-wrap--doughnut">
      <canvas id="chart-status" aria-label="Complaints by status" role="img"></canvas>
    </div>
  </div>
  <div class="card chart-card">
    <h3 class="card__section-title">Monthly trend</h3>
    <div class="chart-wrap">
      <canvas id="chart-trend" aria-label="Monthly complaint trend" role="img"></canvas>
    </div>
  </div>
</div>

<!-- Recent complaints -->
<div class="card" style="margin-top:24px">
  <div class="card__header">
    <h3>Recent complaints</h3>
    <a href="<?= APP_URL ?>/staff/complaints.php" class="btn btn--ghost btn--sm">View all</a>
  </div>

  <?php if (empty($recent_complaints)): ?>
    <div class="empty-state">
      <?= icon('check-circle') ?>
      <h4>No complaints assigned to your department</h4>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th scope="col">Tracking ID</th>
            <th scope="col">Student</th>
            <th scope="col">Title</th>
            <th scope="col">Priority</th>
            <th scope="col">Status</th>
            <th scope="col">Date</th>
            <th scope="col"><span class="sr-only">Action</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_complaints as $c): ?>
          <tr>
            <td><code class="tracking-id"><?= e($c['tracking_id']) ?></code></td>
            <td><?= $c['is_anonymous'] ? '<em>Anonymous</em>' : e($c['student_name']) ?></td>
            <td class="table__title"><?= e($c['title']) ?></td>
            <td><span class="badge badge--<?= priority_badge_class($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
            <td><span class="badge badge--<?= status_badge_class($c['status']) ?>"><?= e($c['status']) ?></span></td>
            <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
            <td>
              <a href="<?= APP_URL ?>/staff/complaint-details.php?id=<?= (int)$c['id'] ?>"
                 class="btn btn--ghost btn--xs">Manage</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
