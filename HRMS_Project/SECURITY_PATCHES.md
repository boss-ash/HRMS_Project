# HRMS — Lahat ng Security Patches (System + Database)

Buong listahan ng security patches na naka-deploy sa system at database.

---

## 1. Authentication at Password

| Patch | Saan | Detalye |
|-------|------|---------|
| **BCrypt password hashing** | Login flow | Passwords naka-store bilang hash; `password_verify()` / `password_hash()` sa PHP. Walang plain text sa DB. |
| **Prepared statements (login)** | `login.php` | Lahat ng query sa users table gamit `mysqli_prepare` + `bind_param` — walang string concatenation. |
| **Session regenerate on login** | `login.php`, `verify_2fa.php`, `setup_2fa.php` | `session_regenerate_id(true)` pag successful login para iwas session fixation. |

**Database:** Table `users` — column `password` VARCHAR(255) para sa BCrypt hash.

---

## 2. Brute-Force / Rate Limiting

| Patch | Saan | Detalye |
|-------|------|---------|
| **Login rate limit** | `includes/rate_limit.php`, `login.php` | Max 3 failed attempts per IP; lockout 20 seconds. |
| **Cooldown by Unix timestamp** | `rate_limit.php` | `UNIX_TIMESTAMP(attempted_at)` para consistent ang window kahit i-refresh. |
| **Cleanup old attempts** | `rate_limit.php` → `cleanupOldLoginAttempts()`, called sa `login.php` | Delete `login_attempts` rows older than 1 day para hindi lumaki ang table. |

**Database:** Table `login_attempts` — columns: `ip_address`, `attempted_at`. Index on `(ip_address, attempted_at)`.

**Files:** `includes/rate_limit.php`, `login.php`.

---

## 3. Two-Factor Authentication (2FA)

| Patch | Saan | Detalye |
|-------|------|---------|
| **TOTP (Google Authenticator)** | `includes/totp.php`, `verify_2fa.php`, `setup_2fa.php` | Pure PHP TOTP: generate secret, verify 6-digit code, QR URL. |
| **Login flow** | `login.php` | Pag tama ang password, redirect sa `verify_2fa.php` o `setup_2fa.php` — hindi diretsong logged in. |
| **2FA verify / setup** | `verify_2fa.php`, `setup_2fa.php` | Verify code bago i-set ang session; setup may QR + manual key. |

**Database:** Table `users` — column `totp_secret` VARCHAR(255) NULL.  
**Migration:** `database/migrate_2fa.sql` (add `totp_secret`).

**Files:** `includes/totp.php`, `login.php`, `verify_2fa.php`, `setup_2fa.php`.

---

## 4. Role-Based Access Control (RBAC)

| Patch | Saan | Detalye |
|-------|------|---------|
| **Admin vs Staff** | `includes/auth.php` | `getRole()`, `isAdmin()`, `requireAdmin()`, `requireOwnProfile()`. |
| **Admin only** | `employees.php`, `activity_logs.php` | `requireAdmin()` — Staff ay redirect sa dashboard na "Access denied. Admin only." |
| **Staff own profile only** | `profile.php` | `requireOwnProfile($employeeId)` — Staff puwede lang sa sariling `employee_id`. |
| **Nav by role** | `includes/nav.php` | Admin: Dashboard, Employees, Activity Logs. Staff: Dashboard, My Profile. |

**Database:** Table `users` — columns: `role` ENUM('admin','staff','hr'), `employee_id` INT NULL (link staff to employee).

**Files:** `includes/auth.php`, `includes/nav.php`, `employees.php`, `activity_logs.php`, `profile.php`.

---

## 5. CSRF Protection

| Patch | Saan | Detalye |
|-------|------|---------|
| **CSRF token** | `includes/auth.php` | `csrf_token()`, `csrf_validate()` — 32-byte token sa session. |
| **Login form** | `login.php` | Hidden input `csrf_token`; validate on POST. |
| **Employee form + delete** | `employees.php` | Hidden `csrf_token` sa add/edit form at sa delete form; validate on POST. |

**Files:** `includes/auth.php`, `login.php`, `employees.php`.

---

## 6. XSS / Input Sanitization

| Patch | Saan | Detalye |
|-------|------|---------|
| **Output escaping** | Lahat ng user-facing output | `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` sa messages, names, etc. |
| **Input sanitization** | `includes/sanitize.php` | `sanitize_string()`, `sanitize_email()`, `validate_email()`, `sanitize_code()` — strip_tags, length limit. |
| **Employee form** | `employees.php` | Lahat ng text inputs dina-sanitize bago i-save; edit button gumagamit ng `data-employee` (hindi inline JSON sa onclick). |

**Files:** `includes/sanitize.php`, `employees.php`, at lahat ng page na may user output.

---

## 7. Session Security

