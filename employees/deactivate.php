<?php declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
app_start_session();
require_role('Administrator');

// Deactivate/reactivate only accepts POST with a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    flash('error', 'Invalid request.');
    redirect(url('employees/index.php'));
}

global $pdo;

$id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? 'deactivate';

if ($id === null || $id === false) {
    flash('error', 'Invalid employee ID.');
    redirect(url('employees/index.php'));
}

// An administrator cannot deactivate or reactivate their own account.
if ((int) $id === (int) $_SESSION['user_id']) {
    flash('error', 'You cannot change your own employment status.');
    redirect(url('employees/index.php'));
}

$newStatus = ($action === 'reactivate') ? 'active' : 'inactive';

try {
    $stmt = $pdo->prepare(
        'UPDATE employees SET employment_status = ? WHERE employee_id = ?'
    );
    $stmt->execute([$newStatus, (int) $id]);

    if ($stmt->rowCount() === 0) {
        flash('error', 'Employee not found.');
    } else {
        flash('success', $newStatus === 'active' ? 'Employee reactivated.' : 'Employee deactivated.');
    }
} catch (PDOException $e) {
    flash('error', 'Could not update the employee record.');
}

redirect(url('employees/index.php'));