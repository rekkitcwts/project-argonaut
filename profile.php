<?php declare(strict_types=1);

$page_title = 'My Profile';
require __DIR__ . '/includes/bootstrap.php';
app_start_session();
require_auth();

// Every logged-in user views their own profile through the shared view page.
redirect(url('employees/view.php?id=' . (int) $_SESSION['user_id']));