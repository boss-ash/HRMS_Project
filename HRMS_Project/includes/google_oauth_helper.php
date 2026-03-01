<?php
/**
 * Google Sign-In (Gmail). Requires config/google_oauth.php with client_id, client_secret, redirect_uri.
 */

function google_oauth_enabled() {
    $file = __DIR__ . '/../config/google_oauth.php';
    if (!is_file($file)) return false;
    $cfg = include $file;
    return is_array($cfg) && !empty($cfg['client_id']) && !empty($cfg['client_secret']);
}

function google_oauth_config() {
    $file = __DIR__ . '/../config/google_oauth.php';
    if (!is_file($file)) return null;
    $cfg = include $file;
    return is_array($cfg) ? $cfg : null;
}

function google_oauth_redirect_uri() {
    $cfg = google_oauth_config();
    if ($cfg && !empty($cfg['redirect_uri'])) return $cfg['redirect_uri'];
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    return $base . ($path ? $path . '/' : '') . 'google_callback.php';
}

/** Ensure users table has email and google_id columns (for first-time use). */
function google_oauth_ensure_columns($conn) {
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
    if (!$r || mysqli_num_rows($r) === 0) @mysqli_query($conn, "ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL");
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'google_id'");
    if (!$r || mysqli_num_rows($r) === 0) @mysqli_query($conn, "ALTER TABLE users ADD COLUMN google_id VARCHAR(50) NULL UNIQUE");
    return true;
}
