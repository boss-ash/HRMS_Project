<?php
/**
 * Login rate-limiting to prevent brute-force attacks.
 * Uses database table login_attempts; requires $conn (MySQLi) to be available.
 */

function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Max failed attempts within the window (then lock; after cooldown they get this many again) */
define('LOGIN_RATE_LIMIT_MAX_ATTEMPTS', 3);
/** Time window in seconds (20 for testing; use 900 for production = 15 minutes) */
define('LOGIN_RATE_LIMIT_WINDOW', 20);

/**
 * Returns current number of failed attempts in the window for this IP.
 */
function getFailedAttemptCount($conn) {
    $ip = getClientIp();
    $since = time() - LOGIN_RATE_LIMIT_WINDOW;
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM login_attempts WHERE ip_address = ? AND UNIX_TIMESTAMP(attempted_at) > ?");
    mysqli_stmt_bind_param($stmt, 'si', $ip, $since);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) ($row['c'] ?? 0);
}

/**
 * Returns true if this IP is allowed to attempt login; false if rate-limited.
 * Uses Unix timestamp so PHP at MySQL same ang window (walang timezone reset).
 */
function isLoginAllowed($conn) {
    return getFailedAttemptCount($conn) < LOGIN_RATE_LIMIT_MAX_ATTEMPTS;
}

/**
 * Record a failed login attempt for current IP.
 */
function recordFailedLogin($conn) {
    $ip = getClientIp();
    $stmt = mysqli_prepare($conn, "INSERT INTO login_attempts (ip_address) VALUES (?)");
    mysqli_stmt_bind_param($stmt, 's', $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Delete old login_attempts rows (e.g. older than 1 day) to prevent table bloat.
 * Call periodically from login page.
 */
function cleanupOldLoginAttempts($conn, $olderThanSeconds = 86400) {
    $since = date('Y-m-d H:i:s', time() - $olderThanSeconds);
    $stmt = mysqli_prepare($conn, "DELETE FROM login_attempts WHERE attempted_at < ?");
    mysqli_stmt_bind_param($stmt, 's', $since);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Clear failed attempts for current IP (call on successful login).
 */
function clearLoginAttempts($conn) {
    $ip = getClientIp();
    $stmt = mysqli_prepare($conn, "DELETE FROM login_attempts WHERE ip_address = ?");
    mysqli_stmt_bind_param($stmt, 's', $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Returns Unix timestamp when the current IP can try again (cooldown ends), or null if allowed.
 * Uses UNIX_TIMESTAMP so walang timezone issue — same ang cooldown kahit i-refresh.
 */
function getCooldownEndTimestamp($conn) {
    $ip = getClientIp();
    $since = time() - LOGIN_RATE_LIMIT_WINDOW;
    $stmt = mysqli_prepare($conn, "SELECT MIN(UNIX_TIMESTAMP(attempted_at)) AS first_ts FROM login_attempts WHERE ip_address = ? AND UNIX_TIMESTAMP(attempted_at) > ?");
    mysqli_stmt_bind_param($stmt, 'si', $ip, $since);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (empty($row['first_ts']) || (int) $row['first_ts'] <= 0) return null;
    $first = (int) $row['first_ts'];
    $end = $first + LOGIN_RATE_LIMIT_WINDOW;
    $now = time();
    if ($end <= $now) return null;
    if ($end > $now + LOGIN_RATE_LIMIT_WINDOW) $end = $now + LOGIN_RATE_LIMIT_WINDOW;
    return $end;
}
