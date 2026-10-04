<?php

declare(strict_types=1);

namespace App\Core;

final class SecurityHeaders
{
    public static function apply(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        $https = ($_SERVER['HTTPS'] ?? '') !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}
