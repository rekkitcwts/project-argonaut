<?php declare(strict_types=1);

$page_title = 'Log in';
require __DIR__ . '/includes/bootstrap.php';
app_start_session();

if (is_logged_in()) {
    redirect(url('index.php'));
}

$error = null;

if (isset($_GET['logged_out'])) {
    flash('success', 'You have been logged out.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Invalid request. Please resubmit the form.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = authenticate($identifier, $password);
        if ($user !== null) {
            do_login($user);
            redirect(url('index.php'));
        }

        // Generic message: the same text is shown whether the account
        // exists, the password is wrong, or the IP is locked out.
        $error = 'Invalid username or password.';
    }
}

require __DIR__ . '/includes/layout-header.php';
?>
<div class="form">
  <div class="page-header"><h1>Log in</h1></div>

  <?php if ($error !== null): ?>
    <div class="flash error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" action="" novalidate>
    <?= csrf_field() ?>

    <div class="field">
      <label for="identifier">Username or Employee ID</label>
      <input type="text" id="identifier" name="identifier"
             placeholder="e.g. jdoe or EMP-0001" required autocomplete="username">
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             required autocomplete="current-password">
      <button type="button" class="toggle-password" data-target="password">Show</button>
    </div>

    <div class="actions">
      <button class="button button-primary" type="submit">Log in</button>
    </div>
  </form>

  <p class="small">Employee accounts are created by your administrator. If you do not have
     an account yet, please ask your administrator to create one for you.</p>
</div>
<?php require __DIR__ . '/includes/layout-footer.php';