<?php declare(strict_types=1);

$page_title = 'Edit Timecard Entry';
require __DIR__ . '/../includes/bootstrap.php';
app_start_session();
require_role('Administrator');

require_once __DIR__ . '/../includes/timecard.php';

global $pdo;

$id     = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$empId  = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
$cutoff = trim($_GET['cutoff_key'] ?? '');

if ($id === null || $id === false) {
    flash('error', 'Invalid attendance record.');
    redirect(url('admin/timecard.php'));
}

// Load the attendance record.
$stmt = $pdo->prepare(
    'SELECT a.*, e.employee_code, e.first_name, e.last_name
     FROM attendance a
     JOIN employees e ON a.employee_id = e.employee_id
     WHERE a.id = ?'
);
$stmt->execute([(int) $id]);
$row = $stmt->fetch();

if (!$row) {
    flash('error', 'Attendance record not found.');
    redirect(url('admin/timecard.php'));
}

$errors = [];
$values = [
    'work_date'   => $row['work_date'],
    'time_in'     => $row['time_in'],
    'time_out'    => $row['time_out'],
    'edit_reason' => $row['edit_reason'] ?? '',
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

        // Ensure the date stays within the same cutoff context (informational only).
        if ($values['time_in'] !== '' && $values['time_out'] !== '') {
            $in = new DateTime('1970-01-01 ' . $values['time_in']);
            $out = new DateTime('1970-01-01 ' . $values['time_out']);
            if ($out->getTimestamp() < $in->getTimestamp()) {
                $errors[] = 'Time out must be after time in.';
            }
        }

        if (empty($errors)) {
            try {
                $hours = timecard_compute_hours($values['time_in'], $values['time_out']);
                $stmt = $pdo->prepare(
                    'UPDATE attendance
                         SET work_date = ?, time_in = ?, time_out = ?,
                             hours_worked = ?, edited_at = CURRENT_TIMESTAMP,
                             edited_by = ?, edit_reason = ?,
                             updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $stmt->execute([
                    $values['work_date'],
                    $values['time_in'],
                    $values['time_out'] === '' ? null : $values['time_out'],
                    $hours,
                    (int) $_SESSION['user_id'],
                    $values['edit_reason'] === '' ? null : $values['edit_reason'],
                    (int) $id,
                ]);
                flash('success', 'Timecard entry updated.');
                redirect(url('admin/timecard.php') . '?employee_id=' . (int) $empId . '&amp;cutoff_key=' . urlencode($cutoff));
            } catch (PDOException $e) {
                $errors[] = 'Could not update the timecard entry. Please try again.';
            }
        }
    }
}

require __DIR__ . '/../includes/layout-header.php';
?>
<div class="page-header"><h1>Edit Timecard Entry</h1></div>

<?= flash_display() ?>

<div class="form">
  <p>
    <strong>Employee:</strong> <?= h($row['first_name'] . ' ' . $row['last_name']) ?>
    (<?= h($row['employee_code']) ?>)
  </p>

  <?php foreach ($errors as $error): ?>
    <div class="flash error"><?= h($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

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
      <small class="note">Why was this entry edited?</small>
    </div>

    <div class="actions">
      <button class="button button-primary" type="submit">Save changes</button>
      <a class="button button-secondary" href="<?= url('admin/timecard.php') ?>?employee_id=<?= (int) $empId ?>&amp;cutoff_key=<?= h($cutoff) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/layout-footer.php';