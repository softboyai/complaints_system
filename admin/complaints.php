<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

require_role('admin');
$pdo  = get_db();
$user = get_logged_in_user();

// Handle assign to department.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    if ($_POST['action'] === 'assign') {
        $cid     = (int)($_POST['complaint_id'] ?? 0);
        $dept_id = (int)($_POST['department_id'] ?? 0);
        if ($cid && $dept_id) {
            $pdo->prepare('UPDATE complaints SET department_id = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$dept_id, $cid]);
            set_flash('flash_success', 'Complaint assigned.');
        }
        header('Location: ' . APP_URL . '/admin/complaints.php');
        exit;
    }
}

// Detail view for a single complaint.
$view_id = (int)($_GET['id'] ?? 0);
if ($view_id) {
    // Delegate to a simple detail view inline.
    $stmt = $pdo->prepare(
        'SELECT c.*, u.full_name AS student_name, u.reg_number,
                d.name AS dept_name
         FROM complaints c
         JOIN users u ON u.id = c.student_id
         LEFT JOIN departments d ON d.id = c.department_id
         WHERE c.id = ? LIMIT 1'
    );
    $stmt->execute([$view_id]);
    $complaint = $stmt->fetch();

    if ($complaint) {
        $history = $pdo->prepare(
            'SELECT sh.*, u.full_name FROM complaint_status_history sh
             JOIN users u ON u.id = sh.changed_by
             WHERE sh.complaint_id = ? ORDER BY sh.changed_at ASC'
        );
        $history->execute([$view_id]);
        $timeline = $history->fetchAll();

        $resp_stmt = $pdo->prepare(
            'SELECT r.*, u.full_name, u.role FROM responses r
             JOIN users u ON u.id = r.author_id
             WHERE r.complaint_id = ? ORDER BY r.created_at ASC'
        );
        $resp_stmt->execute([$view_id]);
        $responses = $resp_stmt->fetchAll();

        // Handle status update and reply from this page.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $action = $_POST['action'] ?? '';

            if ($action === 'update_status') {
                $new_status = $_POST['new_status'] ?? '';
                $note       = trim($_POST['status_note'] ?? '');
                $valid_statuses = ['Submitted','Under Review','In Progress','Resolved','Closed','Rejected','Escalated'];
                if (in_array($new_status, $valid_statuses, true) && $new_status !== $complaint['status']) {
                    $old = $complaint['status'];
                    $pdo->prepare('UPDATE complaints SET status = ?, updated_at = NOW() WHERE id = ?')
                        ->execute([$new_status, $view_id]);
                    $pdo->prepare(
                        'INSERT INTO complaint_status_history (complaint_id, old_status, new_status, changed_by, note) VALUES (?,?,?,?,?)'
                    )->execute([$view_id, $old, $new_status, $user['id'], $note ?: null]);

                    create_notification($pdo, $complaint['student_id'],
                        "Your complaint {$complaint['tracking_id']} is now {$new_status}.",
                        "/student/complaint-details.php?id={$view_id}"
                    );
                    $stu = $pdo->prepare('SELECT email, full_name FROM users WHERE id = ?');
                    $stu->execute([$complaint['student_id']]);
                    $s = $stu->fetch();
                    if ($s) mail_status_changed($s['email'], $s['full_name'], $complaint['tracking_id'], $new_status);

                    set_flash('flash_success', "Status updated to {$new_status}.");
                    header('Location: ' . APP_URL . '/admin/complaints.php?id=' . $view_id);
                    exit;
                }
            }

            if ($action === 'reply') {
                $message     = trim($_POST['message'] ?? '');
                $is_internal = isset($_POST['is_internal']) ? 1 : 0;
                if ($message !== '') {
                    $pdo->prepare(
                        'INSERT INTO responses (complaint_id, author_id, message, is_internal) VALUES (?,?,?,?)'
                    )->execute([$view_id, $user['id'], $message, $is_internal]);

                    if (!$is_internal) {
                        create_notification($pdo, $complaint['student_id'],
                            "A staff member replied to your complaint {$complaint['tracking_id']}.",
                            "/student/complaint-details.php?id={$view_id}"
                        );
                        $stu = $pdo->prepare('SELECT email, full_name FROM users WHERE id = ?');
                        $stu->execute([$complaint['student_id']]);
                        $s = $stu->fetch();
                        if ($s) mail_staff_replied($s['email'], $s['full_name'], $complaint['tracking_id']);
                    }
                    set_flash('flash_success', 'Response added.');
                    header('Location: ' . APP_URL . '/admin/complaints.php?id=' . $view_id . '#responses');
                    exit;
                }
            }

            if ($action === 'assign_dept') {
                $dept_id = (int)($_POST['department_id'] ?? 0);
                if ($dept_id) {
                    $pdo->prepare('UPDATE complaints SET department_id = ?, updated_at = NOW() WHERE id = ?')
                        ->execute([$dept_id, $view_id]);
                    set_flash('flash_success', 'Department updated.');
                    header('Location: ' . APP_URL . '/admin/complaints.php?id=' . $view_id);
                    exit;
                }
            }
        }

        $departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
        $page_title  = 'Complaint ' . $complaint['tracking_id'];
        $active_nav  = 'complaints';
        include dirname(__DIR__) . '/includes/header.php';
        // Render detail view.
        ?>
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= APP_URL ?>/admin/complaints.php">Complaints</a>
          <span><?= icon('chevron-right') ?></span>
          <span aria-current="page"><?= e($complaint['tracking_id']) ?></span>
        </nav>

        <div class="complaint-header card">
          <div class="complaint-header__main">
            <div class="complaint-header__badges">
              <code class="tracking-id"><?= e($complaint['tracking_id']) ?></code>
              <span class="badge badge--<?= status_badge_class($complaint['status']) ?>"><?= e($complaint['status']) ?></span>
              <span class="badge badge--<?= priority_badge_class($complaint['priority']) ?>"><?= e($complaint['priority']) ?> priority</span>
            </div>
            <h2 class="complaint-header__title"><?= e($complaint['title']) ?></h2>
            <p class="complaint-header__meta">
              Submitted <?= date('d M Y, H:i', strtotime($complaint['created_at'])) ?>
              by <?= $complaint['is_anonymous'] ? '<em>Anonymous Student</em>' : e($complaint['student_name'] . ' (' . $complaint['reg_number'] . ')') ?>
            </p>
          </div>
        </div>

        <div class="complaint-layout">
          <div class="complaint-layout__main">
            <div class="card">
              <dl class="details-list">
                <div class="details-list__row"><dt>Category</dt><dd><?= e($complaint['category']) ?></dd></div>
                <div class="details-list__row"><dt>Department</dt><dd><?= $complaint['dept_name'] ? e($complaint['dept_name']) : 'Unassigned' ?></dd></div>
              </dl>
              <h3 class="card__section-title" style="margin-top:24px">Description</h3>
              <div class="complaint-description"><?= nl2br(e($complaint['description'])) ?></div>
            </div>

            <!-- Admin controls -->
            <div class="card" style="margin-top:16px">
              <h3 class="card__section-title">Admin controls</h3>
              <div class="form-row form-row--half">
                <!-- Assign department -->
                <form method="POST" action="">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="assign_dept">
                  <div class="form-group">
                    <label class="form-label" for="dept_assign">Assign to department</label>
                    <select id="dept_assign" name="department_id" class="form-input form-select">
                      <option value="">Select department</option>
                      <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $complaint['department_id'] == $d['id'] ? 'selected' : '' ?>>
                          <?= e($d['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <button type="submit" class="btn btn--secondary btn--sm">Assign department</button>
                </form>

                <!-- Update status -->
                <form method="POST" action="">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_status">
                  <div class="form-group">
                    <label class="form-label" for="new_status">Update status</label>
                    <select id="new_status" name="new_status" class="form-input form-select">
                      <?php foreach (['Submitted','Under Review','In Progress','Resolved','Closed','Rejected','Escalated'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $complaint['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group">
                    <input type="text" name="status_note" class="form-input" placeholder="Note (optional)">
                  </div>
                  <button type="submit" class="btn btn--primary btn--sm">Update status</button>
                </form>
              </div>
            </div>

            <!-- Add reply -->
            <div class="card" id="responses" style="margin-top:16px">
              <h3 class="card__section-title">Conversation</h3>
              <?php foreach ($responses as $r): ?>
                <div class="response-item response-item--<?= $r['author_id'] == $user['id'] ? 'staff' : 'student' ?> <?= $r['is_internal'] ? 'response-item--internal' : '' ?>">
                  <?php if ($r['is_internal']): ?>
                    <div class="response-item__internal-tag"><?= icon('lock') ?> Internal note</div>
                  <?php endif; ?>
                  <div class="response-item__header">
                    <strong><?= e($r['full_name']) ?></strong>
                    <span class="badge badge--grey badge--xs"><?= ucfirst(e($r['role'])) ?></span>
                    <time><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></time>
                  </div>
                  <div class="response-item__body"><?= nl2br(e($r['message'])) ?></div>
                </div>
              <?php endforeach; ?>
              <form method="POST" action="#responses" style="margin-top:16px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reply">
                <div class="form-group">
                  <label class="form-label" for="message">Add a response</label>
                  <textarea id="message" name="message" class="form-input form-textarea" rows="3" required></textarea>
                </div>
                <div class="reply-form__actions">
                  <label class="form-checkbox">
                    <input type="checkbox" name="is_internal" value="1">
                    <span class="form-checkbox__label"><?= icon('lock') ?> Internal note</span>
                  </label>
                  <button type="submit" class="btn btn--primary btn--sm"><?= icon('send') ?> Send</button>
                </div>
              </form>
            </div>
          </div>

          <div class="complaint-layout__sidebar">
            <div class="card">
              <h3 class="card__section-title">Status timeline</h3>
              <ol class="timeline">
                <?php foreach ($timeline as $event): ?>
                  <li class="timeline__item timeline__item--<?= status_badge_class($event['new_status']) ?>">
                    <div class="timeline__dot"></div>
                    <div class="timeline__content">
                      <span class="badge badge--<?= status_badge_class($event['new_status']) ?> badge--xs"><?= e($event['new_status']) ?></span>
                      <span class="timeline__by">by <?= e($event['full_name']) ?></span>
                      <?php if ($event['note']): ?>
                        <p class="timeline__note"><?= e($event['note']) ?></p>
                      <?php endif; ?>
                      <time class="timeline__time"><?= date('d M Y, H:i', strtotime($event['changed_at'])) ?></time>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ol>
            </div>
          </div>
        </div>
        <?php
        include dirname(__DIR__) . '/includes/footer.php';
        exit;
    }
}

// ------- List view -------
$search    = trim($_GET['search']    ?? '');
$status    = trim($_GET['status']    ?? '');
$category  = trim($_GET['category']  ?? '');
$priority  = trim($_GET['priority']  ?? '');
$dept_filter = (int)($_GET['dept'] ?? 0);
$page_num  = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 15;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(c.title LIKE ? OR c.tracking_id LIKE ? OR u.full_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($status !== '')    { $where[] = 'c.status = ?';        $params[] = $status; }
if ($category !== '')  { $where[] = 'c.category = ?';      $params[] = $category; }
if ($priority !== '')  { $where[] = 'c.priority = ?';      $params[] = $priority; }
if ($dept_filter > 0)  { $where[] = 'c.department_id = ?'; $params[] = $dept_filter; }

$where_sql = 'WHERE ' . implode(' AND ', $where);

$count_stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM complaints c JOIN users u ON u.id = c.student_id {$where_sql}"
);
$count_stmt->execute($params);
$total = (int) $count_stmt->fetchColumn();
$pag   = paginate($total, $per_page, $page_num);

$list_stmt = $pdo->prepare(
    "SELECT c.id, c.tracking_id, c.title, c.category, c.status, c.priority,
            c.is_anonymous, c.created_at,
            u.full_name AS student_name,
            d.name AS dept_name
     FROM complaints c
     JOIN users u ON u.id = c.student_id
     LEFT JOIN departments d ON d.id = c.department_id
     {$where_sql}
     ORDER BY c.created_at DESC LIMIT ? OFFSET ?"
);
$p2 = $params;
$p2[] = $per_page;
$p2[] = $pag['offset'];
$list_stmt->execute($p2);
$complaints = $list_stmt->fetchAll();

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$filter_params = array_filter(compact('search','status','category','priority') + ['dept' => $dept_filter ?: '']);

$page_title = 'Complaints';
$active_nav = 'complaints';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">All complaints</h2>
    <p class="page-subtitle"><?= $total ?> complaint<?= $total !== 1 ? 's' : '' ?> found</p>
  </div>
</div>

<div class="filters-bar card">
  <form method="GET" action="" class="filters-bar__form filters-bar__form--wrap">
    <div class="filters-bar__search">
      <span class="filters-bar__search-icon" aria-hidden="true"><?= icon('search') ?></span>
      <input type="text" name="search" class="form-input filters-bar__input"
             placeholder="Search by title, ID or student name"
             value="<?= e($search) ?>" aria-label="Search">
    </div>
    <select name="status"   class="form-input form-select filters-bar__select" aria-label="Status">
      <option value="">All statuses</option>
      <?php foreach (['Submitted','Under Review','In Progress','Resolved','Closed','Rejected','Escalated'] as $st): ?>
        <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="category" class="form-input form-select filters-bar__select" aria-label="Category">
      <option value="">All categories</option>
      <?php foreach (complaint_categories() as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="dept" class="form-input form-select filters-bar__select" aria-label="Department">
      <option value="">All departments</option>
      <?php foreach ($departments as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $dept_filter === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn--secondary btn--sm"><?= icon('filter') ?> Filter</button>
    <?php if (!empty(array_filter($filter_params))): ?>
      <a href="?" class="btn btn--ghost btn--sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($complaints)): ?>
  <div class="empty-state card"><?= icon('file-text') ?><h4>No complaints found</h4></div>
<?php else: ?>
  <div class="card table-card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Tracking ID</th><th>Student</th><th>Title</th>
            <th>Department</th><th>Priority</th><th>Status</th><th>Date</th><th><span class="sr-only">Action</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($complaints as $c): ?>
          <tr>
            <td><code class="tracking-id"><?= e($c['tracking_id']) ?></code></td>
            <td><?= $c['is_anonymous'] ? '<em>Anonymous</em>' : e($c['student_name']) ?></td>
            <td class="table__title"><?= e($c['title']) ?></td>
            <td><?= $c['dept_name'] ? e($c['dept_name']) : '<em>Unassigned</em>' ?></td>
            <td><span class="badge badge--<?= priority_badge_class($c['priority']) ?>"><?= e($c['priority']) ?></span></td>
            <td><span class="badge badge--<?= status_badge_class($c['status']) ?>"><?= e($c['status']) ?></span></td>
            <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
            <td><a href="?id=<?= (int)$c['id'] ?>" class="btn btn--ghost btn--xs">Manage</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pag['total_pages'] > 1): ?>
    <nav class="pagination" aria-label="Pagination">
      <?php if ($pag['current_page'] > 1): ?>
        <a href="<?= pagination_url($pag['current_page'] - 1, $filter_params) ?>" class="btn btn--ghost btn--sm">
          <?= icon('chevron-left') ?> Previous
        </a>
      <?php endif; ?>
      <span class="pagination__info">Page <?= $pag['current_page'] ?> of <?= $pag['total_pages'] ?></span>
      <?php if ($pag['current_page'] < $pag['total_pages']): ?>
        <a href="<?= pagination_url($pag['current_page'] + 1, $filter_params) ?>" class="btn btn--ghost btn--sm">
          Next <?= icon('chevron-right') ?>
        </a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
