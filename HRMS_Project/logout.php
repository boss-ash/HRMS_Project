<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_log.php';

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
if ($userId && isset($conn)) {
    log_activity($conn, $userId, 'logout');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
$base = getBaseUrl();
$path = isset($_GET['timeout']) && $_GET['timeout'] === '1' ? 'login.php?timeout=1' : 'login.php';
header('Location: ' . $base . $path);
exit;
