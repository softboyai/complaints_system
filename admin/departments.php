<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

require_role('admin');
$pdo = get_db();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name  = trim($_POST['name']  ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $did   = (int)($_POST['dept_id'] ?? 0);

        if ($name === '')            $errors['name']  = 'Department name is required.';
        if (mb_strlen($name) > 100) $errors['name']  = 'Name must be under 100 characters.';
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';

        if (empty($errors)) {
            if ($action === 'add') {
                $pdo->prepare('INSERT INTO departments (name, email) VALUES (?, ?)')
                    ->execute([$name, $email ?: null]);
                set_flash('flash_success', 'Department added.');
            } else {
                $pdo->prepare('UPDATE departments SET name = ?, email = ? WHERE id = ?')
                    ->execute([$name, $email ?: null, $did]);
                set_flash('flash_success', 'Department updated.');
            }
            header('Location: ' . APP_URL . '/admin/departments.php');
            exit;
        }
    }

    if ($action === 'delete') {
        $did = (int)($_POST['dept_id'] ?? 0);
        // Check no complaints assigned.
        $count = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE department_id = ?');
        $count->execute([$did]);
        if ($count->fetchColumn() > 0) {
            set_flash('flash_error', 'Cannot delete a department that has complaints assigned to it. Reassign the complaints first.');
        } else {
            $pdo->prepare('DELETE FROM departments WHERE id = ?')->execute([$did]);
            set_flash('flash_success', 'Department deleted.');
        }
        header('Location: ' . APP_URL . '/admin/departments.php');
        exit;
    }
}

// Fetch departments with complaint count.
$departments = $pdo->query(
    'SELECT d.*, COUNT(c.id) AS complaint_count
     FROM departments d
     LEFT JOIN complaints c ON c.department_id = d.id
     GROUP BY d.id ORDER BY d.name'
)->fetchAll();

$page_title = 'Departments';
$active_nav = 'departments';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div><h2 class="page-title">Departments</h2></div>
  <button class="btn btn--primary" onclick="document.getElementById('add-dept-modal').removeAttribute('hidden')">
    <?= icon('plus-circle') ?> Add department
  </button>
</div>

<div class="card table-card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Name</th><th>Email</th><th>Complaints</th><th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($departments as $d): ?>
        <tr>
          <td><strong><?= e($d['name']) ?></strong></td>
          <td><?= $d['email'] ? e($d['email']) : '--' ?></td>
          <td><?= (int)$d['complaint_count'] ?></td>
          <td>
            <div class="action-group">
              <button class="btn btn--ghost btn--xs"
                      onclick="openEditDept(<?= $d['id'] ?>, '<?= addslashes($d['name']) ?>', '<?= addslashes($d['email'] ?? '') ?>')">
                <?= icon('edit') ?> Edit
              </button>
              <?php if ($d['complaint_count'] == 0): ?>
              <form method="POST" action="" style="display:inline"
                    onsubmit="return confirm('Delete <?= addslashes($d['name']) ?>? This cannot be undone.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                <button type="submit" class="btn btn--ghost btn--xs btn--danger"><?= icon('trash') ?> Delete</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Department Modal -->
<div class="modal" id="add-dept-modal" role="dialog" aria-modal="true" aria-labelledby="add-dept-title" hidden>
  <div class="modal__overlay" onclick="this.parentElement.setAttribute('hidden','')"></div>
  <div class="modal__box modal__box--sm">
    <div class="modal__header">
      <h2 class="modal__title" id="add-dept-title">Add department</h2>
      <button class="modal__close" onclick="document.getElementById('add-dept-modal').setAttribute('hidden','')" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal__body">
        <div class="form-group">
          <label class="form-label" for="add_name">Department name</label>
          <input type="text" id="add_name" name="name" class="form-input" required>
          <span class="form-error" role="alert"><?= e($errors['name'] ?? '') ?></span>
        </div>
        <div class="form-group">
          <label class="form-label" for="add_dept_email">Contact email (optional)</label>
          <input type="email" id="add_dept_email" name="email" class="form-input" placeholder="department@mku.ac.rw">
          <span class="form-error" role="alert"><?= e($errors['email'] ?? '') ?></span>
        </div>
      </div>
      <div class="modal__footer">
        <button type="button" class="btn btn--ghost" onclick="document.getElementById('add-dept-modal').setAttribute('hidden','')">Cancel</button>
        <button type="submit" class="btn btn--primary">Add department</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Department Modal -->
<div class="modal" id="edit-dept-modal" role="dialog" aria-modal="true" aria-labelledby="edit-dept-title" hidden>
  <div class="modal__overlay" onclick="this.parentElement.setAttribute('hidden','')"></div>
  <div class="modal__box modal__box--sm">
    <div class="modal__header">
      <h2 class="modal__title" id="edit-dept-title">Edit department</h2>
      <button class="modal__close" onclick="document.getElementById('edit-dept-modal').setAttribute('hidden','')" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <form method="POST" action="" id="edit-dept-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="dept_id" id="edit_dept_id">
      <div class="modal__body">
        <div class="form-group">
          <label class="form-label" for="edit_name">Department name</label>
          <input type="text" id="edit_name" name="name" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_dept_email">Contact email</label>
          <input type="email" id="edit_dept_email" name="email" class="form-input" placeholder="department@mku.ac.rw">
        </div>
      </div>
      <div class="modal__footer">
        <button type="button" class="btn btn--ghost" onclick="document.getElementById('edit-dept-modal').setAttribute('hidden','')">Cancel</button>
        <button type="submit" class="btn btn--primary">Save changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditDept(id, name, email) {
  document.getElementById('edit_dept_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_dept_email').value = email;
  document.getElementById('edit-dept-modal').removeAttribute('hidden');
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
