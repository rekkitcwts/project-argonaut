-- ============================================================================
-- Employee Management & Payroll System - Phase 1
-- MySQL / MariaDB schema
--
-- Import instructions:
--   1. Create the database in phpMyAdmin (or the hosting control panel).
--   2. Select the database, then open the Import tab.
--   3. Upload this file and click Go.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS employee_payroll DEFAULT CHARACTER SET utf8mb4;
USE employee_payroll;

-- ----------------------------------------------------------------------------
-- Roles
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    role_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL,
    role_description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id),
    UNIQUE KEY uk_role_name (role_name)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Employees (records + login accounts; no payroll/salary data in Phase 1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
    employee_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_code VARCHAR(20) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    contact_number VARCHAR(30) DEFAULT NULL,
    department VARCHAR(50) DEFAULT NULL,
    job_title VARCHAR(50) DEFAULT NULL,
    employment_status ENUM('active','inactive','on_leave','terminated') NOT NULL DEFAULT 'active',
    date_hired DATE DEFAULT NULL,
    role_id INT UNSIGNED NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (employee_id),
    UNIQUE KEY uk_employee_code (employee_code),
    UNIQUE KEY uk_username (username),
    KEY idx_employees_email (email),
    KEY idx_employees_role (role_id),
    KEY idx_employees_status (employment_status),
    CONSTRAINT fk_employees_role FOREIGN KEY (role_id) REFERENCES roles (role_id),
    CONSTRAINT fk_employees_created_by FOREIGN KEY (created_by) REFERENCES employees (employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_updated_by FOREIGN KEY (updated_by) REFERENCES employees (employee_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Login attempt tracking (basic brute-force / rate-limiting data)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attempt_id),
    KEY idx_login_attempts_identifier (identifier),
    KEY idx_login_attempts_ip (ip_address),
    KEY idx_login_attempts_time (attempted_at)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Seed roles (no default accounts or passwords are created here)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO roles (role_name, role_description) VALUES
    ('Administrator', 'Full access to manage employee records and accounts'),
    ('Employee', 'Can log in and view their own profile only');

-- ============================================================================
-- Phase 2: Attendance and working hours
-- ============================================================================
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