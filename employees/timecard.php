<?php declare(strict_types=1);

$page_title = 'My Timecard';
require __DIR__ . '/../includes/bootstrap.php';
app_start_session();
require_auth();

require_once __DIR__ . '/../includes/timecard.php';

global $pdo;

// Employee timecard derives employee_id from the session only.
// If a different employee_id is passed in the request, return 403.
$requestedId = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
$sessionId   = (int) ($_SESSION['user_id'] ?? 0);

if ($requestedId !== null && $requestedId !== false && $requestedId !== $sessionId) {
    http_response_code(403);
    require __DIR__ . '/../includes/layout-header.php';
    ?>
<div class="form">
  <div class="page-header"><h1>Forbidden</h1></div>
  <?= flash_display() ?>
  <div class="flash error">You may only view your own timecard.</div>
  <div class="actions">
    <a class="button button-primary" href="<?= url('index.php') ?>">Back to dashboard</a>
  </div>
</div>
<?php
    require __DIR__ . '/../includes/layout-footer.php';
    exit;
}

$employeeId = $sessionId;

$cutoffKey = trim($_GET['cutoff_key'] ?? '');

$stmt = $pdo->prepare(
    'SELECT employee_code, first_name, last_name
     FROM employees WHERE employee_id = ?'
);
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    flash('error', 'Employee not found.');
    redirect(url('index.php'));
}

// Default to the current cutoff if none selected.
if ($cutoffKey === '') {
    $currentCutoff = timecard_get_cutoff(date('Y-m-d'));
    $cutoffKey     = $currentCutoff['cutoff_key'];
}

$selectedCutoff = timecard_parse_cutoff_key($cutoffKey);

$rows = [];
$summary = null;

if ($selectedCutoff !== null) {
    $stmt = $pdo->prepare(
        'SELECT a.id, a.work_date, a.time_in, a.time_out, a.hours_worked,
                a.edited_at, a.edit_reason
         FROM attendance a
         WHERE a.employee_id = ?
           AND a.work_date BETWEEN ? AND ?
         ORDER BY a.work_date ASC, a.time_in ASC'
    );
    $stmt->execute([
        $employeeId,
        $selectedCutoff['start_date'],
        $selectedCutoff['end_date'],
    ]);
    $rows = $stmt->fetchAll();

    $totalDays   = 0;
    $totalHours  = 0.0;
    $incomplete  = 0;
    foreach ($rows as $row) {
        $hours = timecard_resolve_hours($row);
        if ($row['time_out'] !== null && $row['time_out'] !== '') {
            $totalDays++;
            $totalHours += $hours ?? 0.0;
        } else {
            $incomplete++;
        }
    }
    $summary = [
        'total_days'   => $totalDays,
        'total_hours'  => $totalHours,
        'incomplete'   => $incomplete,
    ];
}

require __DIR__ . '/../includes/layout-header.php';
?>
<div class="page-header"><h1>My Timecard</h1></div>

<?= flash_display() ?>

<div class="form">
  <form method="get" action="" novalidate>
    <?= csrf_field() ?>

    <div class="field">
      <label for="cutoff_key">Cutoff period</label>
      <select id="cutoff_key" name="cutoff_key" required>
        <option value="">Select a period</option>
        <?php foreach (timecard_list_cutoffs() as $c): ?>
          <option value="<?= h($c['cutoff_key']) ?>" <?= $cutoffKey === $c['cutoff_key'] ? 'selected' : '' ?>>
            <?= h($c['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="actions">
      <button class="button button-primary" type="submit">View timecard</button>
    </div>
  </form>
</div>

<?php if ($selectedCutoff): ?>
  <div class="page-header" style="margin-top: 2rem;">
    <h2><?= h($employee['first_name'] . ' ' . $employee['last_name']) ?> &middot; <?= h($employee['employee_code']) ?></h2>
    <span class="badge" style="background:#e5e7eb; color:#374151;"><?= h($selectedCutoff['label']) ?></span>
  </div>

  <div class="form">
    <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end; margin-bottom:1rem;">
      <div>
        <strong>Days present:</strong> <?= (int) $summary['total_days'] ?>
      </div>
      <div>
        <strong>Total hours:</strong> <?= number_format($summary['total_hours'], 2) ?> h
      </div>
      <?php if ($summary['incomplete'] > 0): ?>
        <div style="color:#991b1b;">
          <strong><?= (int) $summary['incomplete'] ?> incomplete record(s)</strong> (timed in, not timed out &mdash; excluded from totals)
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Hours</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) === 0): ?>
          <tr>
            <td colspan="5">No attendance records for <?= h($selectedCutoff['label']) ?>.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
            $hours   = timecard_resolve_hours($row);
            $isOpen  = $row['time_out'] === null || $row['time_out'] === '';
            $isEdited = $row['edited_at'] !== null;
            ?>
            <tr>
              <td><?= h($row['work_date']) ?></td>
              <td><?= h($row['time_in'] ?? '—') ?></td>
              <td><?= $isOpen ? '—' : h($row['time_out']) ?></td>
              <td><?= $isOpen ? '<span style="color:#991b1b;"><strong>—</strong> incomplete</span>' : timecard_format_hours($hours) ?></td>
              <td>
                <?php if ($isOpen): ?>
                  <span class="badge badge-incomplete" title="Timed in but never timed out">Incomplete</span>
                <?php elseif ($isEdited): ?>
                  <span class="badge badge-edited" title="Edited on <?= h($row['edited_at']) ?> &mdash; reason: <?= h($row['edit_reason'] ?? '(none)') ?>">Edited</span>
                <?php else: ?>
                  <span class="badge badge-active">OK</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout-footer.php';