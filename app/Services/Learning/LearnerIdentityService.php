<?php

declare(strict_types=1);

namespace App\Services\Learning;

use RuntimeException;

final class LearnerIdentityService
{
    private const SESSION_KEY = 'learner_identity';

    public static function current(): string
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $existing = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_string($existing) && preg_match('/\Alearner-[a-f0-9]{32}\z/', $existing) === 1) {
            return $existing;
        }

        try {
            $identity = 'learner-' . bin2hex(random_bytes(16));
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to initialize learner identity.', 0, $exception);
        }

        $_SESSION[self::SESSION_KEY] = $identity;

        return $identity;
    }
}
