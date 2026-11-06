<?php
declare(strict_types=1);

/*
 * Database connection using PDO.
 *
 * Shared-hosting compatible: requires only the PDO extension and the
 * pdo_mysql driver, both commonly available on PHP/MySQL shared hosting.
 * All queries in this application use PDO prepared statements, which
 * prevents SQL injection.
 */

if (!defined('APP_DB_HOST')) {
    http_response_code(500);
    exit('Application configuration is missing. Copy config/config.php.example to config/config.php and edit your credentials.');
}

try {
    $dsn = 'mysql:host=' . APP_DB_HOST . ';dbname=' . APP_DB_NAME . ';charset=' . APP_DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Disable emulated prepares so the database engine does the
        // parameter binding (real prepared statements).
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, APP_DB_USER, APP_DB_PASS, $options);
} catch (PDOException $e) {
    // Never show connection details or error messages to the browser.
    http_response_code(500);
    exit('Unable to connect to the database. Please check your database configuration.');
}