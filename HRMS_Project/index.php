<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/includes/auth.php';
$base = getBaseUrl();
if (isLoggedIn()) {
    header('Location: ' . $base . 'dashboard.php');
} else {
    header('Location: ' . $base . 'login.php');
}
exit;
