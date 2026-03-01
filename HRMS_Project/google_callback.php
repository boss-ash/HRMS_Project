<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google_oauth_helper.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/activity_log.php';

$base = getBasePath();

if (!google_oauth_enabled()) {
    header('Location: ' . $base . 'login.php');
    exit;
}

$state = $_GET['state'] ?? '';
if (empty($state) || !isset($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $state)) {
    $_SESSION['flash_error'] = 'Invalid request. Please try again.';
    header('Location: ' . $base . 'login.php');
    exit;
}
unset($_SESSION['google_oauth_state']);

$code = $_GET['code'] ?? '';
if (empty($code)) {
    $_SESSION['flash_error'] = 'Sign in was cancelled or failed.';
    header('Location: ' . $base . 'login.php');
    exit;
}

$cfg = google_oauth_config();
$redirectUri = google_oauth_redirect_uri();

$tokenUrl = 'https://oauth2.googleapis.com/token';
$post = http_build_query([
    'code'          => $code,
    'client_id'     => $cfg['client_id'],
    'client_secret' => $cfg['client_secret'],
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);
$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $post,
    ],
]);
$tokenJson = @file_get_contents($tokenUrl, false, $ctx);
$token = $tokenJson ? json_decode($tokenJson, true) : null;
$accessToken = $token['access_token'] ?? null;

if (!$accessToken) {
    $_SESSION['flash_error'] = 'Could not sign in with Google. Please try again.';
    header('Location: ' . $base . 'login.php');
    exit;
}

$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . urlencode($accessToken);
$userJson = @file_get_contents($userInfoUrl);
$userInfo = $userJson ? json_decode($userJson, true) : null;
$googleId = $userInfo['id'] ?? null;
$email = $userInfo['email'] ?? null;
$name = $userInfo['name'] ?? $email;

if (!$googleId || !$email) {
    $_SESSION['flash_error'] = 'Could not get your Google profile.';
    header('Location: ' . $base . 'login.php');
    exit;
}

google_oauth_ensure_columns($conn);

$user = null;
$stmt = mysqli_prepare($conn, "SELECT id, username, full_name, role, employee_id, totp_secret FROM users WHERE google_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $googleId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $user = $row;
}
mysqli_stmt_close($stmt);

if (!$user) {
    $stmt = mysqli_prepare($conn, "SELECT id, username, full_name, role, employee_id, totp_secret FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $user = $row;
        mysqli_stmt_close($stmt);
        @mysqli_query($conn, "UPDATE users SET google_id = '" . mysqli_real_escape_string($conn, $googleId) . "' WHERE id = " . (int)$user['id']);
    } else {
        mysqli_stmt_close($stmt);
    }
}

if (!$user) {
    $_SESSION['flash_error'] = 'No account found for this Google email (' . htmlspecialchars($email) . '). Use username/password to log in, then add your email in profile or ask admin so you can use Google next time.';
    header('Location: ' . $base . 'login.php');
    exit;
}

clearLoginAttempts($conn);
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['user_role'] = ($user['role'] === 'admin') ? 'admin' : 'staff';
$_SESSION['employee_id'] = $user['employee_id'] ? (int) $user['employee_id'] : null;
$_SESSION['last_activity'] = time();
$_SESSION['pending_2fa_user_id'] = (int) $user['id'];
$_SESSION['pending_2fa_username'] = $user['username'];
$_SESSION['pending_2fa_full_name'] = $user['full_name'];
$_SESSION['pending_2fa_role'] = $_SESSION['user_role'];
$_SESSION['pending_2fa_employee_id'] = $_SESSION['employee_id'];

log_activity($conn, $user['id'], 'login', 'Google sign-in: ' . $email);

if (!empty($user['totp_secret'])) {
    header('Location: ' . $base . 'verify_2fa.php');
} else {
    header('Location: ' . $base . 'setup_2fa.php');
}
exit;
