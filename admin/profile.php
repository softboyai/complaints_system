<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/validator.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('admin');
$pdo = get_db();
$user = get_logged_in_user();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$u = $stmt->fetch();

$errors_profile = [];
$errors_password = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $r_name = validate_name($full_name);
        $r_phone = validate_phone($phone);

        if (!$r_name['valid']) $errors_profile['full_name'] = $r_name['error'];
        if (!$r_phone['valid']) $errors_profile['phone'] = $r_phone['error'];

        if (!$errors_profile) {
            $pdo->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')
                ->execute([$full_name, $phone ?: null, $user['id']]);
            $_SESSION['user_name'] = $full_name;
            set_flash('flash_success', 'Profile updated.');
            header('Location: ' . APP_URL . '/admin/profile.php');
            exit;
        }

        $u['full_name'] = $full_name;
        $u['phone'] = $phone;
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $u['password_hash'])) {
            $errors_password['current_password'] = 'Current password is incorrect.';
        }
        $r_password = validate_password($new_password, $u['full_name'], $u['email']);
        $r_confirm = validate_passwords_match($new_password, $confirm);
        if (!$r_password['valid']) $errors_password['new_password'] = $r_password['error'];
        if (!$r_confirm['valid']) $errors_password['confirm_password'] = $r_confirm['error'];

        if (!$errors_password) {
            $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
            set_flash('flash_success', 'Password changed successfully.');
            header('Location: ' . APP_URL . '/admin/profile.php');
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
        <input type="text" id="full_name" name="full_name" class="form-input <?= isset($errors_profile['full_name']) ? 'form-input--error' : '' ?>" value="<?= e($u['full_name']) ?>" required>
        <span class="form-error" role="alert"><?= e($errors_profile['full_name'] ?? '') ?></span>
      </div>
      <div class="form-group">
        <label class="form-label">Email address</label>
        <input type="email" class="form-input" value="<?= e($u['email']) ?>" disabled>
      </div>
      <div class="form-group">
        <label class="form-label" for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" class="form-input <?= isset($errors_profile['phone']) ? 'form-input--error' : '' ?>" value="<?= e($u['phone'] ?? '') ?>" placeholder="+250788123456">
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
      <?php foreach (['current_password' => 'Current password', 'new_password' => 'New password', 'confirm_password' => 'Confirm new password'] as $field => $label): ?>
        <div class="form-group">
          <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
          <input type="password" id="<?= e($field) ?>" name="<?= e($field) ?>" class="form-input <?= isset($errors_password[$field]) ? 'form-input--error' : '' ?>" autocomplete="<?= $field === 'current_password' ? 'current-password' : 'new-password' ?>" required>
          <span class="form-error" role="alert"><?= e($errors_password[$field] ?? '') ?></span>
        </div>
      <?php endforeach; ?>
      <button type="submit" class="btn btn--primary">Change password</button>
    </form>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>