<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/validator.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

require_role('admin');
$pdo  = get_db();
$user = get_logged_in_user();

$errors  = [];
$success = '';

// ---- Add or edit a user ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $full_name  = trim($_POST['full_name']  ?? '');
        $email      = strtolower(trim($_POST['email'] ?? ''));
        $role       = trim($_POST['role']       ?? '');
        $dept_id    = (int)($_POST['department_id'] ?? 0);
        $phone      = trim($_POST['phone']      ?? '');
        $password   = $_POST['password']        ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';
        $reg_number = trim($_POST['reg_number'] ?? '');

        $r_name  = validate_name($full_name);
        $r_email = validate_email($email, $pdo);
        $r_pass  = validate_password($password, $full_name, $email);
        $r_conf  = validate_passwords_match($password, $confirm);

        if (!$r_name['valid'])  $errors['add_full_name'] = $r_name['error'];
        if (!$r_email['valid']) $errors['add_email']     = $r_email['error'];
        if (!$r_pass['valid'])  $errors['add_password']  = $r_pass['error'];
        if (!$r_conf['valid'])  $errors['add_confirm']   = $r_conf['error'];
        if (!in_array($role, ['student','staff','admin'], true)) $errors['add_role'] = 'Select a valid role.';

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare(
                'INSERT INTO users (reg_number, full_name, email, phone, role, department_id, password_hash)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $reg_number ?: null,
                $full_name,
                $email,
                $phone ?: null,
                $role,
                ($role !== 'student' && $dept_id) ? $dept_id : null,
                $hash,
            ]);
            set_flash('flash_success', 'User created successfully.');
            header('Location: ' . APP_URL . '/admin/users.php');
            exit;
        }
    }

    if ($action === 'toggle_active') {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 0);
        if ($uid && $uid !== $user['id']) {
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active, $uid]);
        }
        set_flash('flash_success', 'User status updated.');
        header('Location: ' . APP_URL . '/admin/users.php');
        exit;
    }

    if ($action === 'reset_password') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $stmt = $pdo->prepare('SELECT email, full_name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$uid]);
            $u = $stmt->fetch();
            if ($u) {
                $raw_token  = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $raw_token);
                $expires_at = date('Y-m-d H:i:s', time() + RESET_TOKEN_MINS * 60);
                $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')->execute([$uid]);
                $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,?)')->execute([$uid, $token_hash, $expires_at]);
                $reset_url = APP_URL . '/reset-password.php?token=' . urlencode($raw_token);
                mail_password_reset($u['email'], $u['full_name'], $reset_url);
                set_flash('flash_success', 'Password reset link sent to ' . $u['email'] . '.');
            }
        }
        header('Location: ' . APP_URL . '/admin/users.php');
        exit;
    }
}

// Filter.
$role_filter = trim($_GET['role'] ?? '');
$search      = trim($_GET['search'] ?? '');
$page_num    = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 20;

$where  = ['1=1'];
$params = [];
if ($role_filter !== '') { $where[] = 'u.role = ?'; $params[] = $role_filter; }
if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.reg_number LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$count = $pdo->prepare("SELECT COUNT(*) FROM users u {$where_sql}");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pag   = paginate($total, $per_page, $page_num);

$list = $pdo->prepare(
    "SELECT u.*, d.name AS dept_name FROM users u
     LEFT JOIN departments d ON d.id = u.department_id
     {$where_sql} ORDER BY u.created_at DESC LIMIT ? OFFSET ?"
);
$p2 = $params;
$p2[] = $per_page;
$p2[] = $pag['offset'];
$list->execute($p2);
$users = $list->fetchAll();

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

$page_title = 'Users';
$active_nav = 'users';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h2 class="page-title">Users</h2>
    <p class="page-subtitle"><?= $total ?> user<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <button class="btn btn--primary" onclick="document.getElementById('add-user-modal').removeAttribute('hidden')" aria-controls="add-user-modal">
    <?= icon('plus-circle') ?> Add user
  </button>
</div>