| Patch | Saan | Detalye |
|-------|------|---------|
| **Cookie params** | `includes/auth.php` | HttpOnly, SameSite=Lax, Secure kapag HTTPS. |
| **Session idle timeout** | `includes/auth.php` | `SESSION_IDLE_TIMEOUT` (e.g. 1 min for testing) — auto logout kapag walang activity. |
| **Last activity** | `includes/auth.php` | `$_SESSION['last_activity']` ina-update sa `isLoggedIn()`; kapag expired, session clear at redirect. |
| **Session timer UI** | `includes/nav.php`, `heartbeat.php` | Visible countdown sa nav; heartbeat.php para i-extend session on activity. |

**Files:** `includes/auth.php`, `includes/nav.php`, `heartbeat.php`, `login.php` (timeout message).

---

## 8. Security Headers

| Patch | Saan | Detalye |
|-------|------|---------|
| **X-Content-Type-Options** | `includes/header.php` | `nosniff` |
| **X-Frame-Options** | `includes/header.php` | `SAMEORIGIN` |
| **X-XSS-Protection** | `includes/header.php` | `1; mode=block` |
| **Referrer-Policy** | `includes/header.php` | `strict-origin-when-cross-origin` |
| **Content-Security-Policy** | `includes/header.php` | default-src 'self'; script/style/font/img naka-allow para sa Tailwind, fonts, QR image. |
| **Strict-Transport-Security** | `includes/header.php` | Naka-set kapag HTTPS (max-age=1 year, includeSubDomains, preload). |

**Files:** `includes/header.php`.

---

## 9. Activity Logging (Audit)

| Patch | Saan | Detalye |
|-------|------|---------|
| **Log helper** | `includes/activity_log.php` | `log_activity($conn, $userId, $action, $details)` — may IP at User-Agent. |
| **Login** | `verify_2fa.php`, `setup_2fa.php` | Action `login` pag successful 2FA. |
| **Logout** | `logout.php` | Action `logout` bago destroy session. |
| **Login failed** | `login.php` | Action `login_failed`, user_id null, details = username. |
| **Employee CRUD** | `employees.php` | Actions: `add_employee`, `edit_employee`, `delete_employee` with details. |
| **View logs (Admin only)** | `activity_logs.php` | List + filter by action; `requireAdmin()`. |

**Database:** Table `activity_logs` — columns: `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`.  
**Migration:** `database/migrate_activity_logs.sql`.

**Files:** `includes/activity_log.php`, `logout.php`, `login.php`, `verify_2fa.php`, `setup_2fa.php`, `employees.php`, `activity_logs.php`.

---

## 10. Database Security

| Patch | Saan | Detalye |
|-------|------|---------|
| **Prepared statements (lahat)** | Lahat ng PHP na may query | Login, employees CRUD, profile, rate limit, activity log, 2FA — walang raw concatenation sa SQL. |
| **Limited DB user (optional)** | `database/create_limited_user.sql` | User `hrms_app` — SELECT, INSERT, UPDATE, DELETE lang sa `hrms_db`; walang DROP/CREATE/GRANT. |
| **Config example** | `config/database.example.php` | Sample config; production dapat gamit ng `hrms_app` + strong password. |

**Files:** `config/database.php`, `config/database.example.php`, `database/create_limited_user.sql`.

---

## 11. Path / File Protection

| Patch | Saan | Detalye |
|-------|------|---------|
| **Block config/** | `config/.htaccess` | `Require all denied` — walang direct HTTP access sa config (protektado credentials). |
| **Block database/** | `database/.htaccess` | `Require all denied` — walang direct download ng .sql files. |

**Files:** `config/.htaccess`, `database/.htaccess`.

---

## Database Tables (Security-Related)

| Table | Layunin |
|-------|---------|
| **users** | `password` (BCrypt), `role`, `employee_id`, `totp_secret` — auth at RBAC, 2FA. |
| **login_attempts** | Rate limit: IP + timestamp; cleanup after 1 day. |
| **activity_logs** | Audit: user_id, action, details, ip_address, user_agent, created_at. |

---

## Migration Files (Kung May Existing DB Na)

| File | Idinagdag |
|------|-----------|
| `database/migrate_security.sql` | Table `login_attempts`; column `users.employee_id`. |
| `database/migrate_2fa.sql` | Column `users.totp_secret`. |
| `database/migrate_activity_logs.sql` | Table `activity_logs`. |
| `database/create_limited_user.sql` | User `hrms_app` (limited privileges). |

---

## Quick Checklist

- [x] BCrypt passwords  
- [x] Prepared statements (no SQL injection)  
- [x] Login rate limit + cooldown + cleanup  
- [x] 2FA (TOTP)  
- [x] RBAC (Admin / Staff)  
- [x] CSRF on forms  
- [x] XSS: sanitize + escape + CSP  
- [x] Session: HttpOnly, SameSite, idle timeout, timer + heartbeat  
- [x] Security headers (CSP, HSTS when HTTPS)  
- [x] Activity logs (login, logout, CRUD)  
- [x] Optional limited DB user  
- [x] .htaccess on config/ at database/  

---

*Huling update: kasama lahat ng patches hanggang session timer at 1-min idle timeout.*
