-- ============================================================================
-- Employee Management & Payroll System - Phase 2B
-- Timecard viewer (read + admin edit)
-- MySQL / MariaDB migration
--
-- For existing Phase 2 installs: import this file in phpMyAdmin after the
-- Phase 1 and Phase 2 schemas have been imported.
-- ============================================================================

USE employee_payroll;

-- ----------------------------------------------------------------------------
-- Add hours_worked and edit-audit columns to the attendance table
-- ----------------------------------------------------------------------------
ALTER TABLE attendance
    ADD COLUMN hours_worked DECIMAL(6,2) DEFAULT NULL AFTER time_out,
    ADD COLUMN edited_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
    ADD COLUMN edited_by INT UNSIGNED DEFAULT NULL AFTER edited_at,
    ADD COLUMN edit_reason VARCHAR(255) DEFAULT NULL AFTER edited_by,
    ADD CONSTRAINT fk_attendance_edited_by FOREIGN KEY (edited_by) REFERENCES employees (employee_id);