<!-- Filters -->
<div class="filters-bar card">
  <form method="GET" action="" class="filters-bar__form">
    <div class="filters-bar__search">
      <span class="filters-bar__search-icon"><?= icon('search') ?></span>
      <input type="text" name="search" class="form-input filters-bar__input"
             placeholder="Name, email or reg number" value="<?= e($search) ?>">
    </div>
    <select name="role" class="form-input form-select filters-bar__select">
      <option value="">All roles</option>
      <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Students</option>
      <option value="staff"   <?= $role_filter === 'staff'   ? 'selected' : '' ?>>Staff</option>
      <option value="admin"   <?= $role_filter === 'admin'   ? 'selected' : '' ?>>Admins</option>
    </select>
    <button type="submit" class="btn btn--secondary btn--sm"><?= icon('filter') ?> Filter</button>
    <?php if ($role_filter || $search): ?>
      <a href="?" class="btn btn--ghost btn--sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card table-card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Name</th><th>Email</th><th>Role</th><th>Department</th>
          <th>Registered</th><th>Status</th><th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr class="<?= !$u['is_active'] ? 'row--muted' : '' ?>">
          <td>
            <strong><?= e($u['full_name']) ?></strong>
            <?php if ($u['reg_number']): ?>
              <br><small class="text-muted"><?= e($u['reg_number']) ?></small>
            <?php endif; ?>
          </td>
          <td><?= e($u['email']) ?></td>
          <td><span class="badge badge--grey badge--xs"><?= e(ucfirst($u['role'])) ?></span></td>
          <td><?= $u['dept_name'] ? e($u['dept_name']) : '--' ?></td>
          <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ($u['is_active']): ?>
              <span class="badge badge--green">Active</span>
            <?php else: ?>
              <span class="badge badge--red">Disabled</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="action-group">
              <?php if ($u['id'] !== $user['id']): ?>
                <!-- Toggle active -->
                <form method="POST" action="" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <input type="hidden" name="is_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                  <button type="submit" class="btn btn--ghost btn--xs">
                    <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                  </button>
                </form>
                <!-- Reset password -->
                <form method="POST" action="" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn--ghost btn--xs"><?= icon('lock') ?> Reset pw</button>
                </form>
              <?php else: ?>
                <span class="text-muted" style="font-size:13px">You</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pag['total_pages'] > 1): ?>
  <nav class="pagination" aria-label="Users pagination">
    <?php if ($pag['current_page'] > 1): ?>
      <a href="<?= pagination_url($pag['current_page'] - 1) ?>" class="btn btn--ghost btn--sm"><?= icon('chevron-left') ?> Prev</a>
    <?php endif; ?>
    <span class="pagination__info">Page <?= $pag['current_page'] ?> of <?= $pag['total_pages'] ?></span>
    <?php if ($pag['current_page'] < $pag['total_pages']): ?>
      <a href="<?= pagination_url($pag['current_page'] + 1) ?>" class="btn btn--ghost btn--sm">Next <?= icon('chevron-right') ?></a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>
</div>

<!-- Add User Modal -->
<div class="modal" id="add-user-modal" role="dialog" aria-modal="true" aria-labelledby="add-user-title" hidden>
  <div class="modal__overlay" onclick="document.getElementById('add-user-modal').setAttribute('hidden','')"></div>
  <div class="modal__box">
    <div class="modal__header">
      <h2 class="modal__title" id="add-user-title">Add new user</h2>
      <button class="modal__close" onclick="document.getElementById('add-user-modal').setAttribute('hidden','')" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <form method="POST" action="" id="add-user-form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_user">
      <div class="modal__body">
        <div class="form-row form-row--half">
          <div class="form-group">
            <label class="form-label" for="add_full_name">Full name</label>
            <input type="text" id="add_full_name" name="full_name" class="form-input" required>
            <span class="form-error" role="alert"><?= e($errors['add_full_name'] ?? '') ?></span>
          </div>
          <div class="form-group">
            <label class="form-label" for="add_email">Email</label>
            <input type="email" id="add_email" name="email" class="form-input" required>
            <span class="form-error" role="alert"><?= e($errors['add_email'] ?? '') ?></span>
          </div>
        </div>
        <div class="form-row form-row--half">
          <div class="form-group">
            <label class="form-label" for="add_role">Role</label>
            <select id="add_role" name="role" class="form-input form-select" required>
              <option value="">Select role</option>
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
              <option value="student">Student</option>
            </select>
            <span class="form-error" role="alert"><?= e($errors['add_role'] ?? '') ?></span>
          </div>
          <div class="form-group">
            <label class="form-label" for="add_dept">Department</label>
            <select id="add_dept" name="department_id" class="form-input form-select">
              <option value="">None</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="add_phone">Phone</label>
          <input type="tel" id="add_phone" name="phone" class="form-input" placeholder="+250788123456">
        </div>
        <div class="form-row form-row--half">
          <div class="form-group">
            <label class="form-label" for="add_password">Password</label>
            <input type="password" id="add_password" name="password" class="form-input" required autocomplete="new-password">
            <span class="form-error" role="alert"><?= e($errors['add_password'] ?? '') ?></span>
          </div>
          <div class="form-group">
            <label class="form-label" for="add_confirm">Confirm password</label>
            <input type="password" id="add_confirm" name="confirm_password" class="form-input" required autocomplete="new-password">
            <span class="form-error" role="alert"><?= e($errors['add_confirm'] ?? '') ?></span>
          </div>
        </div>
      </div>
      <div class="modal__footer">
        <button type="button" class="btn btn--ghost" onclick="document.getElementById('add-user-modal').setAttribute('hidden','')">Cancel</button>
        <button type="submit" class="btn btn--primary">Create user</button>
      </div>
    </form>
  </div>
</div>

<?php
// Re-open modal if there were errors.
if (!empty($errors)):
?>
<script>document.getElementById('add-user-modal').removeAttribute('hidden');</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
