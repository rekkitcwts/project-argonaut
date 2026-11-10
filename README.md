# Employee Management & Payroll System - Phase 1

A secure, dependency-free employee records and authentication system built for
standard PHP/MySQL shared hosting (e.g. InfinityFree). Phase 1 covers employee
records, login authentication, and role-based access control. **Payroll
calculations, payslips, attendance, deductions, and financial reports are
intentionally not included** and will be added in later phases.

## Technology

- Backend: PHP 7.4+ (works on standard shared hosting; no framework, no Composer,
  no npm/Node.js, no shell access required)
- Database: MySQL or MariaDB (setup via phpMyAdmin)
- Frontend: HTML5, CSS3, vanilla JavaScript (progressive enhancement; the app
  works without JavaScript)
- Sessions: PHP native sessions with secure cookie settings
- Passwords: `password_hash()` / `password_verify()` (bcrypt)
- Database access: PDO with real prepared statements

## Features in Phase 1

- Employee login with username **or** employee ID and password
- Secure password storage (never plaintext) and generic login error messages
- Session-based authentication with session ID regeneration after login
- Logout that fully destroys the session (POST + CSRF protected)
- Basic brute-force protection: failed login attempts are tracked per IP
  address; repeated failures within a window cause a temporary lockout
- Employee records: create, list, view details, edit, deactivate/reactivate
  (soft delete - records are never removed)
- Unique employee IDs (e.g. EMP-0001)
- Two roles: **Administrator** (manage employee records) and **Employee**
  (log in and view their own profile only)
- CSRF protection on all forms that modify data
- XSS output escaping on every rendered value
- PDO prepared statements everywhere (no SQL injection)
- No database credentials in publicly accessible files

## File structure

```
employee-payroll/
├── .htaccess                  # Disables directory listings
├── index.php                  # Entry point: dashboard or login redirect
├── login.php                  # Login page
├── logout.php                 # Logout (POST, CSRF protected)
├── setup.php                  # One-time administrator setup (DELETE AFTER USE)
├── profile.php                # Redirect to own profile
├── config/
│   ├── .htaccess              # Blocks direct browser access
│   ├── config.php.example     # Copy to config.php and edit credentials
│   └── config.php             # Created by you; contains DB credentials (gitignored)
├── includes/
│   ├── .htaccess              # Blocks direct browser access
│   ├── bootstrap.php          # Loads config, database, and libraries
│   ├── db.php                 # PDO database connection
│   ├── functions.php          # Escaping, CSRF, flash messages, helpers
│   ├── auth.php               # Authentication, sessions, authorization
│   ├── layout-header.php      # Shared page header / navigation
│   └── layout-footer.php      # Shared page footer
├── employees/
│   ├── index.php              # Employee list (search, filter, admin only)
│   ├── view.php               # Employee details
│   ├── create.php             # Create employee (admin only)
│   ├── edit.php               # Edit employee (admin only)
│   └── deactivate.php         # Deactivate/reactivate (admin only, POST)
├── assets/
│   ├── style.css
│   └── main.js
├── sql/
│   ├── .htaccess              # Blocks direct browser access
│   └── schema.sql             # Database schema to import via phpMyAdmin
└── README.md
```

## Installation

### Step 1: Create the database

1. Log in to your hosting control panel (on InfinityFree: the Client Area).
2. Create a new MySQL database. Note the **database name**, **database user
   name**, **database user password**, and **database host name**
   (InfinityFree shows the SQL server hostname, e.g. `sql123.infinityfree.com`).
3. Open **phpMyAdmin** (linked from the control panel) and select the new
   database.

### Step 2: Import the schema

1. In phpMyAdmin, select your database on the left.
2. Click the **Import** tab.
3. Choose `sql/schema.sql` from this project and click **Go**.
4. This creates the `roles`, `employees`, and `login_attempts` tables and seeds
   the two roles (`Administrator` and `Employee`). No accounts or passwords are
   created by the schema.

### Step 3: Configure database credentials

1. Make a copy of the example configuration:

   ```
   cp config/config.php.example config/config.php
   ```

   (On InfinityFree File Manager: duplicate `config/config.php.example` and
   rename the copy to `config/config.php`.)

2. Edit `config/config.php` and enter:

   - `APP_DB_HOST` — database host (from Step 1)
   - `APP_DB_NAME` — database name
   - `APP_DB_USER` — database user
   - `APP_DB_PASS` — database user password
   - `APP_BASE_URL` — the full URL where the app lives, with trailing slash,
     e.g. `https://example.com/` or `https://example.com/empsys/`

`config.php` is gitignored and blocked from direct browser access by
`config/.htaccess`.

### Step 4: Create the initial administrator account

There is **no hardcoded default password** in this system.

1. Upload all project files to your hosting account (see Step 5).
2. Open `setup.php` in your browser (e.g. `https://example.com/setup.php`).
3. Enter the administrator details (employee ID, name, username, email, hire
   date, password) and submit.
4. The administrator account is created with the `Administrator` role.
5. **Immediately delete `setup.php` from the server.** The script refuses to
   run once an administrator account exists, but you must still remove the file.

### Step 5: Upload to InfinityFree (or any shared host)

Using the InfinityFree File Manager or an FTP client:

