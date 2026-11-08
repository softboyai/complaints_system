<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/validator.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

require_role('student');
$pdo  = get_db();
$user = get_logged_in_user();

$errors = [];
$vals   = ['title' => '', 'category' => '', 'description' => '', 'priority' => 'Low', 'is_anonymous' => 0];

// Load departments for optional routing hint (admin assigns, not student).
$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $vals['title']        = trim($_POST['title']       ?? '');
    $vals['category']     = trim($_POST['category']    ?? '');
    $vals['description']  = trim($_POST['description'] ?? '');
    $vals['priority']     = trim($_POST['priority']    ?? 'Low');
    $vals['is_anonymous'] = isset($_POST['is_anonymous']) ? 1 : 0;

    // Validate.
    $r_title = validate_complaint_title($vals['title']);
    $r_cat   = validate_required($vals['category'], 'Category');
    $r_desc  = validate_complaint_description($vals['description']);
    $r_prio  = validate_required($vals['priority'], 'Priority');

    if (!$r_title['valid']) $errors['title']       = $r_title['error'];
    if (!$r_cat['valid'])   $errors['category']    = $r_cat['error'];
    if (!$r_desc['valid'])  $errors['description'] = $r_desc['error'];
    if (!$r_prio['valid'])  $errors['priority']    = $r_prio['error'];

    // Validate category and priority values against allowed enums.
    $valid_cats  = complaint_categories();
    $valid_prios = ['Low', 'Medium', 'High'];
    if (!in_array($vals['category'], $valid_cats, true))   $errors['category'] = 'Select a valid category.';
    if (!in_array($vals['priority'], $valid_prios, true))  $errors['priority'] = 'Select a valid priority.';

    // Validate attachments.
    $uploaded_files = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $file_count = count($_FILES['attachments']['name']);
        if ($file_count > MAX_FILES) {
            $errors['attachments'] = 'You can attach up to ' . MAX_FILES . ' files.';
        } else {
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $file = [
                    'name'     => $_FILES['attachments']['name'][$i],
                    'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                    'error'    => $_FILES['attachments']['error'][$i],
                    'size'     => $_FILES['attachments']['size'][$i],
                    'type'     => $_FILES['attachments']['type'][$i],
                ];
                $r_file = validate_attachment($file);
                if (!$r_file['valid']) {
                    $errors['attachments'] = $file['name'] . ': ' . $r_file['error'];
                    break;
                }
                $uploaded_files[] = $file;
            }
        }
    }

    if (empty($errors)) {
        // Generate tracking ID inside a transaction.
        $pdo->beginTransaction();
        try {
            $tracking_id = generate_tracking_id($pdo);

            $stmt = $pdo->prepare(
                'INSERT INTO complaints
                 (tracking_id, student_id, is_anonymous, category, title, description, priority, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'Submitted\')'
            );
            $stmt->execute([
                $tracking_id,
                $user['id'],
                $vals['is_anonymous'],
                $vals['category'],
                $vals['title'],
                $vals['description'],
                $vals['priority'],
            ]);
            $complaint_id = (int) $pdo->lastInsertId();

            // Initial status history entry.
            $pdo->prepare(
                'INSERT INTO complaint_status_history (complaint_id, old_status, new_status, changed_by, note)
                 VALUES (?, NULL, \'Submitted\', ?, \'Complaint submitted by student.\')'
            )->execute([$complaint_id, $user['id']]);

            // Save attachments.
            foreach ($uploaded_files as $file) {
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mime     = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $stored_name = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest        = UPLOAD_PATH . '/' . $stored_name;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $pdo->prepare(
                        'INSERT INTO attachments (complaint_id, original_name, stored_name, file_type, file_size)
                         VALUES (?, ?, ?, ?, ?)'
                    )->execute([$complaint_id, $file['name'], $stored_name, $mime, $file['size']]);
                }
            }

            $pdo->commit();

            // Notify admin.
            $admin = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
            if ($admin) {
                create_notification($pdo, $admin['id'],
                    "New complaint submitted: {$vals['title']}",
                    "/admin/complaints.php?id={$complaint_id}"
                );
            }

            // Notify student.
            create_notification($pdo, $user['id'],
                "Your complaint {$tracking_id} has been received.",
                "/student/complaint-details.php?id={$complaint_id}"
            );

            // Send confirmation email.
            $stmt2 = $pdo->prepare('SELECT email FROM users WHERE id = ?');
            $stmt2->execute([$user['id']]);
            $student_email = $stmt2->fetchColumn();
            if ($student_email) {
                mail_complaint_received($student_email, $user['full_name'], $tracking_id, $vals['title']);
            }

            set_flash('flash_success', "Your complaint has been submitted. Tracking ID: {$tracking_id}");
            header('Location: ' . APP_URL . '/student/my-complaints.php');
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            log_error('submit-complaint: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

$page_title = 'Submit a complaint';
$active_nav = 'submit';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Submit a complaint</h2>
    <p class="page-subtitle">Describe your concern clearly. Be specific so the right team can help you quickly.</p>
  </div>
</div>

<?php if (!empty($errors['general'])): ?>
  <div class="alert alert--error" role="alert"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="card form-card">
  <form method="POST" action="" enctype="multipart/form-data" id="complaint-form" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label" for="title">
        Title <span class="form-label__required" aria-hidden="true">*</span>
      </label>
      <input type="text" id="title" name="title" class="form-input <?= isset($errors['title']) ? 'form-input--error' : '' ?>"
             value="<?= e($vals['title']) ?>"
             placeholder="For example: Missing marks in Database Systems exam"
             minlength="5" maxlength="100"
             required aria-required="true"
             aria-describedby="title-error title-hint">
      <span class="form-hint" id="title-hint">5 to 100 characters</span>
      <span class="form-error" id="title-error" role="alert"><?= e($errors['title'] ?? '') ?></span>
    </div>

    <div class="form-row form-row--half">
      <div class="form-group">
        <label class="form-label" for="category">
          Category <span class="form-label__required" aria-hidden="true">*</span>
        </label>
        <select id="category" name="category" class="form-input form-select <?= isset($errors['category']) ? 'form-input--error' : '' ?>"
                required aria-required="true" aria-describedby="category-error">
          <option value="">Select a category</option>
          <?php foreach (complaint_categories() as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $vals['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="form-error" id="category-error" role="alert"><?= e($errors['category'] ?? '') ?></span>
      </div>

      <div class="form-group">
        <label class="form-label">
          Priority <span class="form-label__required" aria-hidden="true">*</span>
        </label>
        <div class="radio-group" role="radiogroup" aria-describedby="priority-error">
          <?php foreach (['Low', 'Medium', 'High'] as $p): ?>
            <label class="radio-label">
              <input type="radio" name="priority" value="<?= $p ?>" <?= $vals['priority'] === $p ? 'checked' : '' ?>>
              <span class="radio-badge radio-badge--<?= strtolower($p) ?>"><?= $p ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <span class="form-error" id="priority-error" role="alert"><?= e($errors['priority'] ?? '') ?></span>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="description">
        Description <span class="form-label__required" aria-hidden="true">*</span>
      </label>
      <textarea id="description" name="description"
                class="form-input form-textarea <?= isset($errors['description']) ? 'form-input--error' : '' ?>"
                rows="7"
                placeholder="Describe your complaint in detail. Include dates, names if relevant, and any steps you have already taken."
                minlength="20" maxlength="2000"
                required aria-required="true"
                aria-describedby="description-error description-count"><?= e($vals['description']) ?></textarea>
      <div class="textarea-footer">
        <span class="form-error" id="description-error" role="alert"><?= e($errors['description'] ?? '') ?></span>
        <span class="char-count" id="description-count" aria-live="polite">
          <span id="desc-chars">0</span> / 2000
        </span>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Attachments (optional)</label>
      <div class="upload-zone" id="upload-zone" tabindex="0" role="button" aria-describedby="attachments-error">
        <div class="upload-zone__inner">
          <?= icon('paperclip') ?>
          <p>Drag files here or <span class="upload-zone__link">click to browse</span></p>
          <small>PDF, JPG or PNG. Up to 5 files, 5 MB each.</small>
        </div>
        <input type="file" id="attachments" name="attachments[]"
               accept=".pdf,.jpg,.jpeg,.png"
               multiple
               class="upload-zone__input"
               aria-label="Upload attachments">
      </div>
      <div class="upload-preview" id="upload-preview"></div>
      <?php if (!empty($errors['attachments'])): ?>
        <span class="form-error" id="attachments-error" role="alert"><?= e($errors['attachments']) ?></span>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-checkbox">
        <input type="checkbox" name="is_anonymous" id="is_anonymous" value="1"
               <?= $vals['is_anonymous'] ? 'checked' : '' ?>>
        <span class="form-checkbox__label">
          Submit anonymously
          <span class="form-hint" style="display:inline"> - your name and student ID will not be shown to staff</span>
        </span>
      </label>
    </div>

    <div class="form-actions">
      <a href="<?= APP_URL ?>/student/my-complaints.php" class="btn btn--ghost">Cancel</a>
      <button type="submit" class="btn btn--primary" id="submit-btn">
        <span class="btn__label"><?= icon('send') ?> Submit complaint</span>
        <span class="btn__spinner" hidden aria-hidden="true"></span>
      </button>
    </div>
  </form>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
