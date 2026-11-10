<?php
declare(strict_types=1);

/*
 * Attendance helpers (Phase 2).
 * Shared by the employee time-clock kiosk and the admin attendance view.
 */

if (!defined('APP_DB_HOST')) {
    http_response_code(500);
    exit('Application configuration is missing.');
}

/*
 * Return the open (time_out IS NULL) attendance record for an employee on a
 * given work date, or null when none exists. Enforces "one open record per
 * employee at a time" together with the unique (employee_id, work_date) key.
 */
function attendance_get_open(PDO $pdo, int $employee_id, string $work_date): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, employee_id, work_date, time_in, time_out
         FROM attendance
         WHERE employee_id = ? AND work_date = ? AND time_out IS NULL
         LIMIT 1'
    );
    $stmt->execute([$employee_id, $work_date]);
    $result = $stmt->fetch();
    return $result === false ? null : $result;
}

/*
 * Format a duration in seconds as "Xh Ym".
 */
function attendance_format_duration(int $seconds): string
{
    $seconds = max(0, (int) $seconds);
    $hours = (int) floor($seconds / 3600);
    $minutes = (int) floor(($seconds % 3600) / 60);
    return $hours . 'h ' . $minutes . 'm';
}

/*
 * Return the difference (time_out - time_in) in seconds for two HH:MM:SS
 * values on the same calendar day. May be negative if times are out of order.
 */
function attendance_seconds_between(string $time_in, string $time_out): int
{
    $in = new DateTime('1970-01-01 ' . $time_in);
    $out = new DateTime('1970-01-01 ' . $time_out);
    return (int) ($out->getTimestamp() - $in->getTimestamp());
}