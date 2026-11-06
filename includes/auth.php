<?php
declare(strict_types=1);

/*
 * Authentication and authorization.
 *
 * - Secure password storage with password_hash() / password_verify()
 * - Session-based authentication with session ID regeneration on login
 * - Basic brute-force protection via failed-attempt tracking (IP-based)
 * - Role-based access control (Administrator / Employee)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/*
 * Start the session with the most secure settings available on shared hosting.
 * Settings that the hosting environment does not allow are ignored silently.
 */
function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $settings = [
        'session.use_strict_mode'  => '1',
        'session.use_only_cookies' => '1',
        'session.cookie_httponly'  => '1',   // cookie not readable by JavaScript
        'session.cookie_samesite'  => 'Lax', // limits cross-site request inclusion
    ];
    foreach ($settings as $name => $value) {
        @ini_set($name, $value);
    }

    // Only mark the session cookie secure when the site is actually served over HTTPS.
    if (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        @ini_set('session.cookie_secure', '1');
    }

    // Scope the cookie to the application folder when installed in a subdirectory.
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $cookiePath = dirname($scriptName);
    if ($cookiePath !== '/' && $cookiePath !== '') {
        @ini_set('session.cookie_path', $cookiePath);
    }

    @ini_set('session.name', APP_SESSION_NAME ?? 'emp_sys_session');
    session_start();
}

/*
 * Return true when a user is logged in.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/*
 * Return the logged-in user record, or null.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/*
 * Return true when the logged-in user is an Administrator.
 */
function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role_name'] === 'Administrator';
}

/*
 * Require a logged-in user; redirect to login otherwise.
 */
function require_auth(): void
{
    app_start_session();
    if (!is_logged_in()) {
        flash('error', 'You must be logged in to access that page.');
        redirect(url('login.php'));
    }
}

/*
 * Require a specific role; redirect with an error otherwise.
 */
function require_role(string $role): void
{
    require_auth();
    $user = current_user();
    if ($user === null || $user['role_name'] !== $role) {
        flash('error', 'You do not have permission to access that page.');
        redirect(url('index.php'));
    }
}

/*
 * Check whether the client IP is currently locked out because of too many
 * failed login attempts within the lockout window.
 */
function is_locked_out(): bool
{
    global $pdo;

    $max = (int) (APP_LOGIN_MAX_ATTEMPTS ?? 5);
    $window = date('Y-m-d H:i:s', strtotime('-' . (APP_LOGIN_LOCKOUT_MINUTES ?? 30) . ' minutes'));

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE success = 0 AND ip_address = ? AND attempted_at >= ?'
        );
        $stmt->execute([client_ip(), $window]);
        return (int) $stmt->fetchColumn() >= $max;
    } catch (PDOException $e) {
        // If the database is unavailable, fail closed (treat as locked out).
        return true;
    }
}

/*
 * Authenticate a user by username or employee code plus password.
 *
 * Returns the employee row (with role_name) on success, or null on failure.
 * Failure reasons (wrong password, unknown account, inactive account, lockout)
 * all produce the same result so no account-existence information leaks.
 */
function authenticate(string $identifier, string $password): ?array
{
    global $pdo;

    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        record_login_attempt($identifier, false);
        return null;
    }

    if (is_locked_out()) {
        record_login_attempt($identifier, false);
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT e.*, r.role_name
             FROM employees e
             JOIN roles r ON e.role_id = r.role_id
             WHERE (e.username = ? OR e.employee_code = ?)
               AND e.employment_status = \'active\'
             LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        record_login_attempt($identifier, false);
        return null;
    }

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($identifier, false);
        return null;
    }

    record_login_attempt($identifier, true);
    return $user;
}

/*
 * Record a login attempt and purge old records so the table stays small.
 */
function record_login_attempt(string $identifier, bool $success): void
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO login_attempts (identifier, ip_address, success)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$identifier, client_ip(), $success ? 1 : 0]);

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . (APP_ATTEMPT_RETENTION_HOURS ?? 24) . ' hours'));
        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
            ->execute([$cutoff]);
    } catch (PDOException $e) {
        // Best-effort auditing; must not block legitimate logins.
    }
}

/*
 * Complete a successful login: regenerate the session ID and store user data.
 */
function do_login(array $user): void
{
    app_start_session();
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['employee_id'];
    $_SESSION['user'] = [
        'employee_id'        => (int) $user['employee_id'],
        'employee_code'      => $user['employee_code'],
        'username'           => $user['username'],
        'first_name'         => $user['first_name'],
        'last_name'          => $user['last_name'],
        'email'              => $user['email'],
        'role_id'            => (int) $user['role_id'],
        'role_name'          => $user['role_name'],
        'employment_status'  => $user['employment_status'],
        'login_time'         => time(),
    ];
    csrf_regenerate();
}

/*
 * Log out: clear session data, delete the session cookie, destroy the session.
 */
function do_logout(): void
{
    app_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'] ?? false,
            true // httponly
        );
    }
    session_destroy();
}