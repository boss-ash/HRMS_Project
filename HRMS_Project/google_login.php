<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google_oauth_helper.php';

if (isLoggedIn()) {
    header('Location: ' . getBasePath() . 'dashboard.php');
    exit;
}

if (!google_oauth_enabled()) {
    $_SESSION['flash_error'] = 'Sign in with Google is not configured.';
    header('Location: ' . getBasePath() . 'login.php');
    exit;
}

$cfg = google_oauth_config();
$redirectUri = google_oauth_redirect_uri();
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => $cfg['client_id'],
    'redirect_uri'  => $redirectUri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
]);
header('Location: ' . $url);
exit;
