<?php declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
app_start_session();

// Logout only accepts POST requests with a valid CSRF token.
// Links never log a user out, which prevents CSRF logout attacks.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    redirect(url('login.php'));
}

do_logout();

// The flash message survives logout via a URL parameter because
// session_destroy() prevents session data from being written.
redirect(url('login.php') . '?logged_out=1');