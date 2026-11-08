<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('student');
$pdo  = get_db();
$user = get_logged_in_user();
$uid  = $user['id'];

// Stat cards.
$stats = $pdo->prepare(
    'SELECT
       COUNT(*) AS total,
       SUM(status NOT IN (\'Resolved\',\'Closed\',\'Rejected\')) AS open_count,
       SUM(status = \'Resolved\') AS resolved_count,
       ROUND(AVG(CASE WHEN rating IS NOT NULL THEN rating END), 1) AS avg_rating
     FROM complaints WHERE student_id = ?'
);
$stats->execute([$uid]);
$s = $stats->fetch();

// Recent complaints (last 5).
$recent = $pdo->prepare(
    'SELECT id, tracking_id, title, category, status, priority, created_at
     FROM complaints WHERE student_id = ?
     ORDER BY created_at DESC LIMIT 5'
);
$recent->execute([$uid]);
$recent_complaints = $recent->fetchAll();

$page_title  = 'Dashboard';
$active_nav  = 'dashboard';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Welcome back, <?= e(explode(' ', $user['full_name'])[0]) ?></h2>
    <p class="page-subtitle">Here is a summary of your complaints and feedback.</p>
  </div>
  <a href="<?= APP_URL ?>/student/submit-complaint.php" class="btn btn--primary">
    <?= icon('plus-circle') ?> Submit a complaint
  </a>
</div>

<!-- Stat cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--blue"><?= icon('file-text') ?></div>
    <div class="stat-card__value"><?= (int)($s['total'] ?? 0) ?></div>
    <div class="stat-card__label">Total complaints</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--orange"><?= icon('clock') ?></div>
    <div class="stat-card__value"><?= (int)($s['open_count'] ?? 0) ?></div>
    <div class="stat-card__label">Open</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--green"><?= icon('check-circle') ?></div>
    <div class="stat-card__value"><?= (int)($s['resolved_count'] ?? 0) ?></div>
    <div class="stat-card__label">Resolved</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--yellow"><?= icon('star') ?></div>
    <div class="stat-card__value"><?= $s['avg_rating'] ? number_format((float)$s['avg_rating'], 1) : '--' ?></div>
    <div class="stat-card__label">Avg. satisfaction</div>
  </div>
</div>

<!-- Recent complaints -->
<div class="card" style="margin-top:24px">
  <div class="card__header">
    <h3>Recent complaints</h3>
    <a href="<?= APP_URL ?>/student/my-complaints.php" class="btn btn--ghost btn--sm">View all</a>
  </div>

  <?php if (empty($recent_complaints)): ?>
    <div class="empty-state">
      <?= icon('file-text') ?>
      <h4>No complaints yet</h4>
      <p>When you submit a complaint, it will appear here.</p>
      <a href="<?= APP_URL ?>/student/submit-complaint.php" class="btn btn--primary btn--sm">Submit your first complaint</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table" aria-label="Recent complaints">
        <thead>
          <tr>
            <th scope="col">Tracking ID</th>
            <th scope="col">Title</th>
            <th scope="col">Category</th>
            <th scope="col">Status</th>
            <th scope="col">Date</th>
            <th scope="col"><span class="sr-only">Action</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_complaints as $c): ?>
          <tr>
            <td><code class="tracking-id"><?= e($c['tracking_id']) ?></code></td>
            <td class="table__title"><?= e($c['title']) ?></td>
            <td><?= e($c['category']) ?></td>
            <td><span class="badge badge--<?= status_badge_class($c['status']) ?>"><?= e($c['status']) ?></span></td>
            <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
            <td>
              <a href="<?= APP_URL ?>/student/complaint-details.php?id=<?= (int)$c['id'] ?>"
                 class="btn btn--ghost btn--xs">View</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
