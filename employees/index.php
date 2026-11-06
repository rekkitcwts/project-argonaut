<?php declare(strict_types=1);

$page_title = 'Employee Records';
require __DIR__ . '/../includes/bootstrap.php';
app_start_session();
require_role('Administrator');

global $pdo;

// --- Optional filters ---
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

// Build the query with PDO prepared parameters only.
$sql = '
    SELECT e.employee_id, e.employee_code, e.username, e.first_name, e.last_name,
           e.email, e.department, e.job_title, e.employment_status, e.date_hired,
           r.role_name
    FROM employees e
    LEFT JOIN roles r ON e.role_id = r.role_id
    WHERE 1 = 1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (e.first_name LIKE ?
                OR e.last_name LIKE ?
                OR e.username LIKE ?
                OR e.email LIKE ?
                OR e.department LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$allowedStatuses = ['active', 'inactive', 'on_leave', 'terminated'];
if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $sql .= ' AND e.employment_status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY e.employee_code';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

require __DIR__ . '/../includes/layout-header.php';
?>
<div class="page-header">
  <h1>Employee Records</h1>
  <a class="button button-primary" href="create.php">+ Add Employee</a>
</div>

<form method="get" action="" style="margin-bottom: 1rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-end;">
  <label style="margin:0; font-size:0.875rem; font-weight:600;">Search</label>
  <input type="text" name="search" value="<?= h($search) ?>" placeholder="Name, username, email, department">
  <label style="margin:0; font-size:0.875rem; font-weight:600;">Status</label>
  <select name="status">
    <option value="">All</option>
    <?php foreach ($allowedStatuses as $s): ?>
      <option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="button button-primary" type="submit">Filter</button>
  <?php if ($search !== '' || $status !== ''): ?>
    <a class="button button-secondary" href="index.php">Reset</a>
  <?php endif; ?>
</form>

<?php if (count($employees) === 0): ?>
  <p>No employees found.</p>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Employee ID</th>
          <th>Name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Department</th>
          <th>Job Title</th>
          <th>Status</th>
          <th>Date Hired</th>
          <th style="width: 220px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $emp): ?>
          <tr>
            <td><?= h($emp['employee_code']) ?></td>
            <td><?= h($emp['first_name']) ?> <?= h($emp['last_name']) ?></td>
            <td><?= h($emp['username']) ?></td>
            <td><?= h($emp['email']) ?></td>
            <td><?= h($emp['department']) ?></td>
            <td><?= h($emp['job_title']) ?></td>
            <td><span class="badge badge-<?= h($emp['employment_status']) ?>"><?= ucfirst($emp['employment_status']) ?></span></td>
            <td><?= h($emp['date_hired']) ?></td>
            <td>
              <a class="button-link" href="view.php?id=<?= (int) $emp['employee_id'] ?>">View</a>
              <a class="button-link" href="edit.php?id=<?= (int) $emp['employee_id'] ?>">Edit</a>
              <form action="deactivate.php" method="post" style="display:inline" data-confirm="Are you sure you want to <?= $emp['employment_status'] === 'active' ? 'deactivate' : 'reactivate' ?> this employee?">
                <?= csrf_field() ?>
                <input type="hidden" name="employee_id" value="<?= (int) $emp['employee_id'] ?>">
                <input type="hidden" name="action" value="<?= $emp['employment_status'] === 'active' ? 'deactivate' : 'reactivate' ?>">
                <button class="button button-danger" type="submit"><?= $emp['employment_status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout-footer.php';