<?php

declare(strict_types=1);

namespace App\Core;

final class SessionConfiguration
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $useCookies = filter_var(
            ini_get('session.use_cookies'),
            FILTER_VALIDATE_BOOLEAN
        );

        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', '43200');

        // The HTTP simulator supplies session IDs explicitly without cookies;
        // PHP rejects cookie parameters when cookie sessions are disabled.
        if ($useCookies) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            $https = ($_SERVER['HTTPS'] ?? '') !== ''
                && strtolower((string) $_SERVER['HTTPS']) !== 'off';

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            // Explicit session IDs are used only by the isolated CLI HTTP
            // simulator, which has no cookie jar.
            ini_set('session.use_strict_mode', '0');
        }

        if (!session_start()) {
            throw new \RuntimeException('Unable to start the application session.');
        }
    }
}
