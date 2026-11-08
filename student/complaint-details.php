<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

require_role('student');
$pdo  = get_db();
$user = get_logged_in_user();
$uid  = $user['id'];

// Allow lookup by numeric id or by tracking_id.
$id          = (int)($_GET['id']       ?? 0);
$tracking    = trim($_GET['tracking']  ?? '');
$errors      = [];
$reply_error = '';

// Fetch the complaint.
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM complaints WHERE id = ? AND student_id = ? LIMIT 1');
    $stmt->execute([$id, $uid]);
} elseif ($tracking !== '') {
    $stmt = $pdo->prepare('SELECT * FROM complaints WHERE tracking_id = ? AND student_id = ? LIMIT 1');
    $stmt->execute([$tracking, $uid]);
} else {
    header('Location: ' . APP_URL . '/student/my-complaints.php');
    exit;
}
$complaint = $stmt->fetch();
if (!$complaint) {
    http_response_code(404);
    set_flash('flash_error', 'Complaint not found or you do not have permission to view it.');
    header('Location: ' . APP_URL . '/student/my-complaints.php');
    exit;
}
$cid = (int) $complaint['id'];

// Handle: submit a reply.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();

    if ($_POST['action'] === 'reply') {
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            $reply_error = 'Please enter a message before sending.';
        } elseif (mb_strlen($message) > 2000) {
            $reply_error = 'Message must be under 2000 characters.';
        } else {
            $pdo->prepare(
                'INSERT INTO responses (complaint_id, author_id, message, is_internal) VALUES (?, ?, ?, 0)'
            )->execute([$cid, $uid, $message]);

            // Notify assigned staff if any.
            if ($complaint['assigned_to']) {
                $staff = $pdo->prepare('SELECT email, full_name FROM users WHERE id = ?');
                $staff->execute([$complaint['assigned_to']]);
                $s = $staff->fetch();
                if ($s) {
                    create_notification($pdo, $complaint['assigned_to'],
                        "Student replied to complaint {$complaint['tracking_id']}.",
                        "/staff/complaint-details.php?id={$cid}"
                    );
                    mail_student_replied($s['email'], $s['full_name'], $complaint['tracking_id']);
                }
            }
            set_flash('flash_success', 'Your reply has been sent.');
            header('Location: ' . APP_URL . '/student/complaint-details.php?id=' . $cid . '#responses');
            exit;
        }
    }

    if ($_POST['action'] === 'rate') {
        $rating = (int)($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            $errors['rating'] = 'Please select a rating from 1 to 5.';
        } elseif ($complaint['status'] !== 'Resolved') {
            $errors['rating'] = 'You can only rate a resolved complaint.';
        } else {
            $pdo->prepare('UPDATE complaints SET rating = ? WHERE id = ?')
                ->execute([$rating, $cid]);
            set_flash('flash_success', 'Thank you for your feedback.');
            header('Location: ' . APP_URL . '/student/complaint-details.php?id=' . $cid);
            exit;
        }
    }

    // Reload complaint after POST.
    $stmt = $pdo->prepare('SELECT * FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$cid]);
    $complaint = $stmt->fetch();
}

// Fetch status history.
$history = $pdo->prepare(
    'SELECT sh.*, u.full_name, u.role
     FROM complaint_status_history sh
     JOIN users u ON u.id = sh.changed_by
     WHERE sh.complaint_id = ?
     ORDER BY sh.changed_at ASC'
);
$history->execute([$cid]);
$timeline = $history->fetchAll();

// Fetch responses (public only for student).
$resp_stmt = $pdo->prepare(
    'SELECT r.*, u.full_name, u.role
     FROM responses r
     JOIN users u ON u.id = r.author_id
     WHERE r.complaint_id = ? AND r.is_internal = 0
     ORDER BY r.created_at ASC'
);
$resp_stmt->execute([$cid]);
$responses = $resp_stmt->fetchAll();

// Fetch attachments.
$att_stmt = $pdo->prepare('SELECT * FROM attachments WHERE complaint_id = ?');
$att_stmt->execute([$cid]);
$attachments = $att_stmt->fetchAll();

// Fetch department name.
$dept_name = '';
if ($complaint['department_id']) {
    $d = $pdo->prepare('SELECT name FROM departments WHERE id = ?');
    $d->execute([$complaint['department_id']]);
    $dept_name = $d->fetchColumn() ?: '';
}

// Assigned staff name.
$assigned_name = '';
if ($complaint['assigned_to']) {
    $a = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
    $a->execute([$complaint['assigned_to']]);
    $assigned_name = $a->fetchColumn() ?: '';
}

$page_title = 'Complaint ' . $complaint['tracking_id'];
$active_nav = 'complaints';
include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= APP_URL ?>/student/my-complaints.php">My complaints</a>
  <span aria-hidden="true"><?= icon('chevron-right') ?></span>
  <span aria-current="page"><?= e($complaint['tracking_id']) ?></span>
</nav>

<!-- Complaint header -->
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
      <?php if ($complaint['is_anonymous']): ?>
        <span class="badge badge--grey badge--xs" title="Submitted anonymously">Anonymous</span>
      <?php endif; ?>
    </p>
  </div>
  <div class="complaint-header__actions">
    <a href="<?= APP_URL ?>/student/receipt.php?id=<?= $cid ?>"
       class="btn btn--ghost btn--sm" target="_blank" rel="noopener">
      <?= icon('download') ?> Download receipt
    </a>
  </div>
