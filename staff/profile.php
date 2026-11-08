<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/validator.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('staff');
$pdo  = get_db();
$user = get_logged_in_user();
$uid  = $user['id'];

$stmt = $pdo->prepare('SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ? LIMIT 1');
$stmt->execute([$uid]);
$u = $stmt->fetch();

$errors_profile  = [];
$errors_password = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone     = trim($_POST['phone']     ?? '');

        $r_name  = validate_name($full_name);
        $r_phone = validate_phone($phone);

        if (!$r_name['valid'])  $errors_profile['full_name'] = $r_name['error'];
        if (!$r_phone['valid']) $errors_profile['phone']     = $r_phone['error'];

        if (empty($errors_profile)) {
            $pdo->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')
                ->execute([$full_name, $phone ?: null, $uid]);
            $_SESSION['user_name'] = $full_name;
            set_flash('flash_success', 'Profile updated.');
            header('Location: ' . APP_URL . '/staff/profile.php');
            exit;
        }
        $u['full_name'] = $full_name;
        $u['phone']     = $phone;
    }

    if ($action === 'password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password']      ?? '';
        $confirm  = $_POST['confirm_password']  ?? '';

        if (!password_verify($current, $u['password_hash'])) {
            $errors_password['current_password'] = 'Current password is incorrect.';
        }
        $r_new     = validate_password($new_pass, $u['full_name'], $u['email']);
        $r_confirm = validate_passwords_match($new_pass, $confirm);
        if (!$r_new['valid'])     $errors_password['new_password']     = $r_new['error'];
        if (!$r_confirm['valid']) $errors_password['confirm_password'] = $r_confirm['error'];

        if (empty($errors_password)) {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $uid]);
            set_flash('flash_success', 'Password changed.');
            header('Location: ' . APP_URL . '/staff/profile.php');
            exit;
        }
    }
}

$page_title = 'My profile';
$active_nav = 'profile';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header"><h2 class="page-title">My profile</h2></div>

<div class="two-col-layout">
  <div class="card form-card">
    <h3 class="card__section-title">Account details</h3>
    <form method="POST" action="" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <div class="form-group">
        <label class="form-label" for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" class="form-input <?= isset($errors_profile['full_name']) ? 'form-input--error' : '' ?>"
               value="<?= e($u['full_name']) ?>" required>
        <span class="form-error" role="alert"><?= e($errors_profile['full_name'] ?? '') ?></span>
      </div>
      <div class="form-group">
        <label class="form-label">Email address</label>
        <input type="email" class="form-input" value="<?= e($u['email']) ?>" disabled>
      </div>
      <div class="form-group">
        <label class="form-label">Department</label>
        <input type="text" class="form-input" value="<?= e($u['dept_name'] ?? 'Not assigned') ?>" disabled>
      </div>
      <div class="form-group">
        <label class="form-label" for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" class="form-input <?= isset($errors_profile['phone']) ? 'form-input--error' : '' ?>"
               value="<?= e($u['phone'] ?? '') ?>" placeholder="+250788123456">
        <span class="form-error" role="alert"><?= e($errors_profile['phone'] ?? '') ?></span>
      </div>
      <button type="submit" class="btn btn--primary">Save changes</button>
    </form>
  </div>

  <div class="card form-card">
    <h3 class="card__section-title">Change password</h3>
    <form method="POST" action="" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="form-group">
        <label class="form-label" for="current_password">Current password</label>
        <div class="input-password-wrap">
          <input type="password" id="current_password" name="current_password"
                 class="form-input <?= isset($errors_password['current_password']) ? 'form-input--error' : '' ?>"
                 placeholder="Your current password" autocomplete="current-password" required>
          <button type="button" class="password-toggle" aria-label="Show" data-target="current_password"><?= icon('eye') ?></button>
        </div>
        <span class="form-error" role="alert"><?= e($errors_password['current_password'] ?? '') ?></span>
      </div>
      <div class="form-group">
        <label class="form-label" for="new_password">New password</label>
        <div class="input-password-wrap">
          <input type="password" id="new_password" name="new_password"
                 class="form-input <?= isset($errors_password['new_password']) ? 'form-input--error' : '' ?>"
                 placeholder="At least 8 characters" autocomplete="new-password" required>
          <button type="button" class="password-toggle" aria-label="Show" data-target="new_password"><?= icon('eye') ?></button>
        </div>
        <div class="password-strength" aria-live="polite">
          <div class="password-strength__bar"><div class="password-strength__fill" id="strength-fill"></div></div>
          <span class="password-strength__label" id="strength-label"></span>
        </div>
        <span class="form-error" role="alert"><?= e($errors_password['new_password'] ?? '') ?></span>
      </div>
      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm password</label>
        <div class="input-password-wrap">
          <input type="password" id="confirm_password" name="confirm_password"
                 class="form-input <?= isset($errors_password['confirm_password']) ? 'form-input--error' : '' ?>"
                 placeholder="Repeat new password" autocomplete="new-password" required>
          <button type="button" class="password-toggle" aria-label="Show" data-target="confirm_password"><?= icon('eye') ?></button>
        </div>
        <span class="form-error" role="alert"><?= e($errors_password['confirm_password'] ?? '') ?></span>
      </div>
      <button type="submit" class="btn btn--primary"><?= icon('lock') ?> Change password</button>
    </form>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
