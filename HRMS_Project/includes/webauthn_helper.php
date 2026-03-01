<?php
/**
 * WebAuthn / Passkey helper. Requires: composer require lbuchs/webauthn
 * When vendor/autoload.php is missing, passkey features are disabled.
 */

function passkey_available() {
    return file_exists(__DIR__ . '/../vendor/autoload.php');
}

/**
 * Ensure webauthn_credentials table exists (no manual migration needed).
 */
function passkey_ensure_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS webauthn_credentials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        credential_id VARCHAR(255) NOT NULL,
        public_key TEXT NOT NULL,
        counter INT UNSIGNED NOT NULL DEFAULT 0,
        name VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_credential (credential_id(191)),
        INDEX idx_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    return @mysqli_query($conn, $sql);
}

function passkey_webauthn_instance($conn) {
    if (!passkey_available()) return null;
    require_once __DIR__ . '/../vendor/autoload.php';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $rpId = preg_replace('/:\d+$/', '', $host);
    try {
        return new \lbuchs\WebAuthn\WebAuthn('Horyzon HRMS', $rpId, null, true);
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * List passkeys for a user (id, created_at, name) for display and remove.
 */
function passkey_list_credentials($conn, $userId) {
    $userId = (int) $userId;
    $res = mysqli_query($conn, "SELECT id, created_at, name FROM webauthn_credentials WHERE user_id = $userId ORDER BY created_at DESC");
    if (!$res) return [];
    $list = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $list[] = ['id' => (int) $row['id'], 'created_at' => $row['created_at'], 'name' => $row['name'] ?? ''];
    }
    return $list;
}

/**
 * Remove a passkey. Returns true if deleted (and belonged to user).
 */
function passkey_remove($conn, $userId, $id) {
    $userId = (int) $userId;
    $id = (int) $id;
    $stmt = mysqli_prepare($conn, "DELETE FROM webauthn_credentials WHERE user_id = ? AND id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $id);
    $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * Get credential IDs for a user (for login challenge).
 */
function passkey_get_credential_ids($conn, $userId) {
    $userId = (int) $userId;
    $res = mysqli_query($conn, "SELECT credential_id FROM webauthn_credentials WHERE user_id = $userId");
    if (!$res) return [];
    $ids = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $ids[] = $row['credential_id'];
    }
    return $ids;
}

/**
 * Get options for registration. Returns JSON-serializable object or null.
 * Excludes existing credentials for this user.
 */
function passkey_get_register_options($conn, $userId, $userName, $displayName) {
    passkey_ensure_table($conn);
    $wa = passkey_webauthn_instance($conn);
    if (!$wa) return null;
    $userIdBin = str_pad((string)$userId, 32, "\0", STR_PAD_LEFT);
    $excludeIds = [];
    foreach (passkey_get_credential_ids($conn, $userId) as $hexId) {
        if (strlen($hexId) % 2 === 0 && ctype_xdigit($hexId)) {
            $excludeIds[] = hex2bin($hexId);
        }
    }
    try {
        // false = platform (device: Windows Hello, fingerprint, face ID) — prefer over USB key
        $args = $wa->getCreateArgs($userIdBin, $userName, $displayName, 60, false, 'preferred', false, $excludeIds);
        $ch = $wa->getChallenge();
        $_SESSION['webauthn_challenge'] = $ch && method_exists($ch, 'getBinaryString') ? $ch->getBinaryString() : (string) $ch;
        $_SESSION['webauthn_user_id'] = $userId;
        return $args;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Process registration response. Returns true on success.
 */
function passkey_process_register($conn, $clientDataJSON, $attestationObject, $userId) {
    $wa = passkey_webauthn_instance($conn);
    if (!$wa || empty($_SESSION['webauthn_challenge']) || (int)$_SESSION['webauthn_user_id'] !== (int)$userId) return false;
    $challenge = $_SESSION['webauthn_challenge'];
    try {
        $data = $wa->processCreate($clientDataJSON, $attestationObject, $challenge, false, true, false);
        if (!$data) return false;
        $credentialId = $data->credentialId ?? null;
        $publicKey = $data->credentialPublicKey ?? null;
        if (!$credentialId || !$publicKey) return false;
        $credIdBin = is_object($credentialId) && method_exists($credentialId, 'getBinaryString') ? $credentialId->getBinaryString() : (is_string($credentialId) ? $credentialId : '');
        $credIdStr = bin2hex($credIdBin);
        $pkStr = is_string($publicKey) ? $publicKey : '';
        $stmt = mysqli_prepare($conn, "INSERT INTO webauthn_credentials (user_id, credential_id, public_key, counter) VALUES (?, ?, ?, 0)");
        mysqli_stmt_bind_param($stmt, 'iss', $userId, $credIdStr, $pkStr);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_user_id']);
        return $ok;
    } catch (\Throwable $e) {
        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_user_id']);
        return false;
    }
}

/**
 * Get options for login (assertion). $credentialIds = array of hex credential IDs (binary for allowCredentials).
 * Pass [] for discoverable passkeys (browser will use any registered passkey).
 */
function passkey_get_login_options($conn, $credentialIds = null) {
    passkey_ensure_table($conn);
    $wa = passkey_webauthn_instance($conn);
    if (!$wa) return null;
    $binIds = [];
    if (is_array($credentialIds)) {
        foreach ($credentialIds as $id) {
            if (is_string($id) && strlen($id) % 2 === 0 && ctype_xdigit($id)) {
                $binIds[] = hex2bin($id);
            }
        }
    }
    try {
        $args = $wa->getGetArgs($binIds, 90, true, true, true, true, true, 'preferred');
        $ch = $wa->getChallenge();
        $_SESSION['webauthn_login_challenge'] = $ch && method_exists($ch, 'getBinaryString') ? $ch->getBinaryString() : (string) $ch;
        return $args;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Process login assertion. Returns user_id on success, false otherwise.
 */
function passkey_process_login($conn, $clientDataJSON, $authenticatorData, $signature, $credentialId, $userHandle = null) {
    $wa = passkey_webauthn_instance($conn);
    if (!$wa || empty($_SESSION['webauthn_login_challenge'])) return false;
    $challenge = $_SESSION['webauthn_login_challenge'];
    $credIdHex = null;
    if (is_string($credentialId)) {
        $decoded = base64_decode(strtr($credentialId, '-_', '+/'), true);
        $credIdHex = $decoded !== false ? bin2hex($decoded) : $credentialId;
    } else {
        $credIdHex = $credentialId;
    }
    if (!$credIdHex) {
        unset($_SESSION['webauthn_login_challenge']);
        return false;
    }
    $esc = mysqli_real_escape_string($conn, $credIdHex);
    $res = mysqli_query($conn, "SELECT user_id, public_key, counter FROM webauthn_credentials WHERE credential_id = '$esc' LIMIT 1");
    if (!$res || mysqli_num_rows($res) === 0) {
        unset($_SESSION['webauthn_login_challenge']);
        return false;
    }
    $row = mysqli_fetch_assoc($res);
    $publicKeyPem = $row['public_key'];
    $counter = (int) $row['counter'];
    try {
        $wa->processGet($clientDataJSON, $authenticatorData, $signature, $publicKeyPem, $challenge, $counter, false, true);
        $newCounter = $wa->getSignatureCounter();
        if ($newCounter !== null) {
            mysqli_query($conn, "UPDATE webauthn_credentials SET counter = " . (int)$newCounter . " WHERE credential_id = '$esc'");
        }
        unset($_SESSION['webauthn_login_challenge']);
        return (int) $row['user_id'];
    } catch (\Throwable $e) {
        unset($_SESSION['webauthn_login_challenge']);
        return false;
    }
}
