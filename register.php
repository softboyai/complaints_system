<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validator.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

start_secure_session();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/student/dashboard.php');
    exit;
}

$errors = [];
$vals   = ['full_name' => '', 'reg_number' => '', 'email' => '', 'phone' => '', 'faculty' => ''];

$faculties = [
    'Business and ICT', 'Law', 'Engineering', 'Management',
    'Computer Science', 'Health Sciences', 'Education', 'Social Sciences', 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pdo = get_db();

    $vals['full_name']   = trim($_POST['full_name']   ?? '');
    $vals['reg_number']  = trim($_POST['reg_number']  ?? '');
    $vals['email']       = strtolower(trim($_POST['email'] ?? ''));
    $vals['phone']       = trim($_POST['phone']       ?? '');
    $vals['faculty']     = trim($_POST['faculty']     ?? '');
    $password            = $_POST['password']         ?? '';
    $confirm             = $_POST['confirm_password'] ?? '';

    // Validate all fields.
    $r_name     = validate_name($vals['full_name']);
    $r_reg      = validate_reg_number($vals['reg_number'], $pdo);
    $r_email    = validate_email($vals['email'], $pdo);
    $r_phone    = validate_phone($vals['phone']);
    $r_faculty  = validate_required($vals['faculty'], 'Faculty');
    $r_password = validate_password($password, $vals['full_name'], $vals['email']);
    $r_confirm  = validate_passwords_match($password, $confirm);

    if (!$r_name['valid'])     $errors['full_name']        = $r_name['error'];
    if (!$r_reg['valid'])      $errors['reg_number']       = $r_reg['error'];
    if (!$r_email['valid'])    $errors['email']            = $r_email['error'];
    if (!$r_phone['valid'])    $errors['phone']            = $r_phone['error'];
    if (!$r_faculty['valid'])  $errors['faculty']          = $r_faculty['error'];
    if (!$r_password['valid']) $errors['password']         = $r_password['error'];
    if (!$r_confirm['valid'])  $errors['confirm_password'] = $r_confirm['error'];

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare(
            'INSERT INTO users (reg_number, full_name, email, phone, faculty, role, password_hash)
             VALUES (?, ?, ?, ?, ?, \'student\', ?)'
        );
        $stmt->execute([
            $vals['reg_number'],
            $vals['full_name'],
            $vals['email'],
            $vals['phone'] ?: null,
            $vals['faculty'],
            $hash,
        ]);

          login_user([
            'id' => (int) $pdo->lastInsertId(),
            'full_name' => $vals['full_name'],
            'email' => $vals['email'],
            'role' => 'student',
            'department_id' => null,
          ]);
          set_flash('flash_success', 'Your account has been created. Welcome to MKU Complaints.');
          header('Location: ' . APP_URL . '/student/profile.php');
        exit;
    }
}

