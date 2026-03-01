<?php
/**
 * Activity logging (login, logout, employee actions, etc.).
 * Requires $conn (MySQLi) to be available.
 */

function _activity_get_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function _activity_get_ua() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if ($ua && strlen($ua) > 255) $ua = substr($ua, 0, 255);
    return $ua;
}

/**
 * Log an activity. $userId can be null (e.g. failed login). $details optional (e.g. "Employee ID 5").
 */
function log_activity($conn, $userId, $action, $details = null) {
    $ip = _activity_get_ip();
    $ua = _activity_get_ua();
    $userId = $userId !== null ? (int) $userId : null;
    $details = $details !== null && strlen($details) > 500 ? substr($details, 0, 500) : $details;
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'issss', $userId, $action, $details, $ip, $ua);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
