<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// Redirect already-logged-in users.
start_secure_session();
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    $redirect = match($role) {
        'admin'  => APP_URL . '/admin/dashboard.php',
        'staff'  => APP_URL . '/staff/dashboard.php',
        default  => APP_URL . '/student/dashboard.php',
    };
    header('Location: ' . $redirect);
    exit;
}

$errors   = [];
$email_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';
    $email_val = $email;

    // Basic presence check.
    if ($email === '' || $password === '') {
        $errors['general'] = 'Enter your email address and password.';
    } else {
        $pdo  = get_db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Check if account is locked.
        $is_locked = false;
        if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $is_locked = true;
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            $errors['general'] = "Your account is locked. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . ".";
        }

        if (!$is_locked) {
            if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
                // Successful login.
                $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')
                    ->execute([$user['id']]);

                login_user($user);

                // Redirect.
                $dest = $_SESSION['redirect_after_login'] ?? '';
                unset($_SESSION['redirect_after_login']);

                if (!$dest) {
                    $dest = match($user['role']) {
                        'admin'  => APP_URL . '/admin/dashboard.php',
                        'staff'  => APP_URL . '/staff/dashboard.php',
                        default  => APP_URL . '/student/dashboard.php',
                    };
                }
                header('Location: ' . $dest);
                exit;
            } else {
                // Failed attempt. Generic message.
                $errors['general'] = 'Invalid email or password.';

                if ($user) {
                    $attempts = (int) $user['failed_attempts'] + 1;
                    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
                        $locked_until = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINS * 60);
                        $pdo->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?')
                            ->execute([$attempts, $locked_until, $user['id']]);
                        $errors['general'] = 'Too many failed attempts. Your account is locked for ' . LOGIN_LOCKOUT_MINS . ' minutes.';
                    } else {
                        $pdo->prepare('UPDATE users SET failed_attempts = ? WHERE id = ?')
                            ->execute([$attempts, $user['id']]);
                        $remaining = LOGIN_MAX_ATTEMPTS - $attempts;
                        if ($remaining <= 2) {
                            $errors['general'] .= " ({$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before lockout)";
                        }
                    }
                }
            }
        }
    }
}

$page_title = 'Log in';
$is_public  = true;
include __DIR__ . '/includes/header.php';
?>
<div class="split-layout">
  <div class="split-layout__form">
    <div class="auth-card">
      <div class="auth-card__header">
        <a href="<?= APP_URL ?>/" class="auth-card__back"><?= icon('chevron-left') ?> Back to home</a>
        <h1>Log in to your account</h1>
        <p>Don't have an account? <a href="<?= APP_URL ?>/register.php">Register here</a></p>
      </div>

      <?php if (!empty($errors['general'])): ?>
        <div class="alert alert--error" role="alert"><?= e($errors['general']) ?></div>
      <?php endif; ?>

      <form method="POST" action="" id="login-form" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label" for="email">Email address</label>
          <input type="email" id="email" name="email" class="form-input"
                 value="<?= e($email_val) ?>"
                 placeholder="you@example.com"
                 autocomplete="email"
                 required
                 aria-required="true">
          <span class="form-error" id="email-error" role="alert"></span>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-password-wrap">
            <input type="password" id="password" name="password" class="form-input"
                   placeholder="Your password"
                   autocomplete="current-password"
                   required
                   aria-required="true">
            <button type="button" class="password-toggle" aria-label="Show password" data-target="password">
              <?= icon('eye') ?>
            </button>
          </div>
          <span class="form-error" id="password-error" role="alert"></span>
        </div>

        <div class="form-row form-row--between">
          <label class="form-checkbox">
            <input type="checkbox" name="remember_me"> Remember me
          </label>
          <a href="<?= APP_URL ?>/forgot-password.php" class="form-link">Forgot password?</a>
          <!-- No email needed - enter your email and reset immediately -->
        </div>

        <button type="submit" class="btn btn--primary btn--full btn--lg" id="login-submit">
          <span class="btn__label">Log in</span>
          <span class="btn__spinner" hidden aria-hidden="true"></span>
        </button>
      </form>
    </div>
  </div>

  <div class="split-layout__hero" aria-hidden="true">
    <div class="split-layout__hero-content">
      <h2>Your voice matters at MKU</h2>
      <p>Submit complaints, track their progress and help us improve the university experience for everyone.</p>
      <ul class="split-layout__features">
        <li><?= icon('check-circle') ?> Track your complaint in real time</li>
        <li><?= icon('check-circle') ?> Get responses from the right department</li>
        <li><?= icon('check-circle') ?> Download a receipt for every complaint</li>
      </ul>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
