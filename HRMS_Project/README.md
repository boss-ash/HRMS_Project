# HRMS – Human Resource Management System

A basic HRMS built with **PHP**, **MySQL**, and **Tailwind CSS**. It includes an Admin Dashboard, Employee Management (CRUD), Login, and **security patches** (BCrypt, rate-limiting, RBAC, CSRF, XSS protection).

## Features

- **Login** – Session-based auth, BCrypt, **Google Authenticator (2FA)** — after user/pass, enter 6-digit code from app
- **Forgot password** – Staff uses “Forgot password?” on login → enters username → request goes to Admin. Admin sees **Password reset requests** (Dashboard quick link), sets a new password for the staff; staff signs in with the new password.
- **2FA** – First-time users set up via QR code (Google Authenticator or compatible app); then verify code each login
- **Login rate-limiting** – Prepared statements, brute-force protection (3 attempts → 20s lock)
- **Admin Dashboard** – Overview with total/active employees and department breakdown
- **Employee Management** – Full CRUD (Admin only); prepared statements, input sanitization, **CSRF tokens**
- **My Profile** – Staff role can only view their own linked employee profile (read-only)
- **Staff modules** – **Leave** (apply, balance, history), **Payslips** (view own), **Attendance** (view time in/out), **Announcements** (company news). Staff nav and dashboard quick links for all.
- **Admin modules** – **Leave** (approve/reject requests), **Payslips** (add and list), **Attendance** (add and list by month), **Announcements** (create, publish/unpublish, delete). Admin nav and dashboard quick links for all.
- **RBAC** – Admin: Employees, Leave, Payslips, Attendance, Announcements, Activity Logs. Staff: Dashboard, My Profile, Leave, Payslips, Attendance, Announcements; cannot manage other employees.
- **UI** – Tailwind CSS, responsive layout, Inter font

## Security (Patches Applied)

- **Passwords:** BCrypt via PHP `password_verify()` / `password_hash()`
- **SQL Injection:** Prepared statements on all queries
- **Brute-force:** Login rate-limiting (3 attempts → 20s lock); old `login_attempts` rows auto-deleted after 1 day
- **RBAC:** Admin only: Employees, Activity Logs; Staff: own profile only
- **XSS:** Output escaped; inputs sanitized; CSP header
- **CSRF:** Token on all state-changing forms; validated on POST
- **Session:** Regenerate ID on login; cookie HttpOnly, SameSite=Lax; **idle timeout 30 min** (auto logout)
- **Headers:** X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, **Content-Security-Policy**, **HSTS** when HTTPS
- **Database:** Optional limited user `hrms_app` (SELECT, INSERT, UPDATE, DELETE only) via `database/create_limited_user.sql`
- **Paths:** `config/` and `database/` protected by `.htaccess` (deny direct access)

**Full list:** See **SECURITY_PATCHES.md** for every patch (system + database) with file locations and table details.

## Requirements

- PHP 7.4+ (XAMPP recommended)
- MySQL 5.7+ / MariaDB
- Web server (Apache with XAMPP)

## Setup

1. **Start XAMPP**  
   Start Apache and MySQL.

2. **Create the database**  
   - phpMyAdmin: `http://localhost/phpmyadmin` → Import `database/schema.sql`  
   - Or: `mysql -u root -p < database/schema.sql`  
   - Existing DB: run `database/migrate_security.sql`, `database/migrate_2fa.sql`, `database/migrate_activity_logs.sql`, **`database/migrate_staff_modules.sql`**, and **`database/migrate_password_reset_requests.sql`** for staff modules and forgot-password flow.

3. **Configure**  
   Copy `config/database.example.php` to `config/database.php` and set `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`.  
   **Production:** Create limited DB user: run `database/create_limited_user.sql` as root (set a strong password), then use `hrms_app` / that password in `config/database.php`.

4. **Run**  
   `http://localhost/HRMS_Project/` or `http://localhost/HRMS_Project/login.php`

5. **Default login**  
   - **Admin:** Username `admin`, Password `password`  
   - **Staff:** Username `staff`, Password `password` (nakalink sa employee na John Doe / EMP001; may access sa Dashboard, My Profile, Leave, Payslips, Attendance, Announcements)  
   After correct password you set up **2FA**: scan QR with Google Authenticator (or similar app), then enter the 6-digit code. Next logins: user/pass then 6-digit code. Change passwords in production.

## Project structure

```
HRMS_Project/
├── config/
│   └── database.php
├── database/
│   ├── schema.sql                 # Full schema + security tables
│   ├── migrate_security.sql       # Add security to existing DB
│   ├── migrate_staff_modules.sql  # Leave, Payslips, Attendance, Announcements
│   └── migrate_password_reset_requests.sql  # Forgot password (staff request → admin sets)
├── includes/
│   ├── auth.php             # Session, RBAC, CSRF helpers
│   ├── header.php           # HTML head, Tailwind, security headers
│   ├── nav.php              # Nav (Admin: Employees; Staff: My Profile)
│   ├── rate_limit.php       # Login rate-limiting
│   ├── sanitize.php         # Input sanitization (XSS, length)
│   ├── totp.php             # Google Authenticator (TOTP) – pure PHP
│   └── activity_log.php     # Activity logging (login, logout, CRUD)
├── index.php
├── login.php
├── forgot_password.php      # Staff: request password reset (no login)
├── verify_2fa.php           # Enter 6-digit code after password
├── setup_2fa.php            # First-time: QR code + verify code
├── activity_logs.php        # Admin: view activity logs (scrollable list)
├── admin_leave.php          # Admin: approve/reject leave requests
├── admin_payslips.php       # Admin: add payslips, list all
├── admin_attendance.php     # Admin: add attendance, list by month
├── admin_announcements.php  # Admin: create/publish/delete announcements
├── admin_password_requests.php  # Admin: view requests, set new password for staff
├── logout.php
├── dashboard.php
├── employees.php            # Admin only; CRUD + CSRF
├── profile.php              # Staff own profile (read-only)
├── profile_edit.php         # Staff: edit own phone
├── staff_leave.php          # Staff: apply leave, balance, history
├── staff_payslips.php       # Staff: view own payslips
├── staff_attendance.php     # Staff: view own attendance
├── staff_announcements.php  # Staff: company announcements
└── README.md
```

## Pages

| Page           | URL                     | Access               |
|----------------|-------------------------|----------------------|
| Login          | `/login.php`            | Public               |
| Dashboard      | `/dashboard.php`       | Logged-in            |
| Employees       | `/employees.php`         | **Admin only**       |
| Leave (admin)   | `/admin_leave.php`       | **Admin only**       |
| Payslips (admin)| `/admin_payslips.php`    | **Admin only**       |
| Attendance (admin) | `/admin_attendance.php` | **Admin only**   |
| Announcements (admin) | `/admin_announcements.php` | **Admin only** |
| Password reset requests | `/admin_password_requests.php` | **Admin only** |
| Activity Logs  | `/activity_logs.php`    | **Admin only**       |
| My Profile     | `/profile.php`         | **Staff (own only)** |
| My Leave       | `/staff_leave.php`      | **Staff (own only)** |
| My Payslips    | `/staff_payslips.php`   | **Staff (own only)** |
| My Attendance  | `/staff_attendance.php` | **Staff (own only)** |
| Announcements  | `/staff_announcements.php` | Logged-in         |

## Tech stack

- **Backend:** PHP (MySQLi)
- **Database:** MySQL
- **Frontend:** HTML, Tailwind CSS (CDN), minimal JS
- **Auth:** Session-based; BCrypt; RBAC (admin / staff)
