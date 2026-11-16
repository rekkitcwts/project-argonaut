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
 * Return the single open (time_out IS NULL) attendance record for an
 * employee, regardless of what calendar date it was created on. This is what
 * makes night shifts work: an employee who timed in at 23:48 on Nov 15 can
 * time out at 04:43 on Nov 16 by closing that same open record.
 *
 * Returns null when no open record exists.
 */
function attendance_get_open_record(PDO $pdo, int $employee_id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, employee_id, work_date, time_in, time_out
         FROM attendance
         WHERE employee_id = ? AND time_out IS NULL
         ORDER BY work_date DESC, time_in DESC
         LIMIT 1'
    );
    $stmt->execute([$employee_id]);
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
 * Return the difference (time_out - time_in) in seconds.
 * Handles cross-midnight shifts: if time_out is earlier than time_in (e.g.
 * time_in 23:48, time_out 04:43 the next day), 24 hours are added so the
 * duration is positive.
 */
function attendance_seconds_between(string $time_in, string $time_out): int
{
    $in = new DateTime('1970-01-01 ' . $time_in);
    $out = new DateTime('1970-01-01 ' . $time_out);
    $seconds = (int) ($out->getTimestamp() - $in->getTimestamp());
    if ($seconds < 0) {
        $seconds += 86400; // add 24 hours for cross-midnight shifts
    }
    return $seconds;
}