<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

require_role('staff');
$pdo  = get_db();
$user = get_logged_in_user();
$dept = (int)($user['department_id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . APP_URL . '/staff/complaints.php');
    exit;
}

// Fetch complaint - staff can only see complaints in their department.
$stmt = $pdo->prepare('SELECT * FROM complaints WHERE id = ? AND department_id = ? LIMIT 1');
$stmt->execute([$id, $dept]);
$complaint = $stmt->fetch();
if (!$complaint) {
    set_flash('flash_error', 'Complaint not found or not assigned to your department.');
    header('Location: ' . APP_URL . '/staff/complaints.php');
    exit;
}
$cid = (int) $complaint['id'];

$errors = [];

// Handle form submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ---- Add a response ----
    if ($action === 'reply') {
        $message     = trim($_POST['message']     ?? '');
        $is_internal = isset($_POST['is_internal']) ? 1 : 0;

        if ($message === '') {
            $errors['reply'] = 'Message is required.';
        } elseif (mb_strlen($message) > 2000) {
            $errors['reply'] = 'Message must be under 2000 characters.';
        } else {
            $pdo->prepare(
                'INSERT INTO responses (complaint_id, author_id, message, is_internal) VALUES (?, ?, ?, ?)'
            )->execute([$cid, $user['id'], $message, $is_internal]);

            if (!$is_internal) {
                // Notify student.
                create_notification($pdo, $complaint['student_id'],
                    "A staff member replied to your complaint {$complaint['tracking_id']}.",
                    "/student/complaint-details.php?id={$cid}"
                );
                // Email student.
                $stu = $pdo->prepare('SELECT email, full_name FROM users WHERE id = ?');
                $stu->execute([$complaint['student_id']]);
                $s = $stu->fetch();
                if ($s) {
                    mail_staff_replied($s['email'], $s['full_name'], $complaint['tracking_id']);
                }
            }

            set_flash('flash_success', 'Response added.');
            header('Location: ' . APP_URL . '/staff/complaint-details.php?id=' . $cid . '#responses');
            exit;
        }
    }

    // ---- Update status ----
    if ($action === 'update_status') {
        $new_status = trim($_POST['new_status'] ?? '');
        $note       = trim($_POST['status_note'] ?? '');
        $allowed    = ['Submitted','Under Review','In Progress','Resolved','Rejected','Escalated'];

        if (!in_array($new_status, $allowed, true)) {
            $errors['status'] = 'Select a valid status.';
        } elseif ($new_status === $complaint['status']) {
            $errors['status'] = 'The status is already ' . $complaint['status'] . '.';
        } else {
            $old_status = $complaint['status'];

            $pdo->prepare('UPDATE complaints SET status = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$new_status, $cid]);

            $pdo->prepare(
                'INSERT INTO complaint_status_history (complaint_id, old_status, new_status, changed_by, note)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$cid, $old_status, $new_status, $user['id'], $note ?: null]);

            // Notify student.
            create_notification($pdo, $complaint['student_id'],
                "Your complaint {$complaint['tracking_id']} is now {$new_status}.",
                "/student/complaint-details.php?id={$cid}"
            );

            $stu = $pdo->prepare('SELECT email, full_name FROM users WHERE id = ?');
            $stu->execute([$complaint['student_id']]);
            $s = $stu->fetch();
            if ($s) {
                mail_status_changed($s['email'], $s['full_name'], $complaint['tracking_id'], $new_status);
            }

            set_flash('flash_success', "Status updated to {$new_status}.");
            header('Location: ' . APP_URL . '/staff/complaint-details.php?id=' . $cid);
            exit;
        }
    }

    // Reload after failed POST.
    $stmt = $pdo->prepare('SELECT * FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$cid]);
    $complaint = $stmt->fetch();
}

// Fetch related data.
$history = $pdo->prepare(
    'SELECT sh.*, u.full_name FROM complaint_status_history sh
     JOIN users u ON u.id = sh.changed_by
     WHERE sh.complaint_id = ? ORDER BY sh.changed_at ASC'
);
$history->execute([$cid]);
$timeline = $history->fetchAll();

$resp_stmt = $pdo->prepare(
    'SELECT r.*, u.full_name, u.role FROM responses r
     JOIN users u ON u.id = r.author_id
     WHERE r.complaint_id = ? ORDER BY r.created_at ASC'
);
$resp_stmt->execute([$cid]);
$responses = $resp_stmt->fetchAll();

$att_stmt = $pdo->prepare('SELECT * FROM attachments WHERE complaint_id = ?');
$att_stmt->execute([$cid]);
$attachments = $att_stmt->fetchAll();

// Student name (anonymised if needed).
$student_display = 'Anonymous Student';
if (!$complaint['is_anonymous']) {
    $stu = $pdo->prepare('SELECT full_name, reg_number FROM users WHERE id = ?');
    $stu->execute([$complaint['student_id']]);
    $s = $stu->fetch();
    $student_display = $s ? $s['full_name'] . ' (' . $s['reg_number'] . ')' : 'Unknown';
}

$dept_name = '';
if ($complaint['department_id']) {
    $d = $pdo->prepare('SELECT name FROM departments WHERE id = ?');
    $d->execute([$complaint['department_id']]);
    $dept_name = $d->fetchColumn() ?: '';
}

$page_title = 'Complaint ' . $complaint['tracking_id'];
$active_nav = 'complaints';
include dirname(__DIR__) . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= APP_URL ?>/staff/complaints.php">Complaints</a>
  <span aria-hidden="true"><?= icon('chevron-right') ?></span>
  <span aria-current="page"><?= e($complaint['tracking_id']) ?></span>
</nav>

<!-- Header card -->
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
      by <?= e($student_display) ?>
    </p>
  </div>
</div>

<div class="complaint-layout">
  <!-- Left column: details + status update -->
  <div class="complaint-layout__main">
    <div class="card">
      <h3 class="card__section-title">Details</h3>
      <dl class="details-list">
        <div class="details-list__row"><dt>Category</dt><dd><?= e($complaint['category']) ?></dd></div>
        <div class="details-list__row"><dt>Department</dt><dd><?= e($dept_name) ?></dd></div>
        <div class="details-list__row"><dt>Student</dt><dd><?= e($student_display) ?></dd></div>
        <div class="details-list__row"><dt>Last updated</dt><dd><?= date('d M Y, H:i', strtotime($complaint['updated_at'])) ?></dd></div>
      </dl>

      <h3 class="card__section-title" style="margin-top:24px">Description</h3>
      <div class="complaint-description"><?= nl2br(e($complaint['description'])) ?></div>

      <?php if (!empty($attachments)): ?>
      <h3 class="card__section-title" style="margin-top:24px">Attachments</h3>
      <ul class="attachment-list">
        <?php foreach ($attachments as $att): ?>
          <li class="attachment-item">
            <span class="attachment-item__icon"><?= icon('paperclip') ?></span>
            <span class="attachment-item__name"><?= e($att['original_name']) ?></span>
            <span class="attachment-item__size"><?= format_bytes($att['file_size']) ?></span>
            <a href="<?= APP_URL ?>/download.php?id=<?= (int)$att['id'] ?>" class="btn btn--ghost btn--xs" download>
              <?= icon('download') ?> Download
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <!-- Update status -->
    <?php if (!in_array($complaint['status'], ['Closed'])): ?>
    <div class="card" style="margin-top:16px">
      <h3 class="card__section-title">Update status</h3>
      <?php if (!empty($errors['status'])): ?>
        <div class="alert alert--error" role="alert"><?= e($errors['status']) ?></div>
      <?php endif; ?>
      <form method="POST" action="" id="status-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_status">
        <div class="form-row form-row--half">
          <div class="form-group">
            <label class="form-label" for="new_status">New status</label>
            <select id="new_status" name="new_status" class="form-input form-select" required>
              <?php foreach (['Submitted','Under Review','In Progress','Resolved','Rejected','Escalated'] as $st): ?>
                <option value="<?= e($st) ?>" <?= $complaint['status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="status_note">Note (optional)</label>
          <textarea id="status_note" name="status_note" class="form-input form-textarea" rows="2"
                    placeholder="Add context about this status change"></textarea>
        </div>
        <button type="submit" class="btn btn--primary btn--sm">Update status</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right column: timeline -->
  <div class="complaint-layout__sidebar">
    <div class="card">
      <h3 class="card__section-title">Status timeline</h3>
      <ol class="timeline">
        <?php foreach ($timeline as $event): ?>
          <li class="timeline__item timeline__item--<?= status_badge_class($event['new_status']) ?>">
            <div class="timeline__dot" aria-hidden="true"></div>
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

<!-- Conversation -->
<div class="card" id="responses" style="margin-top:16px">
  <h3 class="card__section-title">Conversation</h3>

  <?php if (empty($responses)): ?>
    <div class="empty-state empty-state--small">
      <?= icon('send') ?>
      <p>No messages yet.</p>
    </div>
  <?php else: ?>
    <div class="responses-list">
      <?php foreach ($responses as $r): ?>
        <div class="response-item response-item--<?= $r['author_id'] == $user['id'] ? 'staff' : 'student' ?> <?= $r['is_internal'] ? 'response-item--internal' : '' ?>">
          <?php if ($r['is_internal']): ?>
            <div class="response-item__internal-tag"><?= icon('lock') ?> Internal note</div>
          <?php endif; ?>
          <div class="response-item__header">
            <strong class="response-item__author"><?= $r['author_id'] == $user['id'] ? 'You' : e($r['full_name']) ?></strong>
            <span class="badge badge--grey badge--xs"><?= ucfirst(e($r['role'])) ?></span>
            <time class="response-item__time"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></time>
          </div>
          <div class="response-item__body"><?= nl2br(e($r['message'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Add reply form -->
  <?php if (!in_array($complaint['status'], ['Closed','Rejected'])): ?>
  <div class="reply-form" style="margin-top:24px">
    <?php if (!empty($errors['reply'])): ?>
      <div class="alert alert--error" role="alert"><?= e($errors['reply']) ?></div>
    <?php endif; ?>
    <form method="POST" action="#responses" id="reply-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reply">
      <div class="form-group">
        <label class="form-label" for="message">Add a response</label>
        <textarea id="message" name="message" class="form-input form-textarea" rows="4"
                  placeholder="Write your reply here" maxlength="2000" required></textarea>
      </div>
      <div class="reply-form__actions">
        <label class="form-checkbox">
          <input type="checkbox" name="is_internal" value="1">
          <span class="form-checkbox__label"><?= icon('lock') ?> Internal note (not visible to student)</span>
        </label>
        <button type="submit" class="btn btn--primary btn--sm">
          <?= icon('send') ?> Send
        </button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
