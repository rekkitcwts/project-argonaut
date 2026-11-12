<?php declare(strict_types=1);

$page_title = 'Add Missing Entry';
require __DIR__ . '/../includes/bootstrap.php';
app_start_session();
require_role('Administrator');

require_once __DIR__ . '/../includes/timecard.php';

global $pdo;

$empId   = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
$cutoff  = trim($_GET['cutoff_key'] ?? '');

if ($empId === null || $empId === false) {
    flash('error', 'Invalid employee.');
    redirect(url('admin/timecard.php'));
}

$stmt = $pdo->prepare(
    'SELECT employee_id, employee_code, first_name, last_name
     FROM employees WHERE employee_id = ?'
);
$stmt->execute([$empId]);
$employee = $stmt->fetch();

if (!$employee) {
    flash('error', 'Employee not found.');
    redirect(url('admin/timecard.php'));
}

$selectedCutoff = null;
if ($cutoff !== '') {
    $selectedCutoff = timecard_parse_cutoff_key($cutoff);
    if ($selectedCutoff === null) {
        flash('error', 'Invalid cutoff period.');
        $selectedCutoff = null;
    }
}

$errors = [];
$values = [
    'work_date'   => '',
    'time_in'     => '',
    'time_out'    => '',
    'edit_reason' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Invalid request. Please resubmit the form.';
    } else {
        $values = [
            'work_date'   => trim($_POST['work_date'] ?? ''),
            'time_in'     => trim($_POST['time_in'] ?? ''),
            'time_out'    => trim($_POST['time_out'] ?? ''),
            'edit_reason' => trim($_POST['edit_reason'] ?? ''),
        ];

        if ($values['work_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['work_date'])) {
            $errors[] = 'Please enter a valid date.';
        }
        if ($values['time_in'] === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $values['time_in'])) {
            $errors[] = 'Please enter a valid time in.';
        }
        if ($values['time_out'] !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $values['time_out'])) {
            $errors[] = 'Please enter a valid time out.';
        }

        if ($selectedCutoff && ($values['work_date'] < $selectedCutoff['start_date'] || $values['work_date'] > $selectedCutoff['end_date'])) {
            $errors[] = 'The date must fall within the selected cutoff period.';
        }

        if ($values['time_in'] !== '' && $values['time_out'] !== '') {
            $in = new DateTime('1970-01-01 ' . $values['time_in']);
            $out = new DateTime('1970-01-01 ' . $values['time_out']);
            if ($out->getTimestamp() < $in->getTimestamp()) {
                $errors[] = 'Time out must be after time in.';
            }
        }

        if (empty($errors)) {
            $chk = $pdo->prepare(
                'SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND work_date = ?'
            );
            $chk->execute([$empId, $values['work_date']]);
            if ((int) $chk->fetchColumn() > 0) {
                $errors[] = 'An attendance record already exists for this date.';
            }
        }

        if (empty($errors)) {
            try {
                $hours = timecard_compute_hours($values['time_in'], $values['time_out']);
                $stmt = $pdo->prepare(
                    'INSERT INTO attendance
                         (employee_id, work_date, time_in, time_out, hours_worked,
                          created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
                );
                $stmt->execute([
                    $empId,
                    $values['work_date'],
                    $values['time_in'],
                    $values['time_out'] === '' ? null : $values['time_out'],
                    $hours,
                ]);
                flash('success', 'Missing attendance entry added.');
                redirect(url('admin/timecard.php') . '?employee_id=' . (int) $empId . '&amp;cutoff_key=' . urlencode($cutoff));
            } catch (PDOException $e) {
                $errors[] = 'Could not add the attendance entry. Please try again.';
            }
        }
    }
}

require __DIR__ . '/../includes/layout-header.php';
?>
<div class="page-header"><h1>Add Missing Entry</h1></div>

<?= flash_display() ?>

<div class="form">
  <p>
    <strong>Employee:</strong> <?= h($employee['first_name'] . ' ' . $employee['last_name']) ?>
    (<?= h($employee['employee_code']) ?>)
  </p>

  <?php foreach ($errors as $error): ?>
    <div class="flash error"><?= h($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="employee_id" value="<?= (int) $empId ?>">

    <div class="field">
      <label for="work_date">Date</label>
      <input type="date" id="work_date" name="work_date"
             value="<?= h($values['work_date']) ?>" required>
    </div>

    <div class="field">
      <label for="time_in">Time In</label>
      <input type="time" id="time_in" name="time_in"
             value="<?= h($values['time_in']) ?>" required>
    </div>

    <div class="field">
      <label for="time_out">Time Out</label>
      <input type="time" id="time_out" name="time_out"
             value="<?= h($values['time_out']) ?>">
      <small class="note">Leave blank if the employee is still clocked in.</small>
    </div>

    <div class="field">
      <label for="edit_reason">Reason / note (optional)</label>
      <input type="text" id="edit_reason" name="edit_reason"
             value="<?= h($values['edit_reason']) ?>" maxlength="255">
      <small class="note">Why was this entry added manually?</small>
    </div>

    <div class="actions">
      <button class="button button-primary" type="submit">Add entry</button>
      <a class="button button-secondary" href="<?= url('admin/timecard.php') ?>?employee_id=<?= (int) $empId ?>&amp;cutoff_key=<?= h($cutoff) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/layout-footer.php';