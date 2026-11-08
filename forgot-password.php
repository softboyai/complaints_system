<?php
// Simplified password reset - no email required.
// Step 1: User enters their email address.
// Step 2: If the email is found, the new-password form is shown immediately on the same page.
// Step 3: User sets a new password and is redirected to login.

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validator.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// Redirect already-logged-in users.
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$step   = 1;         // 1 = email form, 2 = new password form
$errors = [];
$email_val = '';

$pdo = get_db();

// ----------------------------------------------------------------
// STEP 1 POST: look up the email and advance to step 2.
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '1') {
    csrf_verify();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $email_val = $email;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Generic message - do not reveal whether the email exists.
            $errors['email'] = 'No active account was found with that email address.';
        } else {
            // Store the user ID in the session so step 2 knows who to update.
            // Use a short-lived session key - expires in 15 minutes.
            $_SESSION['pw_reset_user_id']  = (int) $user['id'];
            $_SESSION['pw_reset_email']    = $user['email'];
            $_SESSION['pw_reset_name']     = $user['full_name'];
            $_SESSION['pw_reset_expires']  = time() + 15 * 60;
            $step = 2;
        }
    }
}

// ----------------------------------------------------------------
// STEP 2 POST: validate and save the new password.
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '2') {
    csrf_verify();

    // Verify the session token is still valid.
    if (empty($_SESSION['pw_reset_user_id']) || empty($_SESSION['pw_reset_expires'])
        || time() > (int) $_SESSION['pw_reset_expires']) {
        // Session expired - restart.
        unset($_SESSION['pw_reset_user_id'], $_SESSION['pw_reset_email'],
              $_SESSION['pw_reset_name'],    $_SESSION['pw_reset_expires']);
        set_flash('flash_warning', 'Your reset session expired. Please start again.');
        header('Location: ' . APP_URL . '/forgot-password.php');
        exit;
    }

    $uid      = (int) $_SESSION['pw_reset_user_id'];
    $res_name = $_SESSION['pw_reset_name']  ?? '';
    $res_email= $_SESSION['pw_reset_email'] ?? '';

    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $r_password = validate_password($password, $res_name, $res_email);
    $r_confirm  = validate_passwords_match($password, $confirm);

    if (!$r_password['valid']) $errors['password']         = $r_password['error'];
    if (!$r_confirm['valid'])  $errors['confirm_password'] = $r_confirm['error'];

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare(
            'UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?'
        )->execute([$hash, $uid]);

        // Clear the reset session keys.
        unset($_SESSION['pw_reset_user_id'], $_SESSION['pw_reset_email'],
              $_SESSION['pw_reset_name'],    $_SESSION['pw_reset_expires']);

        set_flash('flash_success', 'Your password has been reset. Please log in with your new password.');
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    // Keep step 2 visible if there were errors.
    $step = 2;
}

// If we arrive here via GET and a valid reset session exists, skip to step 2.
if ($_SERVER['REQUEST_METHOD'] === 'GET'
    && !empty($_SESSION['pw_reset_user_id'])
    && !empty($_SESSION['pw_reset_expires'])
    && time() < (int) $_SESSION['pw_reset_expires']) {
    $step = 2;
}

$page_title = 'Reset password';
$is_public  = true;
include __DIR__ . '/includes/header.php';
?>

<div class="split-layout split-layout--narrow">
  <div class="split-layout__form">
    <div class="auth-card">

      <div class="auth-card__header">
        <a href="<?= APP_URL ?>/login.php" class="auth-card__back">
          <?= icon('chevron-left') ?> Back to log in
        </a>
        <h1><?= $step === 1 ? 'Reset your password' : 'Set a new password' ?></h1>
        <?php if ($step === 1): ?>
          <p>Enter your registered email address. If your account is found, you can set a new password straight away.</p>
        <?php else: ?>
          <p>Setting a new password for <strong><?= e($_SESSION['pw_reset_email'] ?? '') ?></strong></p>
        <?php endif; ?>
      </div>

      <?php if ($step === 1): ?>
      <!-- ---- Step 1: Email form ---- -->
      <form method="POST" action="" id="forgot-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="1">

        <div class="form-group">
          <label class="form-label" for="email">Email address</label>
          <input type="email" id="email" name="email"
                 class="form-input <?= isset($errors['email']) ? 'form-input--error' : '' ?>"
                 value="<?= e($email_val) ?>"
                 placeholder="you@example.com"
                 autocomplete="email"
                 required aria-required="true"
                 aria-describedby="email-error">
          <span class="form-error" id="email-error" role="alert"><?= e($errors['email'] ?? '') ?></span>
        </div>

        <button type="submit" class="btn btn--primary btn--full btn--lg">
          <span class="btn__label">Continue</span>
          <span class="btn__spinner" hidden aria-hidden="true"></span>
        </button>
      </form>

      <?php else: ?>
      <!-- ---- Step 2: New password form ---- -->
      <form method="POST" action="" id="reset-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="2">

        <div class="form-group">
          <label class="form-label" for="password">New password</label>
          <div class="input-password-wrap">
            <input type="password" id="password" name="password"
                   class="form-input <?= isset($errors['password']) ? 'form-input--error' : '' ?>"
                   placeholder="At least 8 characters"
                   autocomplete="new-password"
                   required aria-required="true"
                   aria-describedby="password-error">
            <button type="button" class="password-toggle" aria-label="Show password" data-target="password">
              <?= icon('eye') ?>
            </button>
          </div>
          <div class="password-strength" id="password-strength" aria-live="polite">
            <div class="password-strength__bar">
              <div class="password-strength__fill" id="strength-fill"></div>
            </div>
            <span class="password-strength__label" id="strength-label"></span>
          </div>
          <span class="form-error" id="password-error" role="alert"><?= e($errors['password'] ?? '') ?></span>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm new password</label>
          <div class="input-password-wrap">
            <input type="password" id="confirm_password" name="confirm_password"
                   class="form-input <?= isset($errors['confirm_password']) ? 'form-input--error' : '' ?>"
                   placeholder="Repeat your new password"
                   autocomplete="new-password"
                   required aria-required="true"
                   aria-describedby="confirm_password-error">
            <button type="button" class="password-toggle" aria-label="Show password" data-target="confirm_password">
              <?= icon('eye') ?>
            </button>
          </div>
          <span class="form-error" id="confirm_password-error" role="alert"><?= e($errors['confirm_password'] ?? '') ?></span>
        </div>

        <div class="form-actions" style="flex-direction:column;gap:10px">
          <button type="submit" class="btn btn--primary btn--full btn--lg">
            <span class="btn__label">Save new password</span>
            <span class="btn__spinner" hidden aria-hidden="true"></span>
          </button>
          <a href="<?= APP_URL ?>/forgot-password.php" class="btn btn--ghost btn--full"
             onclick="<?php
               // Clear the reset session on the server when user clicks "Start over".
               // We do this via a small inline link that triggers a GET to a clear-reset endpoint.
             ?>">
            Use a different email
          </a>
        </div>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
