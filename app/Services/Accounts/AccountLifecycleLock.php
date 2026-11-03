<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use RuntimeException;

/**
 * Serializes account-owned writes with deletion and credential transitions.
 * Anonymous learners do not share this lock.
 */
final class AccountLifecycleLock
{
    private static int $lockDepth = 0;

    public static function withLock(callable $operation): mixed
    {
        if (self::$lockDepth > 0) {
            self::$lockDepth++;
            try {
                return $operation();
            } finally {
                self::$lockDepth--;
            }
        }

        $directory = (new AccountRepository())->directoryPath();
        $path = $directory . DIRECTORY_SEPARATOR . 'lifecycle.lock';
        if (is_link($path)) {
            throw new RuntimeException('Private account lifecycle storage contains an unsafe symbolic link.');
        }
        $handle = fopen($path, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Private account lifecycle storage is unavailable.');
        }
        @chmod($path, 0600);
        $permissions = @fileperms($path);
        if (!is_int($permissions) || ($permissions & 0077) !== 0) {
            fclose($handle);
            throw new RuntimeException('Private account lifecycle storage permissions are insufficient.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Private account lifecycle storage is busy.');
            }
            self::$lockDepth = 1;
            try {
                return $operation();
            } finally {
                self::$lockDepth = 0;
            }
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function withWritableLearner(string $learnerId, callable $operation): mixed
    {
        if (preg_match('/\Aaccount-learner-[a-f0-9]{32}\z/', $learnerId) !== 1) {
            return $operation();
        }

        return self::withLock(static function () use ($learnerId, $operation): mixed {
            $account = (new AccountRepository())->findByLearnerId($learnerId);
            if (!is_array($account) || ($account['status'] ?? null) !== 'active') {
                throw new RuntimeException('Account data is unavailable for writing.');
            }

            // The identity may have been resolved before this process acquired the
            // lifecycle lock. Recheck the session epoch while holding the lock so
            // a password reset or account transition cannot race a stale write.
            $sessionAccount = AccountSessionService::current();
            if (!is_array($sessionAccount)
                || !hash_equals($learnerId, (string) ($sessionAccount['learner_id'] ?? ''))) {
                throw new RuntimeException('The authenticated account session is no longer valid for writing.');
            }

            return $operation();
        });
    }
}
