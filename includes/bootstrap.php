<?php
declare(strict_types=1);

/*
 * Bootstrap: loads configuration, the database connection, and shared libraries.
 * Required by every public page of the application.
 */

if (!defined('APP_DB_HOST')) {
    $configFile = __DIR__ . '/../config/config.php';
    if (!file_exists($configFile)) {
        http_response_code(500);
        echo '<h1>Configuration missing</h1>'
            . '<p>Copy <code>config/config.php.example</code> to '
            . '<code>config/config.php</code> and edit your database credentials.</p>';
        exit;
    }
    require $configFile;
}

// Set the server timezone so all date()/time() values are stored in local
// time, not UTC. Falls back to the server default if APP_TIMEZONE is not set.
if (defined('APP_TIMEZONE')) {
    @date_default_timezone_set(APP_TIMEZONE);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';