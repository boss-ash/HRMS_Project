<?php
/**
 * Input sanitization to prevent XSS and enforce safe string limits.
 * Use when accepting user input from forms or GET/POST.
 */

function sanitize_string($value, $maxLength = 255) {
    if (!is_string($value)) return '';
    $value = trim($value);
    $value = strip_tags($value);
    if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

/** Sanitize for HTML output (defense in depth; prefer htmlspecialchars at output time) */
function sanitize_for_output($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sanitize_email($value) {
    $value = trim($value);
    $value = filter_var($value, FILTER_SANITIZE_EMAIL);
    return $value;
}

function validate_email($value) {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

/** Allow only alphanumeric and limited chars for codes */
function sanitize_code($value, $maxLength = 20) {
    $value = preg_replace('/[^A-Za-z0-9\-_]/', '', trim($value));
    if ($maxLength > 0 && strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}
