<?php
declare(strict_types=1);

/*
 * Timecard helpers (Phase 2B).
 * Reusable cutoff logic and hours computation for admin and employee views.
 */

if (!defined('APP_DB_HOST')) {
    http_response_code(500);
    exit('Application configuration is missing.');
}

/*
 * Return the cutoff period for a given date.
 *
 * Cutoff A: 1st to 15th of the month.
 * Cutoff B: 16th to the last day of the month (handles 28/29/30/31).
 *
 * Returns: ['cutoff_key' => '202601A', 'label' => '2026-01 Cutoff A (1st–15th)',
 *           'start_date' => '2026-01-01', 'end_date' => '2026-01-15',
 *           'cutoff_type' => 'A']
 */
function timecard_get_cutoff(string $date): array
{
    $dt = new DateTime($date);
    $year = (int) $dt->format('Y');
    $month = (int) $dt->format('m');
    $day = (int) $dt->format('j');

    if ($day >= 1 && $day <= 15) {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = sprintf('%04d-%02d-15', $year, $month);
        $label = sprintf('%04d-%02d Cutoff A (1st–15th)', $year, $month);
        $cutoffType = 'A';
    } else {
        $start = sprintf('%04d-%02d-16', $year, $month);
        $lastDay = (int) (new DateTime(sprintf('%04d-%02d-01', $year, $month)))->modify('last day of this month')->format('d');
        $end = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
        $label = sprintf('%04d-%02d Cutoff B (16th–%d)', $year, $month, $lastDay);
        $cutoffType = 'B';
    }

    return [
        'cutoff_key'   => sprintf('%04d%02d%s', $year, $month, $cutoffType),
        'label'        => $label,
        'start_date'   => $start,
        'end_date'     => $end,
        'cutoff_type'  => $cutoffType,
    ];
}

/*
 * List available cutoffs going back $months_back months, newest first.
 * Used to populate the admin and employee cutoff dropdowns.
 */
function timecard_list_cutoffs(int $months_back = 12): array
{
    $cutoffs = [];
    $today = new DateTime();

    for ($i = $months_back; $i >= 0; $i--) {
        $dt = clone $today;
        $dt->modify("-$i months");
        $year = (int) $dt->format('Y');
        $month = (int) $dt->format('m');

        // Cutoff A
        $cutoffs[] = [
            'cutoff_key'  => sprintf('%04d%02dA', $year, $month),
            'label'       => sprintf('%04d-%02d Cutoff A (1st–15th)', $year, $month),
            'start_date'  => sprintf('%04d-%02d-01', $year, $month),
            'end_date'    => sprintf('%04d-%02d-15', $year, $month),
            'cutoff_type' => 'A',
        ];

        // Cutoff B
        $lastDay = (int) (new DateTime(sprintf('%04d-%02d-01', $year, $month)))->modify('last day of this month')->format('d');
        $cutoffs[] = [
            'cutoff_key'  => sprintf('%04d%02dB', $year, $month),
            'label'       => sprintf('%04d-%02d Cutoff B (16th–%d)', $year, $month, $lastDay),
            'start_date'  => sprintf('%04d-%02d-16', $year, $month),
            'end_date'    => sprintf('%04d-%02d-%02d', $year, $month, $lastDay),
            'cutoff_type' => 'B',
        ];
    }

    return $cutoffs;
}

/*
 * Parse a cutoff key (e.g. '202601A') into start_date and end_date.
 * Returns null if the key is malformed.
 */
function timecard_parse_cutoff_key(string $cutoffKey): ?array
{
    if (!preg_match('/^(\d{4})(\d{2})([AB])$/', $cutoffKey, $m)) {
        return null;
    }
    $year  = (int) $m[1];
    $month = (int) $m[2];
    $type  = $m[3];

    if ($type === 'A') {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = sprintf('%04d-%02d-15', $year, $month);
        $label = sprintf('%04d-%02d Cutoff A (1st–15th)', $year, $month);
    } else {
        $start = sprintf('%04d-%02d-16', $year, $month);
        $lastDay = (int) (new DateTime(sprintf('%04d-%02d-01', $year, $month)))->modify('last day of this month')->format('d');
        $end   = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
        $label = sprintf('%04d-%02d Cutoff B (16th–%d)', $year, $month, $lastDay);
    }

    return [
        'cutoff_key'  => $cutoffKey,
        'label'       => $label,
        'start_date'  => $start,
        'end_date'    => $end,
        'cutoff_type' => $type,
    ];
}

/*
 * Compute hours worked between two TIME values.
 * Returns null if either time is missing (open/incomplete record).
 * Returns 0 if time_out is earlier than time_in (defensive).
 */
function timecard_compute_hours(?string $timeIn, ?string $timeOut): ?float
{
    if ($timeIn === null || $timeOut === null || $timeIn === '' || $timeOut === '') {
        return null;
    }
    $in  = new DateTime('1970-01-01 ' . $timeIn);
    $out = new DateTime('1970-01-01 ' . $timeOut);
    $seconds = (int) ($out->getTimestamp() - $in->getTimestamp());
    if ($seconds < 0) {
        $seconds = 0;
    }
    return round($seconds / 3600, 2);
}

/*
 * Format hours for display. Returns '—' when null (incomplete record).
 */
function timecard_format_hours(?float $hours): string
{
    if ($hours === null) {
        return '—';
    }
    return number_format($hours, 2) . ' h';
}

/*
 * Resolve the stored hours_worked value for a row, computing it on the fly
 * when the column is NULL (legacy Phase 2 records created before Phase 2B).
 */
function timecard_resolve_hours(array $row): ?float
{
    if (!empty($row['hours_worked'])) {
        return (float) $row['hours_worked'];
    }
    if ($row['time_out'] !== null && $row['time_out'] !== '' && $row['time_in'] !== null && $row['time_in'] !== '') {
        return timecard_compute_hours($row['time_in'], $row['time_out']);
    }
    return null;
}