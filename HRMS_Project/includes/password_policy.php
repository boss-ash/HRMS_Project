<?php
/**
 * Password policy for Horyzon: validation and rules description.
 * Use when changing or resetting passwords.
 */

// Policy settings (change here to adjust rules)
if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}
if (!defined('PASSWORD_REQUIRE_UPPERCASE')) {
    define('PASSWORD_REQUIRE_UPPERCASE', true);
}
if (!defined('PASSWORD_REQUIRE_LOWERCASE')) {
    define('PASSWORD_REQUIRE_LOWERCASE', true);
}
if (!defined('PASSWORD_REQUIRE_NUMBER')) {
    define('PASSWORD_REQUIRE_NUMBER', true);
}
if (!defined('PASSWORD_REQUIRE_SPECIAL')) {
    define('PASSWORD_REQUIRE_SPECIAL', true);
}
if (!defined('PASSWORD_SPECIAL_CHARS')) {
    define('PASSWORD_SPECIAL_CHARS', '!@#$%^&*()_+-=[]{}|;:\'",.<>?/`~');
}

/**
 * Validate a password against the policy.
 * Returns array of error messages (empty array = valid).
 *
 * @param string $password
 * @return string[]
 */
function validate_password_policy($password) {
    $errors = [];
    $len = mb_strlen($password);

    if ($len < PASSWORD_MIN_LENGTH) {
        $errors[] = 'At least ' . PASSWORD_MIN_LENGTH . ' characters required.';
    }
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = 'At least one uppercase letter (A–Z) required.';
    }
    if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
        $errors[] = 'At least one lowercase letter (a–z) required.';
    }
    if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
        $errors[] = 'At least one number (0–9) required.';
    }
    if (PASSWORD_REQUIRE_SPECIAL) {
        $special = preg_quote(PASSWORD_SPECIAL_CHARS, '/');
        if (!preg_match('/[' . $special . ']/', $password)) {
            $errors[] = 'At least one special character required (e.g. ! @ # $ %).';
        }
    }

    return $errors;
}

/**
 * Return a short, human-readable description of the policy for UI.
 *
 * @return string
 */
function get_password_policy_description() {
    $parts = ['At least ' . PASSWORD_MIN_LENGTH . ' characters'];
    if (PASSWORD_REQUIRE_UPPERCASE) $parts[] = 'one uppercase letter';
    if (PASSWORD_REQUIRE_LOWERCASE) $parts[] = 'one lowercase letter';
    if (PASSWORD_REQUIRE_NUMBER) $parts[] = 'one number';
    if (PASSWORD_REQUIRE_SPECIAL) $parts[] = 'one special character (!@#$%^&* etc.)';
    return implode(', ', $parts) . '.';
}
