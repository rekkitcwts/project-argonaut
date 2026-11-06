<?php declare(strict_types=1);

$page_title = 'Edit Employee';
require __DIR__ . '/../includes/bootstrap.php';
app_start_session();
require_role('Administrator');

global $pdo;

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    flash('error', 'Invalid employee ID.');
    redirect(url('employees/index.php'));
}

$stmt = $pdo->prepare(
    'SELECT e.*, r.role_name
     FROM employees e
     LEFT JOIN roles r ON e.role_id = r.role_id
     WHERE e.employee_id = ?'
);
$stmt->execute([(int) $id]);
$employee = $stmt->fetch();

if (!$employee) {
    flash('error', 'Employee not found.');
    redirect(url('employees/index.php'));
}

$roles = $pdo->query('SELECT role_id, role_name FROM roles ORDER BY role_name')->fetchAll();

$errors = [];
$values = [
    'employee_code'      => $employee['employee_code'],
    'first_name'         => $employee['first_name'],
    'last_name'          => $employee['last_name'],
    'username'           => $employee['username'],
    'email'              => $employee['email'],
    'contact_number'     => $employee['contact_number'],
    'department'         => $employee['department'],
    'job_title'          => $employee['job_title'],
    'employment_status'  => $employee['employment_status'],
    'date_hired'         => $employee['date_hired'],
    'role_id'            => $employee['role_id'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Invalid request. Please resubmit the form.';
    } else {
        $values = [
            'employee_code'      => trim($_POST['employee_code'] ?? ''),
            'first_name'         => trim($_POST['first_name'] ?? ''),
            'last_name'          => trim($_POST['last_name'] ?? ''),
            'username'           => trim($_POST['username'] ?? ''),
            'email'              => trim($_POST['email'] ?? ''),
            'contact_number'     => trim($_POST['contact_number'] ?? ''),
            'department'         => trim($_POST['department'] ?? ''),
            'job_title'          => trim($_POST['job_title'] ?? ''),
            'employment_status'  => $_POST['employment_status'] ?? $employee['employment_status'],
            'date_hired'         => $_POST['date_hired'] ?? '',
            'role_id'            => $_POST['role_id'] ?? $employee['role_id'],
        ];
        $newPassword = $_POST['new_password'] ?? '';

        if ($values['employee_code'] === '') { $errors[] = 'Employee ID is required.'; }
        elseif (!preg_match('/^[A-Z0-9-]{1,20}$/', $values['employee_code'])) {
            $errors[] = 'Employee ID may only contain uppercase letters, numbers, and hyphens.';
        }
        if ($values['first_name'] === '') { $errors[] = 'First name is required.'; }
        if ($values['last_name'] === '') { $errors[] = 'Last name is required.'; }
        if ($values['username'] === '') { $errors[] = 'Username is required.'; }
        if ($values['email'] !== '' && !is_valid_email($values['email'])) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($values['date_hired'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['date_hired'])) {
            $errors[] = 'Please enter a valid hire date.';
        }
        if (!in_array($values['employment_status'], ['active', 'inactive', 'on_leave', 'terminated'], true)) {
            $errors[] = 'Invalid employment status.';
        }
        if ($values['role_id'] === '') { $errors[] = 'Please select a role.'; }
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }

        if (empty($errors)) {
            foreach (['employee_code', 'username'] as $field) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE $field = ? AND employee_id != ?");
                $chk->execute([$values[$field], (int) $id]);
                if ((int) $chk->fetchColumn() > 0) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' already exists.';
                }
            }
            if ($values['email'] !== '') {
                $chk = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE email = ? AND employee_id != ?');
                $chk->execute([$values['email'], (int) $id]);
                if ((int) $chk->fetchColumn() > 0) {
                    $errors[] = 'Email address already exists.';
                }
            }
        }

        if (empty($errors)) {
            try {
                $update = $pdo->prepare(
                    'UPDATE employees
                        SET employee_code = ?, username = ?, first_name = ?, last_name = ?,
                            email = ?, contact_number = ?, department = ?, job_title = ?,
                            employment_status = ?, date_hired = ?, role_id = ?,
                            updated_at = CURRENT_TIMESTAMP, updated_by = ?
                     WHERE employee_id = ?'
                );
                $update->execute([
                    $values['employee_code'],
                    $values['username'],
                    $values['first_name'],
                    $values['last_name'],
                    $values['email'] === '' ? null : $values['email'],
                    $values['contact_number'] === '' ? null : $values['contact_number'],
                    $values['department'] === '' ? null : $values['department'],
                    $values['job_title'] === '' ? null : $values['job_title'],
                    $values['employment_status'],
                    $values['date_hired'],
                    (int) $values['role_id'],
                    (int) $_SESSION['user_id'],
                    (int) $id,
                ]);

                if ($newPassword !== '') {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $pw = $pdo->prepare('UPDATE employees SET password_hash = ? WHERE employee_id = ?');
                    $pw->execute([$hash, (int) $id]);
                }

                flash('success', 'Employee updated successfully.');
                redirect(url('employees/view.php?id=' . (int) $id));
            } catch (PDOException $e) {
                $errors[] = 'Could not update the employee. Please try again.';
            }
        }
    }
}

