<?php
/**
 * Session heartbeat — returns session expiry time. isLoggedIn() updates last_activity.
 * Called by the session timer JS on user activity.
 */
define('HRMS_LOADED', true);
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'expires_at' => null]);
    exit;
}

$expiresAt = ($_SESSION['last_activity'] ?? time()) + SESSION_IDLE_TIMEOUT;
echo json_encode(['ok' => true, 'expires_at' => $expiresAt]);