$page_title = 'Create account';
$is_public  = true;
include __DIR__ . '/includes/header.php';
?>
<div class="split-layout">
  <div class="split-layout__form">
    <div class="auth-card">
      <div class="auth-card__header">
        <a href="<?= APP_URL ?>/" class="auth-card__back"><?= icon('chevron-left') ?> Back to home</a>
        <h1>Create your account</h1>
        <p>Already registered? <a href="<?= APP_URL ?>/login.php">Log in</a></p>
      </div>

      <form method="POST" action="" id="register-form" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label" for="full_name">Full name</label>
          <input type="text" id="full_name" name="full_name" class="form-input <?= isset($errors['full_name']) ? 'form-input--error' : '' ?>"
                 value="<?= e($vals['full_name']) ?>"
                 placeholder="For example: Irakoze Ntwari"
                 autocomplete="name"
                 required aria-required="true"
                 aria-describedby="full_name-error">
          <span class="form-error" id="full_name-error" role="alert"><?= e($errors['full_name'] ?? '') ?></span>
        </div>

        <div class="form-group">
          <label class="form-label" for="reg_number">Registration number</label>
          <input type="text" id="reg_number" name="reg_number" class="form-input <?= isset($errors['reg_number']) ? 'form-input--error' : '' ?>"
                 value="<?= e($vals['reg_number']) ?>"
                 placeholder="For example: BBICTR/2024/45081"
                 autocomplete="off"
                 required aria-required="true"
                 aria-describedby="reg_number-error">
          <span class="form-error" id="reg_number-error" role="alert"><?= e($errors['reg_number'] ?? '') ?></span>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email address</label>
          <input type="email" id="email" name="email" class="form-input <?= isset($errors['email']) ? 'form-input--error' : '' ?>"
                 value="<?= e($vals['email']) ?>"
                 placeholder="you@example.com"
                 autocomplete="email"
                 required aria-required="true"
                 aria-describedby="email-error">
          <span class="form-error" id="email-error" role="alert"><?= e($errors['email'] ?? '') ?></span>
        </div>

        <div class="form-row form-row--half">
          <div class="form-group">
            <label class="form-label" for="phone">Phone number</label>
            <input type="tel" id="phone" name="phone" class="form-input <?= isset($errors['phone']) ? 'form-input--error' : '' ?>"
                   value="<?= e($vals['phone']) ?>"
                   placeholder="+250788123456"
                   autocomplete="tel"
                   aria-describedby="phone-error">
            <span class="form-error" id="phone-error" role="alert"><?= e($errors['phone'] ?? '') ?></span>
          </div>

          <div class="form-group">
            <label class="form-label" for="faculty">Faculty</label>
            <select id="faculty" name="faculty" class="form-input form-select <?= isset($errors['faculty']) ? 'form-input--error' : '' ?>"
                    required aria-required="true" aria-describedby="faculty-error">
              <option value="">Select faculty</option>
              <?php foreach ($faculties as $f): ?>
                <option value="<?= e($f) ?>" <?= $vals['faculty'] === $f ? 'selected' : '' ?>><?= e($f) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-error" id="faculty-error" role="alert"><?= e($errors['faculty'] ?? '') ?></span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-password-wrap">
            <input type="password" id="password" name="password" class="form-input <?= isset($errors['password']) ? 'form-input--error' : '' ?>"
                   placeholder="At least 8 characters"
                   autocomplete="new-password"
                   required aria-required="true"
                   aria-describedby="password-error">
            <button type="button" class="password-toggle" aria-label="Show password" data-target="password">
              <?= icon('eye') ?>
            </button>
          </div>
          <div class="password-strength" id="password-strength" aria-live="polite">
            <div class="password-strength__bar"><div class="password-strength__fill" id="strength-fill"></div></div>
            <span class="password-strength__label" id="strength-label"></span>
          </div>
          <span class="form-error" id="password-error" role="alert"><?= e($errors['password'] ?? '') ?></span>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm password</label>
          <div class="input-password-wrap">
            <input type="password" id="confirm_password" name="confirm_password" class="form-input <?= isset($errors['confirm_password']) ? 'form-input--error' : '' ?>"
                   placeholder="Repeat your password"
                   autocomplete="new-password"
                   required aria-required="true"
                   aria-describedby="confirm_password-error">
            <button type="button" class="password-toggle" aria-label="Show password" data-target="confirm_password">
              <?= icon('eye') ?>
            </button>
          </div>
          <span class="form-error" id="confirm_password-error" role="alert"><?= e($errors['confirm_password'] ?? '') ?></span>
        </div>

        <button type="submit" class="btn btn--primary btn--full btn--lg" id="register-submit">
          <span class="btn__label">Create account</span>
          <span class="btn__spinner" hidden aria-hidden="true"></span>
        </button>
      </form>
    </div>
  </div>

  <div class="split-layout__hero" aria-hidden="true">
    <div class="split-layout__hero-content">
      <h2>Submit a complaint in minutes</h2>
      <p>Your registration number gets you access to the full complaints and feedback system at Mount Kigali University.</p>
      <ul class="split-layout__features">
        <li><?= icon('check-circle') ?> Secure and confidential</li>
        <li><?= icon('check-circle') ?> Submit anonymously if you prefer</li>
        <li><?= icon('check-circle') ?> Get updates by email and in-app</li>
      </ul>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
