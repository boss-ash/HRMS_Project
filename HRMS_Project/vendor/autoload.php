<?php
/**
 * Simple autoload for lbuchs/WebAuthn (no Composer required).
 */
spl_autoload_register(function ($class) {
    $prefix = 'lbuchs\\WebAuthn\\';
    $base = __DIR__ . '/lbuchs/webauthn/src/';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        $old = set_include_path($base . PATH_SEPARATOR . get_include_path());
        require $file;
        set_include_path($old);
    }
});
