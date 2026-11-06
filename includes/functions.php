<?php
declare(strict_types=1);

/*
 * Shared helper functions: output escaping, CSRF protection, flash
 * messages, redirects, and other small utilities used by every page.
 */

if (!defined('APP_DB_HOST')) {
    http_response_code(500);
    exit('Application configuration is missing.');
}

/*
 * Escape a value for safe output in HTML.
 * Prevents cross-site scripting (XSS) from user-provided data.
 */
function h(?string $value, bool $double_encode = false): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $double_encode);
}

/*
 * Build an absolute URL from a path using APP_BASE_URL.
 */
function url(string $path): string
{
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : '';
    $base = rtrim((string) $base, '/');
    return $base . '/' . ltrim($path, '/');
}

/*
 * Generate a CSRF token stored in the session.
 * Tokens are regenerated after every successful login.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = random_token();
    }
    return $_SESSION['csrf_token'];
}

/*
 * Render a hidden CSRF token input for forms.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/*
 * Validate the CSRF token submitted with a POST request.
 * Returns true only when the token matches the session token.
 */
function csrf_valid(): bool
{
    $provided = $_POST['csrf_token'] ?? '';
    if (!is_string($provided) || $provided === '') {
        return false;
    }
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($expected === '') {
        return false;
    }
    return hash_equals($expected, $provided);
}

/*
 * Regenerate the CSRF token (called after login).
 */
function csrf_regenerate(): void
{
    $_SESSION['csrf_token'] = random_token();
}

/*
 * Store a flash message shown once on the next page load.
 * $key is used as the CSS class (e.g. 'success' or 'error').
 */
function flash(string $key, string $message): void
{
    $_SESSION['flash'][$key][] = $message;
}

/*
 * Return and remove flash messages for a key.
 */
function flash_get(string $key): array
{
    $messages = $_SESSION['flash'][$key] ?? [];
    unset($_SESSION['flash'][$key]);
    return (array) $messages;
}

/*
 * Render and consume all pending flash messages as HTML.
 */
function flash_display(): string
{
    $output = '';
    foreach ($_SESSION['flash'] ?? [] as $key => $messages) {
        foreach ((array) $messages as $message) {
            $cssClass = 'flash ' . h((string) $key);
            $output .= '<div class="' . $cssClass . '">' . h($message) . '</div>';
        }
    }
    unset($_SESSION['flash']);
    return $output;
}

/*
 * Send a redirect and stop execution. Falls back to a meta refresh/JavaScript
 * redirect if headers have already been sent.
 */
function redirect(string $url): void
{
    if (headers_sent()) {
        echo '<meta http-equiv="refresh" content="0;url=' . h($url, true) . '">';
        echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
        exit;
    }
    header('Location: ' . $url, true, 302);
    exit;
}

/*
 * Return the client IP address.
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/*
 * Generate the next employee code (EMP-0001, EMP-0002, ...).
 * Used as a default value for the employee creation form.
 */
function generate_employee_code(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT MAX(employee_code) AS max_code FROM employees");
    $row = $stmt->fetch();
    $max = $row['max_code'] ?? null;
    $number = 1;
    if ($max !== null && preg_match('/^EMP-(\\d+)$/', (string) $max, $matches)) {
        $number = (int) $matches[1] + 1;
    }
    return 'EMP-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
}

/*
 * Generate a random hex token (CSRF tokens, session regeneration).
 * Falls back gracefully on older PHP builds without random_bytes().
 */
function random_token(): string
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(32));
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes(32);
        if ($bytes !== false) {
            return bin2hex($bytes);
        }
    }
    return sha1(uniqid(mt_rand(), true));
}

/*
 * Basic email validation wrapper.
 */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}