1. Upload the contents of this project to `htdocs/` (for the primary domain) or
   to a subfolder of `htdocs/` (e.g. `htdocs/empsys/`). If you use a subfolder,
   set `APP_BASE_URL` in `config.php` accordingly (e.g.
   `https://example.com/empsys/`).
2. Ensure `.htaccess` files were uploaded (FTP clients sometimes hide dotfiles).
3. Recommended file permissions: `644` for files, `755` for directories.
4. No Composer, npm, or command-line steps are needed on the server.

### Step 6: Log in

1. Open your site URL.
2. Log in with the administrator username/employee ID and password created in
   Step 4.
3. Administrators see the Employees section (create, view, edit, deactivate).
4. Employees see only their own profile.

## Security design

| Threat | Protection |
|--------|------------|
| SQL injection | PDO with real prepared statements (`ATTR_EMULATE_PREPARES => false`) on every query |
| Stolen passwords | `password_hash()` (bcrypt); passwords are never stored in plain text |
| Account enumeration | Generic login error message regardless of failure reason |
| Brute-force login | Failed attempts tracked per IP address; temporary lockout after repeated failures |
| Session hijacking | `session_regenerate_id(true)` after login; `HttpOnly`, `SameSite=Lax`, and `Secure` (HTTPS only) cookies; strict-mode, cookies-only sessions |
| CSRF | Unique per-session token validated on every state-changing form |
| XSS | Every user-provided value escaped with `htmlspecialchars()` on output |
| Credential exposure | `config.php` blocked from browser access by `.htaccess`; never committed |
| Data deletion | Deactivation sets `employment_status = 'inactive'`; records are kept |
| Privilege escalation | Role checks on every restricted page; employees cannot view or edit other employees' records, even via URL parameters |

## Hosting notes (InfinityFree / free shared hosting)

- No SSH, terminal, Composer, or npm is required or used.
- The application is a collection of ordinary PHP files; move it between hosts
  by copying files and importing `sql/schema.sql`.
- `.htaccess` is supported, so directory listings are disabled and sensitive
  directories (`config/`, `includes/`, `sql/`) deny direct browser access.
- If your host does not allow changing some session INI settings, the code
  ignores those errors and keeps working with the server defaults.
- Use HTTPS where available (InfinityFree provides free SSL) so the `Secure`
  cookie flag activates.
- Free hosts sometimes suspend accounts for inactivity or resource limits;
  keep regular backups of your database and files.

## Manual testing checklist

Before relying on the system, verify each of the following:

1. Login works with both the username and the employee ID.
2. Wrong password shows "Invalid username or password" (same message for
   unknown usernames).
3. After repeated failed attempts from one browser/IP, login is temporarily blocked.
4. Logging in regenerates the session (session cookie changes).
5. Logging out destroys the session; the back button does not restore access.
6. An Employee cannot open `employees/create.php`, `employees/edit.php`,
   `employees/index.php`, or `employees/deactivate.php` directly.
7. An Employee cannot view another employee's record via
   `employees/view.php?id=<other id>`.
8. Deactivating an employee sets status to "inactive" and the record remains;
   the employee can no longer log in.
9. Flash messages (success/error) appear after create, edit, and deactivate.
10. `config.php` cannot be downloaded by visiting its URL directly.

## What comes next (Phase 2+)

The following are explicitly out of scope for Phase 1:

- Payroll calculations, payslips, and payment processing
- Salary, bank account, tax, and deduction storage
- Financial reports

These will be added in later phases only after this foundation is stable.

## Phase 2: Attendance and Working Hours

Phase 2 adds employee time-in / time-out tracking.

### New database table

`attendance` — one row per employee per work date:

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED PK | Auto-increment |
| employee_id | INT UNSIGNED FK | References employees.employee_id |
| work_date | DATE | Calendar date of the shift |
| time_in | TIME | Clock-in time |
| time_out | TIME | NULL until the employee clocks out |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Auto-updated on change |

A unique key on `(employee_id, work_date)` prevents duplicate same-day entries.

### Deployment

- Fresh installs: `sql/schema.sql` now includes the `attendance` table.
- Existing Phase 1 installs: import `sql/schema_attendance_phase2.sql` in phpMyAdmin.

### How it works

**Employee time clock (`attendance.php`)**

- A single input where the employee types their Employee ID (e.g. `EMP-0001`).
- The server looks up the employee. Unknown or inactive IDs are rejected.
- If the employee has no open time-in record for today, submitting records a time-in.
- If they have an open time-in record, submitting records a time-out and computes the hours worked.
- Confirmation messages: `"Timed in at 8:03 AM."` / `"Timed out at 5:12 PM — 9h 9m recorded."`
- Errors: unknown/inactive ID, already timed in, no open record to time out from.

**Admin view (`admin/attendance.php`)**

- Administrators see a read-only list of attendance for a chosen date (defaults to today), showing employee, time in, time out, hours worked, and when the record was created.
- A date filter lets you view any day.

### Notes

- No login is required to use the time clock; the employee identifies themselves by typing their Employee ID. Place the kiosk accordingly.
- `hours_worked` is computed on-the-fly from `time_in`/`time_out` and is not stored as a column.
- No payroll, salary, overtime, shift, or leave logic is included.

## License

This project is provided as-is for educational and self-hosted use. Review the
security notes above before deploying in any production environment.