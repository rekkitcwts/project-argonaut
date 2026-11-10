<?php declare(strict_types=1);

$page_title = 'Attendance';
require __DIR__ . '/../includes/bootstrap.php';
app_start_session();
require_role('Administrator');

require_once __DIR__ . '/../includes/attendance.php';

global $pdo;

$filterDate = trim($_GET['date'] ?? date('Y-m-d'));
if ($filterDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $filterDate = date('Y-m-d');
}

$sql = '
    SELECT a.id, a.employee_id, a.work_date, a.time_in, a.time_out, a.created_at,
           e.employee_code, e.first_name, e.last_name
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    WHERE a.work_date = ?';
$params = [$filterDate];
$sql .= ' ORDER BY a.time_in ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require __DIR__ . '/../includes/layout-header.php';
?>
<div class="page-header">
  <h1>Attendance</h1>
  <form method="get" action="" style="display:inline-block; margin-left: 1rem; vertical-align: top;">
    <label for="date" style="margin: 0 0.35rem 0 0; font-size: 0.875rem; font-weight: 600;">Date</label>
    <input type="date" id="date" name="date" value="<?= h($filterDate) ?>">
    <button class="button button-primary" type="submit">Filter</button>
    <a class="button button-secondary" href="<?= url('attendance.php') ?>?date=<?= h(date('Y-m-d')) ?>">Today</a>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Employee</th>
        <th>Employee ID</th>
        <th>Time In</th>
        <th>Time Out</th>
        <th>Hours</th>
        <th>Recorded</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($rows) === 0): ?>
        <tr>
          <td colspan="6">No attendance records for <?= h($filterDate) ?>.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
          $hours = $row['time_out']
              ? attendance_format_duration(attendance_seconds_between($row['time_in'], $row['time_out']))
              : '-';
          ?>
          <tr>
            <td><?= h($row['first_name'] . ' ' . $row['last_name']) ?></td>
            <td><?= h($row['employee_code']) ?></td>
            <td><?= h($row['time_in']) ?></td>
            <td><?= h($row['time_out'] ?? '-') ?></td>
            <td><?= h($hours) ?></td>
            <td><?= h($row['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/layout-footer.php';