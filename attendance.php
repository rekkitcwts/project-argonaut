<?php declare(strict_types=1);

$page_title = 'Time Clock';
require __DIR__ . '/includes/bootstrap.php';
app_start_session();

require_once __DIR__ . '/includes/attendance.php';

global $pdo;

$employeeCode = trim($_POST['employee_code'] ?? '');
$employee = null;
$openRecord = null;
$action = 'time_in';
$lookupError = null;

if ($employeeCode !== '') {
    $stmt = $pdo->prepare(
        'SELECT employee_id, employee_code, first_name, last_name, employment_status
         FROM employees
         WHERE employee_code = ?'
    );
    $stmt->execute([$employeeCode]);
    $employee = $stmt->fetch();

    if (!$employee) {
        $lookupError = 'Unknown employee ID.';
    } elseif ($employee['employment_status'] !== 'active') {
        $lookupError = 'This employee account is not active.';
    } else {
        $openRecord = attendance_get_open_record($pdo, (int) $employee['employee_id']);
        $action = $openRecord ? 'time_out' : 'time_in';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash('error', 'Invalid request. Please resubmit.');
    } elseif (!$employee || $employee['employment_status'] !== 'active') {
        flash('error', 'Unknown or inactive employee ID.');
    } else {
        $empId = (int) $employee['employee_id'];
        try {
            if ($action === 'time_in') {
                // Double-check no open record exists at all (defends against
                // double time-in across midnight / night shifts).
                $existing = attendance_get_open_record($pdo, $empId);
                if ($existing) {
                    flash('error', 'You have already timed in. Please time out first.');
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance (employee_id, work_date, time_in)
                         VALUES (?, ?, ?)'
                    );
                    $today = date('Y-m-d');
                    $stmt->execute([$empId, $today, date('H:i:s')]);
                    flash('success', 'Timed in at ' . date('g:i A') . '.');
                }
            } else {
                if (!$openRecord) {
                    flash('error', 'There is no open time-in record to time out from.');
                } else {
                    $timeOut = date('H:i:s');
                    $stmt = $pdo->prepare(
                        'UPDATE attendance
                         SET time_out = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?'
                    );
                    $stmt->execute([$timeOut, (int) $openRecord['id']]);
                    $seconds = attendance_seconds_between($openRecord['time_in'], $timeOut);
                    flash('success', 'Timed out at ' . date('g:i A') . ' — ' . attendance_format_duration($seconds) . ' recorded.');
                }
            }
        } catch (PDOException $e) {
            flash('error', 'Could not record attendance. Please try again.');
        }
    }
    redirect(url('attendance.php'));
}

require __DIR__ . '/includes/layout-header.php';
?>
<div class="form">
  <div class="page-header"><h1>Time Clock</h1></div>

  <div id="clock" style="font-size:1.25rem;font-weight:700;text-align:center;margin-bottom:1rem;letter-spacing:0.05em;">--:--:--</div>

  <?= flash_display() ?>

  <?php if ($lookupError !== null): ?>
    <div class="flash error"><?= h($lookupError) ?></div>
  <?php endif; ?>

  <form method="post" action="" novalidate>
    <?= csrf_field() ?>

    <div class="field">
      <label for="employee_code">Employee ID</label>
      <input type="text" id="employee_code" name="employee_code"
             value="<?= h($employeeCode) ?>" required maxlength="20" autocomplete="off">
      <small class="note">Type your employee ID (e.g. EMP-0001), then press the button below.</small>
    </div>

    <?php if ($employee && $employee['employment_status'] === 'active'): ?>
      <div class="field">
        <label>Employee</label>
        <input type="text" readonly
               value="<?= h($employee['first_name'] . ' ' . $employee['last_name']) ?> (<?= h($employee['employee_code']) ?>)">
        <small class="note"><?= $action === 'time_in'
            ? 'No open time-in record. Press Time In to start your shift.'
            : 'You have an open time-in record. Press Time Out to end your shift.' ?></small>
      </div>
    <?php endif; ?>

    <div class="actions">
      <button class="button button-primary" type="submit"><?= $action === 'time_in' ? 'Time In' : 'Time Out' ?></button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout-footer.php';