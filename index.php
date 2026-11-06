<?php declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
app_start_session();

// Public entry point: send authenticated users to their dashboard,
// and unauthenticated visitors to the login page.
if (!is_logged_in()) {
    redirect(url('login.php'));
}

if (is_admin()) {
    redirect(url('employees/index.php'));
}

redirect(url('profile.php'));