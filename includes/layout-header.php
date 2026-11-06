<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title ?? APP_NAME) ?> &middot; <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= url('assets/style.css') ?>">
</head>
<body>
<div class="wrapper">
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= url('index.php') ?>"><?= h(APP_NAME) ?></a>
    <nav class="topbar-nav">
      <?php if (is_logged_in()): ?>
        <a href="<?= url('index.php') ?>">Dashboard</a>
        <?php if (is_admin()): ?>
          <a href="<?= url('employees/index.php') ?>">Employees</a>
        <?php endif; ?>
        <a href="<?= url('profile.php') ?>">My Profile</a>
        <form action="<?= url('logout.php') ?>" method="post" style="display:inline">
          <?= csrf_field() ?>
          <button class="button button-secondary" type="submit">Log out</button>
        </form>
      <?php else: ?>
        <a href="<?= url('login.php') ?>">Log in</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="container">
<?= flash_display() ?>