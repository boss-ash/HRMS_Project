<?php
/**
 * TOTP (Google Authenticator) - pure PHP, no Composer.
 * RFC 6238 compatible.
 */

function totp_base32_decode($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input));
    $buffer = 0;
    $bitsLeft = 0;
    $output = '';
    for ($i = 0; $i < strlen($input); $i++) {
        $pos = strpos($alphabet, $input[$i]);
        if ($pos === false) continue;
        $buffer = ($buffer << 5) | $pos;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $output;
}

function totp_base32_encode($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $buffer = 0;
    $bitsLeft = 0;
    for ($i = 0; $i < strlen($input); $i++) {
        $buffer = ($buffer << 8) | ord($input[$i]);
        $bitsLeft += 8;
        while ($bitsLeft >= 5) {
            $bitsLeft -= 5;
            $output .= $alphabet[($buffer >> $bitsLeft) & 31];
        }
    }
    if ($bitsLeft > 0) $output .= $alphabet[($buffer << (5 - $bitsLeft)) & 31];
    return $output;
}

/**
 * Generate a random secret (raw bytes); encode as base32 for storage and QR.
 */
function totp_generate_secret($length = 20) {
    $bytes = random_bytes($length);
    return totp_base32_encode($bytes);
}

/**
 * Verify 6-digit code. $secret is base32 string from DB.
 * Optional window: allow 1 step before/after (30 sec) for clock drift.
 */
function totp_verify($secret, $code, $window = 1) {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $secretBin = totp_base32_decode($secret);
    if ($secretBin === '') return false;
    $timeSlice = floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $counter = pack('N*', 0) . pack('N', $timeSlice + $i);
        $hash = hash_hmac('sha1', $counter, $secretBin, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );
        $otp = $truncated % 1000000;
        if ((int) $code === $otp) return true;
    }
    return false;
}

/**
 * Get current 6-digit code (for testing or display). $secret is base32.
 */
function totp_get_code($secret) {
    $secretBin = totp_base32_decode($secret);
    if ($secretBin === '') return '';
    $timeSlice = floor(time() / 30);
    $counter = pack('N*', 0) . pack('N', $timeSlice);
    $hash = hash_hmac('sha1', $counter, $secretBin, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    );
    return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * otpauth:// URL for QR code (Google Authenticator).
 */
function totp_get_qr_url($secret, $label, $issuer = 'Horyzon') {
    $label = rawurlencode($label);
    $issuer = rawurlencode($issuer);
    $secret = preg_replace('/[^A-Z2-7]/', '', strtoupper($secret));
    return "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
}
