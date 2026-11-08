<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('student');
$pdo  = get_db();
$user = get_logged_in_user();
$uid  = $user['id'];

// Filters from query string.
$search   = trim($_GET['search']   ?? '');
$status   = trim($_GET['status']   ?? '');
$category = trim($_GET['category'] ?? '');
$page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Build WHERE clause.
$where  = ['c.student_id = ?'];
$params = [$uid];

if ($search !== '') {
    $where[]  = '(c.title LIKE ? OR c.tracking_id LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($status !== '') {
    $where[]  = 'c.status = ?';
    $params[] = $status;
}
if ($category !== '') {
    $where[]  = 'c.category = ?';
    $params[] = $category;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// Total count.
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c {$where_sql}");
$count_stmt->execute($params);
$total = (int) $count_stmt->fetchColumn();

$pag = paginate($total, $per_page, $page_num);

// Fetch page.
$list_stmt = $pdo->prepare(
    "SELECT c.id, c.tracking_id, c.title, c.category, c.status, c.priority, c.is_anonymous, c.created_at, c.updated_at
     FROM complaints c {$where_sql}
     ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?"
);
$params[] = $per_page;
$params[] = $pag['offset'];
$list_stmt->execute($params);
$complaints = $list_stmt->fetchAll();

$statuses   = ['Submitted','Under Review','In Progress','Resolved','Closed','Rejected','Escalated'];
$categories = complaint_categories();
$filter_params = array_filter(['search' => $search, 'status' => $status, 'category' => $category]);

$page_title = 'My complaints';
$active_nav = 'complaints';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">My complaints</h2>
    <p class="page-subtitle"><?= $total ?> complaint<?= $total !== 1 ? 's' : '' ?> found</p>
  </div>
  <a href="<?= APP_URL ?>/student/submit-complaint.php" class="btn btn--primary">
    <?= icon('plus-circle') ?> New complaint
  </a>
</div>

<!-- Filters -->
<div class="filters-bar card">
  <form method="GET" action="" id="filter-form" class="filters-bar__form">
    <div class="filters-bar__search">
      <span class="filters-bar__search-icon" aria-hidden="true"><?= icon('search') ?></span>
      <input type="text" name="search" id="search" class="form-input filters-bar__input"
             placeholder="Search by title or tracking ID"
             value="<?= e($search) ?>" aria-label="Search complaints">
    </div>

    <select name="status" class="form-input form-select filters-bar__select" aria-label="Filter by status">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $st): ?>
        <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="category" class="form-input form-select filters-bar__select" aria-label="Filter by category">
      <option value="">All categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn--secondary btn--sm"><?= icon('filter') ?> Filter</button>
    <?php if (!empty($filter_params)): ?>
      <a href="?" class="btn btn--ghost btn--sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Complaints list -->
<?php if (empty($complaints)): ?>
  <div class="empty-state card">
    <?= icon('file-text') ?>
    <h4>No complaints found</h4>
    <?php if (!empty($filter_params)): ?>
      <p>Try adjusting your filters.</p>
      <a href="?" class="btn btn--ghost btn--sm">Clear filters</a>
    <?php else: ?>
      <p>You have not submitted any complaints yet.</p>
      <a href="<?= APP_URL ?>/student/submit-complaint.php" class="btn btn--primary btn--sm">Submit your first complaint</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card table-card">
    <div class="table-wrap">
      <table class="table" aria-label="My complaints">
        <thead>
          <tr>
            <th scope="col">Tracking ID</th>
            <th scope="col">Title</th>
            <th scope="col">Category</th>
            <th scope="col">Priority</th>
            <th scope="col">Status</th>
            <th scope="col">Submitted</th>
            <th scope="col"><span class="sr-only">Action</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($complaints as $c): ?>
          <tr>
            <td><code class="tracking-id"><?= e($c['tracking_id']) ?></code></td>
            <td class="table__title">
              <?= e($c['title']) ?>
              <?php if ($c['is_anonymous']): ?>
                <span class="badge badge--grey badge--xs" title="Anonymous submission">Anon</span>
              <?php endif; ?>
            </td>
            <td><?= e($c['category']) ?></td>
            <td><span class="badge badge--<?= priority_badge_class($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
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

    <!-- Pagination -->
    <?php if ($pag['total_pages'] > 1): ?>
    <nav class="pagination" aria-label="Complaints pagination">
      <?php if ($pag['current_page'] > 1): ?>
        <a href="<?= pagination_url($pag['current_page'] - 1, $filter_params) ?>"
           class="btn btn--ghost btn--sm" aria-label="Previous page">
          <?= icon('chevron-left') ?> Previous
        </a>
      <?php endif; ?>

      <span class="pagination__info">
        Page <?= $pag['current_page'] ?> of <?= $pag['total_pages'] ?>
      </span>

      <?php if ($pag['current_page'] < $pag['total_pages']): ?>
        <a href="<?= pagination_url($pag['current_page'] + 1, $filter_params) ?>"
           class="btn btn--ghost btn--sm" aria-label="Next page">
          Next <?= icon('chevron-right') ?>
        </a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
