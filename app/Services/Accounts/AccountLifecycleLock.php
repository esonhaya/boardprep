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
    public static function withLock(callable $operation): mixed
    {
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
            return $operation();
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
            return $operation();
        });
    }
}
