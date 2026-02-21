# HRMS – Human Resource Management System

A basic HRMS built with **PHP**, **MySQL**, and **Tailwind CSS**. It includes an Admin Dashboard, Employee Management (CRUD), Login, and **security patches** (BCrypt, rate-limiting, RBAC, CSRF, XSS protection).

## Features

- **Login** – Session-based auth, BCrypt, **Google Authenticator (2FA)** — after user/pass, enter 6-digit code from app
- **2FA** – First-time users set up via QR code (Google Authenticator or compatible app); then verify code each login
- **Login rate-limiting** – Prepared statements, brute-force protection (3 attempts → 20s lock)
- **Admin Dashboard** – Overview with total/active employees and department breakdown
- **Employee Management** – Full CRUD (Admin only); prepared statements, input sanitization, **CSRF tokens**
- **My Profile** – Staff role can only view their own linked employee profile (read-only)
- **RBAC** – Admin: full access. Staff: dashboard + own profile only; cannot delete or manage other employees.
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
   - Existing DB: run `database/migrate_security.sql`, `database/migrate_2fa.sql`, and `database/migrate_activity_logs.sql` for activity logs table.

3. **Configure**  
   Copy `config/database.example.php` to `config/database.php` and set `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`.  
   **Production:** Create limited DB user: run `database/create_limited_user.sql` as root (set a strong password), then use `hrms_app` / that password in `config/database.php`.

4. **Run**  
   `http://localhost/HRMS_Project/` or `http://localhost/HRMS_Project/login.php`

5. **Default login**  
   - **Admin:** Username `admin`, Password `password`  
   - **Staff:** Username `staff`, Password `password` (nakalink sa employee na John Doe / EMP001; puwede lang mag-view ng “My Profile”)  
   After correct password you set up **2FA**: scan QR with Google Authenticator (or similar app), then enter the 6-digit code. Next logins: user/pass then 6-digit code. Change passwords in production.

## Project structure

```
HRMS_Project/
├── config/
│   └── database.php
├── database/
│   ├── schema.sql           # Full schema + security tables
│   └── migrate_security.sql # Add security to existing DB
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
├── verify_2fa.php           # Enter 6-digit code after password
├── setup_2fa.php            # First-time: QR code + verify code
├── activity_logs.php        # Admin: view activity logs (login, logout, CRUD)
├── logout.php
├── dashboard.php
├── employees.php            # Admin only; CRUD + CSRF
├── profile.php              # Staff own profile (read-only)
└── README.md
```

## Pages

| Page          | URL                  | Access              |
|---------------|----------------------|---------------------|
| Login         | `/login.php`         | Public              |
| Dashboard     | `/dashboard.php`     | Logged-in           |
| Employees     | `/employees.php`     | **Admin only**      |
| Activity Logs | `/activity_logs.php` | **Admin only**      |
| My Profile    | `/profile.php`       | **Staff (own only)**|

## Tech stack

- **Backend:** PHP (MySQLi)
- **Database:** MySQL
- **Frontend:** HTML, Tailwind CSS (CDN), minimal JS
- **Auth:** Session-based; BCrypt; RBAC (admin / staff)
