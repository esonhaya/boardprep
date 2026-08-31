<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

if (session_status() !== PHP_SESSION_NONE) {
    exit("[SKIPPED] session configuration requires a process without an active session.\n");
}

$_SERVER['HTTPS'] = 'on';
\App\Core\SessionConfiguration::start();
$params = session_get_cookie_params();

if (($params['secure'] ?? false) !== true
    || ($params['httponly'] ?? false) !== true
    || strtolower((string) ($params['samesite'] ?? '')) !== 'lax'
    || ini_get('session.use_strict_mode') !== '1') {
    exit("[FAIL] production session cookie safeguards were not configured.\n");
}

session_write_close();
echo "[PASS] strict session IDs and secure cookie attributes are configured.\n";
