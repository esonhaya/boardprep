<?php

declare(strict_types=1);

namespace App\Services\Shared;

final class CsrfTokenService
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $existing = \SessionService::get(self::SESSION_KEY);
        if (is_string($existing) && preg_match('/\A[a-f0-9]{64}\z/', $existing) === 1) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        \SessionService::set(self::SESSION_KEY, $token);

        return $token;
    }

    public static function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        \SessionService::set(self::SESSION_KEY, $token);
        return $token;
    }

    public static function valid(mixed $candidate): bool
    {
        $expected = \SessionService::get(self::SESSION_KEY);
        if (!is_string($candidate) || !is_string($expected) || $candidate === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }
}
