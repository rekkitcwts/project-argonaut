<?php declare(strict_types=1);

$page_title = 'Employee Details';
require __DIR__ . '/../includes/bootstrap.php';
app_start_session();
require_auth();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    flash('error', 'Invalid employee ID.');
    redirect(url('employees/index.php'));
}

global $pdo;

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

// Employees may only view their own profile; administrators may view any record.
if (!is_admin() && (int) $employee['employee_id'] !== (int) ($_SESSION['user_id'] ?? 0)) {
    flash('error', 'You can only view your own profile.');
    redirect(url('profile.php'));
}

require __DIR__ . '/../includes/layout-header.php';
?>
<div class="page-header">
  <h1>Employee Details</h1>
  <a class="button button-secondary" href="index.php">&larr; Back to list</a>
</div>

<div class="form">
  <table class="detail-table">
    <tr><td>Employee ID</td><td><?= h($employee['employee_code']) ?></td></tr>
    <tr><td>Name</td><td><?= h($employee['first_name']) ?> <?= h($employee['last_name']) ?></td></tr>
    <tr><td>Username</td><td><?= h($employee['username']) ?></td></tr>
    <tr><td>Email address</td><td><?= h($employee['email'] ?? '(not provided)') ?></td></tr>
    <tr><td>Contact number</td><td><?= h($employee['contact_number'] ?? '(not provided)') ?></td></tr>
    <tr><td>Department</td><td><?= h($employee['department'] ?? '(not provided)') ?></td></tr>
    <tr><td>Job title</td><td><?= h($employee['job_title'] ?? '(not provided)') ?></td></tr>
    <tr><td>Employment status</td><td><span class="badge badge-<?= h($employee['employment_status']) ?>"><?= ucfirst($employee['employment_status']) ?></span></td></tr>
    <tr><td>Date hired</td><td><?= h($employee['date_hired']) ?></td></tr>
    <tr><td>Role</td><td><?= h($employee['role_name'] ?? '(not assigned)') ?></td></tr>
    <tr><td>Record created</td><td><?= h($employee['created_at']) ?></td></tr>
    <tr><td>Last updated</td><td><?= h($employee['updated_at']) ?></td></tr>
  </table>
</div>

<?php if (is_admin()): ?>
  <div class="page-header" style="margin-top: 1.5rem;">
    <a class="button-link" href="edit.php?id=<?= (int) $employee['employee_id'] ?>">Edit employee</a>
    <form action="deactivate.php" method="post" style="display:inline; margin-left: 1rem;" data-confirm="Are you sure you want to <?= $employee['employment_status'] === 'active' ? 'deactivate' : 'reactivate' ?> this employee?">
      <?= csrf_field() ?>
      <input type="hidden" name="employee_id" value="<?= (int) $employee['employee_id'] ?>">
      <input type="hidden" name="action" value="<?= $employee['employment_status'] === 'active' ? 'deactivate' : 'reactivate' ?>">
      <button class="button button-danger" type="submit"><?= $employee['employment_status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
    </form>
    <?php if ((int) $employee['employee_id'] === (int) $_SESSION['user_id']): ?>
      <span class="small" style="color:#6b7280;">&nbsp;&nbsp;(You cannot deactivate your own account.)</span>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout-footer.php';