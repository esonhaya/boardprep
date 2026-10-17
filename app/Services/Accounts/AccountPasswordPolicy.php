<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use InvalidArgumentException;

final class AccountPasswordPolicy
{
    public static function validate(string $password): void
    {
        if (strlen($password) > 1024) {
            throw new InvalidArgumentException('Use a password with no more than 128 characters.');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
        if ($length < 12) {
            throw new InvalidArgumentException('Use a password with at least 12 characters.');
        }
        if ($length > 128) {
            throw new InvalidArgumentException('Use a password with no more than 128 characters.');
        }

        if (!self::usesArgon2id() && PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($password) > 72) {
            throw new InvalidArgumentException('This PHP runtime limits passwords to 72 bytes. Shorten this password or use a runtime with Argon2id support.');
        }
    }

    public static function verify(string $candidate, string $storedHash): bool
    {
        return strlen($candidate) <= 1024 && password_verify($candidate, $storedHash);
    }

    public static function hash(string $password): string
    {
        $algorithm = self::usesArgon2id() ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $options = self::usesArgon2id()
            ? ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1]
            : [];
        $hash = password_hash($password, $algorithm, $options);
        if (!is_string($hash)) {
            throw new \RuntimeException('Credential storage is temporarily unavailable.');
        }

        return $hash;
    }

    public static function needsRehash(string $hash): bool
    {
        $algorithm = self::usesArgon2id() ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $options = self::usesArgon2id()
            ? ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1]
            : [];

        return password_needs_rehash($hash, $algorithm, $options);
    }

    public static function usesArgon2id(): bool
    {
        return defined('PASSWORD_ARGON2ID')
            && in_array('argon2id', password_algos(), true);
    }
}
