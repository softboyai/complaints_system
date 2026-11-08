<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('student');
$pdo  = get_db();
$user = get_logged_in_user();
$uid  = $user['id'];

$errors = [];
$vals   = ['type' => '', 'target' => '', 'rating' => '', 'comment' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $vals['type']    = trim($_POST['type']    ?? '');
    $vals['target']  = trim($_POST['target']  ?? '');
    $vals['rating']  = (int)($_POST['rating'] ?? 0);
    $vals['comment'] = trim($_POST['comment'] ?? '');

    $valid_types = ['Course', 'Lecturer', 'Service', 'Facility'];

    if (!in_array($vals['type'], $valid_types, true)) $errors['type']   = 'Select a valid feedback type.';
    if ($vals['target'] === '')                        $errors['target'] = 'Enter the name of the course, lecturer or service.';
    if (mb_strlen($vals['target']) > 200)              $errors['target'] = 'Name must be under 200 characters.';
    if ($vals['rating'] < 1 || $vals['rating'] > 5)   $errors['rating'] = 'Select a rating from 1 to 5.';
    if ($vals['comment'] !== '' && mb_strlen($vals['comment']) > 1000) {
        $errors['comment'] = 'Comment must be under 1000 characters.';
    }

    if (empty($errors)) {
        $pdo->prepare(
            'INSERT INTO feedback (student_id, type, target, rating, comment) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $uid,
            $vals['type'],
            $vals['target'],
            $vals['rating'],
            $vals['comment'] ?: null,
        ]);
        set_flash('flash_success', 'Your feedback has been submitted. Thank you.');
        header('Location: ' . APP_URL . '/student/feedback.php');
        exit;
    }
}

// Fetch student's previous feedback.
$prev = $pdo->prepare(
    'SELECT * FROM feedback WHERE student_id = ? ORDER BY created_at DESC LIMIT 20'
);
$prev->execute([$uid]);
$previous = $prev->fetchAll();

$page_title = 'Give feedback';
$active_nav = 'feedback';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Give feedback</h2>
    <p class="page-subtitle">Share your experience on courses, lecturers and university services.</p>
  </div>
</div>

<div class="two-col-layout">
  <!-- Feedback form -->
  <div class="card form-card">
    <h3 class="card__section-title">New feedback</h3>
    <form method="POST" action="" id="feedback-form" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="type">
          Feedback type <span class="form-label__required" aria-hidden="true">*</span>
        </label>
        <select id="type" name="type" class="form-input form-select <?= isset($errors['type']) ? 'form-input--error' : '' ?>"
                required aria-required="true" aria-describedby="type-error">
          <option value="">Select type</option>
          <option value="Course"    <?= $vals['type'] === 'Course'    ? 'selected' : '' ?>>Course</option>
          <option value="Lecturer"  <?= $vals['type'] === 'Lecturer'  ? 'selected' : '' ?>>Lecturer</option>
          <option value="Service"   <?= $vals['type'] === 'Service'   ? 'selected' : '' ?>>Service</option>
          <option value="Facility"  <?= $vals['type'] === 'Facility'  ? 'selected' : '' ?>>Facility</option>
        </select>
        <span class="form-error" id="type-error" role="alert"><?= e($errors['type'] ?? '') ?></span>
      </div>

      <div class="form-group">
        <label class="form-label" for="target">
          Name <span class="form-label__required" aria-hidden="true">*</span>
        </label>
        <input type="text" id="target" name="target" class="form-input <?= isset($errors['target']) ? 'form-input--error' : '' ?>"
               value="<?= e($vals['target']) ?>"
               placeholder="Course name, lecturer name, service, etc."
               maxlength="200"
               required aria-required="true"
               aria-describedby="target-error">
        <span class="form-error" id="target-error" role="alert"><?= e($errors['target'] ?? '') ?></span>
      </div>

      <div class="form-group">
        <label class="form-label">
          Rating <span class="form-label__required" aria-hidden="true">*</span>
        </label>
        <?php if (!empty($errors['rating'])): ?>
          <span class="form-error" role="alert"><?= e($errors['rating']) ?></span>
        <?php endif; ?>
        <div class="star-rating" role="radiogroup" aria-label="Rating" aria-required="true">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <label class="star-rating__label" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
              <input type="radio" name="rating" value="<?= $i ?>"
                     class="sr-only" <?= (int)$vals['rating'] === $i ? 'checked' : '' ?>>
              <?= icon('star') ?>
            </label>
          <?php endfor; ?>
        </div>
        <p class="star-rating__legend" aria-hidden="true">1 = Very poor, 5 = Excellent</p>
      </div>

      <div class="form-group">
        <label class="form-label" for="comment">Comment (optional)</label>
        <textarea id="comment" name="comment" class="form-input form-textarea <?= isset($errors['comment']) ? 'form-input--error' : '' ?>"
                  rows="4"
                  placeholder="Share any additional thoughts"
                  maxlength="1000"
                  aria-describedby="comment-error"><?= e($vals['comment']) ?></textarea>
        <span class="form-error" id="comment-error" role="alert"><?= e($errors['comment'] ?? '') ?></span>
      </div>

      <button type="submit" class="btn btn--primary">
        <?= icon('send') ?> Submit feedback
      </button>
    </form>
  </div>

  <!-- Previous feedback -->
  <div>
    <h3 class="section-title">Your previous feedback</h3>
    <?php if (empty($previous)): ?>
      <div class="empty-state empty-state--small card">
        <?= icon('star') ?>
        <p>No feedback submitted yet.</p>
      </div>
    <?php else: ?>
      <div class="feedback-list">
        <?php foreach ($previous as $f): ?>
          <div class="feedback-card card">
            <div class="feedback-card__header">
              <span class="badge badge--grey badge--xs"><?= e($f['type']) ?></span>
              <div class="star-display" aria-label="<?= $f['rating'] ?> out of 5">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="star <?= $i <= $f['rating'] ? 'star--filled' : '' ?>" aria-hidden="true">&#9733;</span>
                <?php endfor; ?>
              </div>
            </div>
            <strong><?= e($f['target']) ?></strong>
            <?php if ($f['comment']): ?>
              <p class="feedback-card__comment"><?= e($f['comment']) ?></p>
            <?php endif; ?>
            <time class="feedback-card__time"><?= date('d M Y', strtotime($f['created_at'])) ?></time>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
