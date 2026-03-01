<?php
/**
 * Passkey (WebAuthn) API: register options, register finish, login options, login verify.
 * All JSON request/response. Requires composer lbuchs/webauthn and webauthn_credentials table.
 */
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/webauthn_helper.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($data) {
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
}

function base64url_decode($s) {
    if (!is_string($s)) return null;
    $dec = base64_decode(strtr($s, '-_', '+/'), true);
    return $dec !== false ? $dec : null;
}

/** Recursively convert options to JSON-serializable form (ByteBuffer -> base64url string). */
function webauthn_options_for_json($obj) {
    if ($obj === null || is_scalar($obj)) {
        return $obj;
    }
    if (is_object($obj) && method_exists($obj, 'getBinaryString')) {
        $bin = $obj->getBinaryString();
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
    if (is_object($obj)) {
        $arr = [];
        foreach ((array) $obj as $k => $v) {
            $arr[$k] = webauthn_options_for_json($v);
        }
        return $arr;
    }
    if (is_array($obj)) {
        return array_map('webauthn_options_for_json', $obj);
    }
    return $obj;
}

$base = getBasePath();

if (!passkey_available()) {
    json_out(['ok' => false, 'error' => 'Passkey not configured. Run: composer require lbuchs/webauthn']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$postBody = null;
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $postBody = file_get_contents('php://input');
    if ($action === '' && $postBody !== '') {
        $dec = json_decode($postBody, true);
        if (is_array($dec) && isset($dec['action'])) {
            $action = $dec['action'];
        }
    }
}

switch ($action) {

    case 'register_options':
        if (!isLoggedIn()) {
            json_out(['ok' => false, 'error' => 'Not logged in']);
            exit;
        }
        $userId = (int) $_SESSION['user_id'];
        $userName = $_SESSION['username'] ?? (string) $userId;
        $displayName = $_SESSION['user_name'] ?? $userName;
        $options = passkey_get_register_options($conn, $userId, $userName, $displayName);
        if (!$options) {
            json_out(['ok' => false, 'error' => 'Could not generate options']);
            exit;
        }
        json_out(['ok' => true, 'options' => webauthn_options_for_json($options)]);
        break;

    case 'register':
        if (!isLoggedIn()) {
            json_out(['ok' => false, 'error' => 'Not logged in']);
            exit;
        }
        $body = $postBody !== null ? json_decode($postBody, true) : null;
        if (!$body || empty($body['id']) || empty($body['response'])) {
            json_out(['ok' => false, 'error' => 'Invalid request']);
            exit;
        }
        $resp = $body['response'];
        $clientDataJSON = $resp['clientDataJSON'] ?? '';
        $attestationObject = $resp['attestationObject'] ?? '';
        if (!$clientDataJSON || !$attestationObject) {
            json_out(['ok' => false, 'error' => 'Missing clientDataJSON or attestationObject']);
            exit;
        }
        $clientDataBin = base64url_decode($clientDataJSON);
        $attestationBin = base64url_decode($attestationObject);
        if ($clientDataBin === null || $attestationBin === null) {
            json_out(['ok' => false, 'error' => 'Invalid base64 encoding']);
            exit;
        }
        $userId = (int) $_SESSION['user_id'];
        $ok = passkey_process_register($conn, $clientDataBin, $attestationBin, $userId);
        if (!$ok) {
            json_out(['ok' => false, 'error' => 'Registration failed']);
            exit;
        }
        json_out(['ok' => true]);
        break;

    case 'login_options':
        $options = passkey_get_login_options($conn, []);
        if (!$options) {
            json_out(['ok' => false, 'error' => 'Could not generate options']);
            exit;
        }
        json_out(['ok' => true, 'options' => webauthn_options_for_json($options)]);
        break;

    case 'login_verify':
        $body = $postBody !== null ? json_decode($postBody, true) : null;
        if (!$body || empty($body['id']) || empty($body['response'])) {
            json_out(['ok' => false, 'error' => 'Invalid request']);
            exit;
        }
        $resp = $body['response'];
        $clientDataJSON = $resp['clientDataJSON'] ?? '';
        $authenticatorData = $resp['authenticatorData'] ?? '';
        $signature = $resp['signature'] ?? '';
        $credentialId = $body['rawId'] ?? $body['id'] ?? '';
        if (!$clientDataJSON || !$authenticatorData || !$signature || !$credentialId) {
            json_out(['ok' => false, 'error' => 'Missing assertion data']);
            exit;
        }
        $clientDataBin = base64url_decode($clientDataJSON);
        $authDataBin = base64url_decode($authenticatorData);
        $sigBin = base64url_decode($signature);
        if ($clientDataBin === null || $authDataBin === null || $sigBin === null) {
            json_out(['ok' => false, 'error' => 'Invalid base64 encoding']);
            exit;
        }
        $userId = passkey_process_login($conn, $clientDataBin, $authDataBin, $sigBin, $credentialId);
        if ($userId === false) {
            json_out(['ok' => false, 'error' => 'Verification failed']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "SELECT username, full_name, role, employee_id FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        if (!$user) {
            json_out(['ok' => false, 'error' => 'User not found']);
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'] === 'admin' ? 'admin' : 'staff';
        $_SESSION['employee_id'] = $user['employee_id'] ? (int) $user['employee_id'] : null;
        $_SESSION['last_activity'] = time();
        $_SESSION['pending_2fa_user_id'] = $userId;
        $_SESSION['pending_2fa_username'] = $user['username'];
        $_SESSION['pending_2fa_full_name'] = $user['full_name'];
        $_SESSION['pending_2fa_role'] = $_SESSION['user_role'];
        $_SESSION['pending_2fa_employee_id'] = $_SESSION['employee_id'];
        $stmt2 = mysqli_prepare($conn, "SELECT totp_secret FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt2, 'i', $userId);
        mysqli_stmt_execute($stmt2);
        $r2 = mysqli_stmt_get_result($stmt2);
        $row2 = mysqli_fetch_assoc($r2);
        mysqli_stmt_close($stmt2);
        $redirect = $base . (isset($row2['totp_secret']) && $row2['totp_secret'] !== '' ? 'verify_2fa.php' : 'setup_2fa.php');
        json_out(['ok' => true, 'redirect' => $redirect]);
        break;

    case 'remove_passkey':
        if (!isLoggedIn()) {
            json_out(['ok' => false, 'error' => 'Not logged in']);
            exit;
        }
        $userId = (int) $_SESSION['user_id'];
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            json_out(['ok' => false, 'error' => 'Invalid passkey']);
            exit;
        }
        if (!passkey_remove($conn, $userId, $id)) {
            json_out(['ok' => false, 'error' => 'Could not remove passkey']);
            exit;
        }
        json_out(['ok' => true]);
        break;

    default:
        json_out(['ok' => false, 'error' => 'Unknown action']);
}