require __DIR__ . '/../includes/layout-header.php';
?>
<div class="page-header">
  <h1>Edit Employee</h1>
  <a class="button button-secondary" href="view.php?id=<?= (int) $id ?>">&larr; Back to profile</a>
</div>

<?php foreach ($errors as $error): ?>
  <div class="flash error"><?= h($error) ?></div>
<?php endforeach; ?>

<form method="post" action="" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div class="field">
    <label for="employee_code">Employee ID</label>
    <input type="text" id="employee_code" name="employee_code"
           value="<?= h($values['employee_code']) ?>" required maxlength="20">
  </div>

  <div class="field">
    <label for="first_name">First name</label>
    <input type="text" id="first_name" name="first_name"
           value="<?= h($values['first_name']) ?>" required maxlength="50">
  </div>

  <div class="field">
    <label for="last_name">Last name</label>
    <input type="text" id="last_name" name="last_name"
           value="<?= h($values['last_name']) ?>" required maxlength="50">
  </div>

  <div class="field">
    <label for="username">Username</label>
    <input type="text" id="username" name="username"
           value="<?= h($values['username']) ?>" required maxlength="50">
  </div>

  <div class="field">
    <label for="email">Email address</label>
    <input type="email" id="email" name="email"
           value="<?= h($values['email']) ?>" maxlength="100">
  </div>

  <div class="field">
    <label for="contact_number">Contact number</label>
    <input type="tel" id="contact_number" name="contact_number"
           value="<?= h($values['contact_number']) ?>" maxlength="30">
  </div>

  <div class="field">
    <label for="department">Department</label>
    <input type="text" id="department" name="department"
           value="<?= h($values['department']) ?>" maxlength="50">
  </div>

  <div class="field">
    <label for="job_title">Job title</label>
    <input type="text" id="job_title" name="job_title"
           value="<?= h($values['job_title']) ?>" maxlength="50">
  </div>

  <div class="field">
    <label for="employment_status">Employment status</label>
    <select id="employment_status" name="employment_status">
      <option value="active" <?= $values['employment_status'] === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $values['employment_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      <option value="on_leave" <?= $values['employment_status'] === 'on_leave' ? 'selected' : '' ?>>On leave</option>
      <option value="terminated" <?= $values['employment_status'] === 'terminated' ? 'selected' : '' ?>>Terminated</option>
    </select>
  </div>

  <div class="field">
    <label for="date_hired">Date hired</label>
    <input type="date" id="date_hired" name="date_hired"
           value="<?= h($values['date_hired']) ?>" required>
  </div>

  <div class="field">
    <label for="role_id">Role</label>
    <select id="role_id" name="role_id" required>
      <?php foreach ($roles as $role): ?>
        <option value="<?= (int) $role['role_id'] ?>" <?= (string) ($values['role_id'] ?? '') === (string) $role['role_id'] ? 'selected' : '' ?>>
          <?= h($role['role_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="field">
    <label for="new_password">New password (optional)</label>
    <input type="password" id="new_password" name="new_password" minlength="8">
    <button type="button" class="toggle-password" data-target="new_password">Show</button>
    <small class="note">Leave blank to keep the current password. Minimum 8 characters if changed.</small>
  </div>

  <div class="actions">
    <button class="button button-primary" type="submit">Save Changes</button>
    <a class="button button-secondary" href="index.php">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/layout-footer.php';