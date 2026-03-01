<?php
/**
 * Authentication and Role-Based Access Control (RBAC).
 */

/** Session idle timeout (seconds) — auto logout after this long without activity. */
if (!defined('SESSION_IDLE_TIMEOUT')) {
    define('SESSION_IDLE_TIMEOUT', 5 * 60); // 5 minutes
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    // Session idle timeout: if last activity was too long ago, treat as logged out
    $last = $_SESSION['last_activity'] ?? 0;
    if ($last && (time() - $last) > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function getRole() {
    return $_SESSION['user_role'] ?? 'staff';
}

function isAdmin() {
    return getRole() === 'admin';
}

function getEmployeeId() {
    return isset($_SESSION['employee_id']) ? (int) $_SESSION['employee_id'] : null;
}

/** Require user to be logged in; redirect to login if not. */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . 'login.php');
        exit;
    }
}

/** Require Admin role; redirect to dashboard with error if not. */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['flash_error'] = 'Access denied. Admin only.';
        header('Location: ' . getBaseUrl() . 'dashboard.php');
        exit;
    }
}

/** Require Staff to only access their own employee_id (for profile). */
function requireOwnProfile($employeeId) {
    requireLogin();
    if (isAdmin()) return;
    $ownId = getEmployeeId();
    if ($ownId === null || (int) $employeeId !== $ownId) {
        $_SESSION['flash_error'] = 'Access denied. You can only view your own profile.';
        header('Location: ' . getBaseUrl() . 'dashboard.php');
        exit;
    }
}

function getBaseUrl() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = dirname($script);
    if ($path === '/' || $path === '\\') return '/';
    return rtrim($path, '/\\') . '/';
}

function getBasePath() {
    return getBaseUrl();
}

/** CSRF token: generate and store in session; use in forms. */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Validate CSRF token from POST. */
function csrf_validate() {
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
