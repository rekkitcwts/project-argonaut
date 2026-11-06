<?php declare(strict_types=1);

$page_title = 'First-time setup';
require __DIR__ . '/includes/bootstrap.php';
app_start_session();

global $pdo;

/*
 * One-time setup: creates the initial administrator account.
 * No default password is hardcoded: the installer chooses credentials here.
 * Once an administrator exists, setup.php refuses to run and should be deleted.
 */

// Seed the role rows (the schema also seeds them).
try {
    $pdo->exec("INSERT IGNORE INTO roles (role_name, role_description) VALUES
        ('Administrator', 'Full access to manage employee records and accounts'),
        ('Employee', 'Can log in and view their own profile only')");
} catch (PDOException $e) {
    // Roles probably already exist; continue.
}

// If an administrator already exists, setup is finished.
try {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM employees e
         JOIN roles r ON e.role_id = r.role_id
         WHERE r.role_name = ?'
    );
    $stmt->execute(['Administrator']);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'Setup has already been completed. Please delete setup.php and log in.');
        redirect(url('login.php'));
    }
} catch (PDOException $e) {
    // Table may not exist yet; the error surfaces when the insert is attempted.
}

$errors = [];
$values = [
    'employee_code' => '',
    'first_name'    => '',
    'last_name'     => '',
    'username'      => '',
    'email'         => '',
    'date_hired'    => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Invalid request. Please resubmit the form.';
    } else {
        $values = [
            'employee_code' => trim($_POST['employee_code'] ?? ''),
            'first_name'    => trim($_POST['first_name'] ?? ''),
            'last_name'     => trim($_POST['last_name'] ?? ''),
            'username'      => trim($_POST['username'] ?? ''),
            'email'         => trim($_POST['email'] ?? ''),
            'date_hired'    => $_POST['date_hired'] ?? '',
        ];
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        // --- Validation ---
        if ($values['employee_code'] === '') {
            $errors[] = 'Employee ID is required.';
        } elseif (!preg_match('/^[A-Z0-9-]{1,20}$/', $values['employee_code'])) {
            $errors[] = 'Employee ID may only contain uppercase letters, numbers, and hyphens.';
        }
        if ($values['first_name'] === '') {
            $errors[] = 'First name is required.';
        }
        if ($values['last_name'] === '') {
            $errors[] = 'Last name is required.';
        }
        if ($values['username'] === '') {
            $errors[] = 'Username is required.';
        }
        if ($values['email'] !== '' && !is_valid_email($values['email'])) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($values['date_hired'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['date_hired'])) {
            $errors[] = 'Please enter a valid hire date.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        // --- Uniqueness checks ---
        if (empty($errors)) {
            foreach (['employee_code', 'username'] as $field) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE $field = ?");
                $stmt->execute([$values[$field]]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' already exists.';
                }
            }
            if ($values['email'] !== '') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
                $stmt->execute([$values['email']]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $errors[] = 'Email address already exists.';
                }
            }
        }

        // --- Insert ---
        if (empty($errors)) {
            try {
                $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Administrator' LIMIT 1");
                $roleStmt->execute();
                $roleId = $roleStmt->fetchColumn();

                $hash = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    'INSERT INTO employees
                        (employee_code, username, password_hash, first_name, last_name,
                         email, employment_status, date_hired, role_id)
                     VALUES (?, ?, ?, ?, ?, ?, \'active\', ?, ?)'
                );
                $insert->execute([
                    $values['employee_code'],
                    $values['username'],
                    $hash,
                    $values['first_name'],
                    $values['last_name'],
                    $values['email'] === '' ? null : $values['email'],
                    $values['date_hired'],
                    (int) $roleId,
                ]);

                flash('success', 'Administrator account created successfully.');
                flash('success', 'IMPORTANT: Delete setup.php from the server immediately, then log in.');
                redirect(url('login.php'));
            } catch (PDOException $e) {
                $errors[] = 'Could not create the account. Please check your database configuration.';
            }
        }
    }
}

if ($values['employee_code'] === '') {
    $values['employee_code'] = generate_employee_code($pdo);
}

require __DIR__ . '/includes/layout-header.php';
?>
<div class="form">
  <div class="page-header"><h1>First-time Setup</h1></div>

  <p>Create the first administrator account. There is no default password &mdash;
     you choose the credentials below. After setup, <strong>delete setup.php</strong>.</p>

  <?php foreach ($errors as $error): ?>
    <div class="flash error"><?= h($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="" novalidate>
    <?= csrf_field() ?>

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
      <small class="note">Used together with the password to log in.</small>
    </div>

    <div class="field">
      <label for="email">Email address (optional)</label>
      <input type="email" id="email" name="email"
             value="<?= h($values['email']) ?>" maxlength="100">
    </div>

    <div class="field">
      <label for="date_hired">Date hired</label>
      <input type="date" id="date_hired" name="date_hired"
             value="<?= h($values['date_hired']) ?>" required>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required minlength="8">
      <button type="button" class="toggle-password" data-target="password">Show</button>
      <small class="note">Minimum 8 characters. Stored as a cryptographic hash &mdash; never in plain text.</small>
    </div>

    <div class="field">
      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
    </div>

    <div class="actions">
      <button class="button button-primary" type="submit">Create administrator account</button>
    </div>
  </form>

  <p class="small"><a href="<?= url('login.php') ?>">&larr; Already have an account? Log in</a></p>
</div>
<?php require __DIR__ . '/includes/layout-footer.php';