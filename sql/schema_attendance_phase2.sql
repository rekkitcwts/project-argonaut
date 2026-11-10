-- ============================================================================
-- Employee Management & Payroll System - Phase 2
-- Attendance and working hours (time-in / time-out)
-- MySQL / MariaDB migration
--
-- For existing Phase 1 installs: import this file in phpMyAdmin after the
-- Phase 1 schema has been imported.
-- ============================================================================

USE employee_payroll;

-- ----------------------------------------------------------------------------
-- Attendance (time-in / time-out records)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    time_in TIME DEFAULT NULL,
    time_out TIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_attendance_employee_date (employee_id, work_date),
    KEY idx_attendance_employee (employee_id),
    KEY idx_attendance_date (work_date),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees (employee_id)
) ENGINE=InnoDB;