</div>

<div class="complaint-layout">
  <!-- Left column: details + attachments -->
  <div class="complaint-layout__main">
    <div class="card">
      <h3 class="card__section-title">Details</h3>
      <dl class="details-list">
        <div class="details-list__row">
          <dt>Category</dt>
          <dd><?= e($complaint['category']) ?></dd>
        </div>
        <div class="details-list__row">
          <dt>Department</dt>
          <dd><?= $dept_name ? e($dept_name) : 'Not yet assigned' ?></dd>
        </div>
        <div class="details-list__row">
          <dt>Assigned to</dt>
          <dd><?= $assigned_name ? e($assigned_name) : 'Pending' ?></dd>
        </div>
        <div class="details-list__row">
          <dt>Last updated</dt>
          <dd><?= date('d M Y, H:i', strtotime($complaint['updated_at'])) ?></dd>
        </div>
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
            <a href="<?= APP_URL ?>/download.php?id=<?= (int)$att['id'] ?>"
               class="btn btn--ghost btn--xs" download>
              <?= icon('download') ?> Download
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <!-- Rate the resolution -->
    <?php if ($complaint['status'] === 'Resolved' && !$complaint['rating']): ?>
    <div class="card card--highlight" style="margin-top:16px">
      <h3>How was this resolved?</h3>
      <p>Your rating helps us improve the way we handle complaints.</p>
      <?php if (!empty($errors['rating'])): ?>
        <div class="alert alert--error" role="alert"><?= e($errors['rating']) ?></div>
      <?php endif; ?>
      <form method="POST" action="" id="rating-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="rate">
        <div class="star-rating" role="radiogroup" aria-label="Rate the resolution">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <label class="star-rating__label" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
              <input type="radio" name="rating" value="<?= $i ?>" class="sr-only">
              <?= icon('star') ?>
            </label>
          <?php endfor; ?>
        </div>
        <button type="submit" class="btn btn--primary btn--sm" style="margin-top:16px">Submit rating</button>
      </form>
    </div>
    <?php elseif ($complaint['rating']): ?>
    <div class="card" style="margin-top:16px">
      <p>You rated this resolution <strong><?= (int)$complaint['rating'] ?> / 5</strong>. Thank you.</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right column: status timeline -->
  <div class="complaint-layout__sidebar">
    <div class="card">
      <h3 class="card__section-title">Status timeline</h3>
      <ol class="timeline" aria-label="Complaint status history">
        <?php foreach ($timeline as $event): ?>
          <li class="timeline__item timeline__item--<?= status_badge_class($event['new_status']) ?>">
            <div class="timeline__dot" aria-hidden="true"></div>
            <div class="timeline__content">
              <span class="badge badge--<?= status_badge_class($event['new_status']) ?> badge--xs">
                <?= e($event['new_status']) ?>
              </span>
              <?php if ($event['note']): ?>
                <p class="timeline__note"><?= e($event['note']) ?></p>
              <?php endif; ?>
              <time class="timeline__time" datetime="<?= $event['changed_at'] ?>">
                <?= date('d M Y, H:i', strtotime($event['changed_at'])) ?>
              </time>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>
</div>

<!-- Conversation thread -->
<div class="card" id="responses" style="margin-top:16px">
  <h3 class="card__section-title">Conversation</h3>

  <?php if (empty($responses)): ?>
    <div class="empty-state empty-state--small">
      <?= icon('send') ?>
      <p>No messages yet. Use the box below to add context or ask a question.</p>
    </div>
  <?php else: ?>
    <div class="responses-list">
      <?php foreach ($responses as $r): ?>
        <div class="response-item response-item--<?= $r['author_id'] == $uid ? 'student' : 'staff' ?>">
          <div class="response-item__header">
            <strong class="response-item__author">
              <?= $r['author_id'] == $uid ? 'You' : e($r['full_name']) ?>
            </strong>
            <span class="response-item__role badge badge--grey badge--xs">
              <?= $r['author_id'] == $uid ? 'Student' : ucfirst(e($r['role'])) ?>
            </span>
            <time class="response-item__time">
              <?= date('d M Y, H:i', strtotime($r['created_at'])) ?>
            </time>
          </div>
          <div class="response-item__body"><?= nl2br(e($r['message'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Reply form -->
  <?php if (!in_array($complaint['status'], ['Closed', 'Rejected'])): ?>
  <div class="reply-form" style="margin-top:24px">
    <?php if ($reply_error): ?>
      <div class="alert alert--error" role="alert"><?= e($reply_error) ?></div>
    <?php endif; ?>
    <form method="POST" action="#responses" id="reply-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reply">
      <div class="form-group">
        <label class="form-label" for="message">Add a message</label>
        <textarea id="message" name="message" class="form-input form-textarea" rows="4"
                  placeholder="Type your message here"
                  maxlength="2000" required
                  aria-describedby="message-error"></textarea>
        <span class="form-error" id="message-error" role="alert"></span>
      </div>
      <button type="submit" class="btn btn--primary btn--sm">
        <?= icon('send') ?> Send message
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
