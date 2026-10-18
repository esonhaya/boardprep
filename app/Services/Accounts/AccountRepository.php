<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Core\App;
use Closure;
use JsonException;
use RuntimeException;

/**
 * Private, atomic credential/profile store. It is deliberately separate from
 * canonical content and learner collections and is never served by the web
 * root. The existing JSON storage architecture is the default deployment
 * mode; this small store adds one lock around uniqueness and credential
 * transitions instead of composing unsafe read-then-write operations.
 */
final class AccountRepository
{
    private string $directory;
    private string $dataPath;
    private string $lockPath;
    private ?Closure $writeFault;

    /** @param null|callable(string):void $writeFault Test-only atomic-write fault injection. */
    public function __construct(?string $directory = null, ?callable $writeFault = null)
    {
        $this->writeFault = $writeFault === null ? null : Closure::fromCallable($writeFault);
        if ($directory === null) {
            $config = (array) App::config('database', []);
            $storage = $config['path'] ?? null;
            if (!is_string($storage) || trim($storage) === '') {
                throw new RuntimeException('Private account storage is not configured.');
            }
            $directory = rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'learner-accounts';
        }

        if (!str_starts_with($directory, DIRECTORY_SEPARATOR)) {
            $directory = getcwd() . DIRECTORY_SEPARATOR . $directory;
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Private account storage is unavailable.');
        }
        $this->protect($directory, 0700);

        $resolved = realpath($directory);
        if (!is_string($resolved) || !$this->isOutsideDocumentRoot($resolved)) {
            throw new RuntimeException('Private account storage must be outside the public document root.');
        }
        if (!is_readable($resolved) || !is_writable($resolved)) {
            throw new RuntimeException('Private account storage permissions are insufficient.');
        }

        $this->directory = $resolved;
        $this->dataPath = $resolved . DIRECTORY_SEPARATOR . 'accounts.json';
        $this->lockPath = $resolved . DIRECTORY_SEPARATOR . 'accounts.lock';
        if (is_link($this->dataPath) || is_link($this->lockPath)) {
            throw new RuntimeException('Private account storage contains an unsafe symbolic link.');
        }
    }

    public function findByUsername(string $username): ?array
    {
        return $this->withLock(LOCK_SH, static function (array $state) use ($username): ?array {
            $record = $state['accounts'][$username] ?? null;
            return is_array($record) ? $record : null;
        });
    }

    public function findById(string $accountId): ?array
    {
        return $this->withLock(LOCK_SH, static function (array $state) use ($accountId): ?array {
            foreach ($state['accounts'] as $record) {
                if (is_array($record) && ($record['account_id'] ?? null) === $accountId) {
                    return $record;
                }
            }
            return null;
        });
    }

    public function findByLearnerId(string $learnerId): ?array
    {
        return $this->withLock(LOCK_SH, static function (array $state) use ($learnerId): ?array {
            foreach ($state['accounts'] as $record) {
                if (is_array($record) && ($record['learner_id'] ?? null) === $learnerId) {
                    return $record;
                }
            }
            return null;
        });
    }

    public function directoryPath(): string
    {
        return $this->directory;
    }

    public function create(array $account): bool
    {
        return $this->mutate(static function (array &$state) use ($account): bool {
            $username = (string) ($account['username'] ?? '');
            if ($username === '' || isset($state['accounts'][$username])) {
                return false;
            }
            foreach ($state['accounts'] as $existing) {
                if (is_array($existing)
                    && ((($existing['account_id'] ?? null) === ($account['account_id'] ?? null))
                        || (($existing['learner_id'] ?? null) === ($account['learner_id'] ?? null)))) {
                    return false;
                }
            }
            $state['accounts'][$username] = $account;
            return true;
        });
    }

    public function updateById(string $accountId, callable $update): ?array
    {
        return $this->mutate(static function (array &$state) use ($accountId, $update): ?array {
            foreach ($state['accounts'] as $username => $account) {
                if (!is_array($account) || ($account['account_id'] ?? null) !== $accountId) {
                    continue;
                }
                $changed = $update($account);
                if (!is_array($changed)
                || ($changed['account_id'] ?? null) !== $accountId
                || ($changed['username'] ?? null) !== $username
                || ($changed['learner_id'] ?? null) !== ($account['learner_id'] ?? null)) {
                    throw new RuntimeException('Account update did not preserve its identity.');
                }
                $state['accounts'][$username] = $changed;
                return $changed;
            }
            return null;
        });
    }

    public function updateByUsername(string $username, callable $update): ?array
    {
        return $this->mutate(static function (array &$state) use ($username, $update): ?array {
            $account = $state['accounts'][$username] ?? null;
            if (!is_array($account)) {
                return null;
            }
            $changed = $update($account);
            if (!is_array($changed)
                || ($changed['account_id'] ?? null) !== ($account['account_id'] ?? null)
                || ($changed['username'] ?? null) !== $username
                || ($changed['learner_id'] ?? null) !== ($account['learner_id'] ?? null)) {
                throw new RuntimeException('Account update did not preserve its identity.');
            }
            $state['accounts'][$username] = $changed;
            return $changed;
        });
    }

    /**
     * Reserve a bounded attempt across all supplied buckets atomically.
     * Bucket keys are one-way digests and never contain usernames or IPs.
     *
     * @param array<string,int> $bucketLimits
     */
    public function reserveAttempts(array $bucketLimits, int $windowSeconds): bool
    {
        return $this->mutate(static function (array &$state) use ($bucketLimits, $windowSeconds): bool {
            $now = time();
            foreach ($bucketLimits as $key => $limit) {
                $seen = is_array($state['rate_limits'][$key] ?? null) ? $state['rate_limits'][$key] : [];
                $seen = array_values(array_filter(
                    $seen,
                    static fn(mixed $timestamp): bool => is_int($timestamp) && $timestamp > $now - $windowSeconds
                ));
                $state['rate_limits'][$key] = $seen;
                if (count($seen) >= $limit) {
                    return false;
                }
            }

            foreach ($bucketLimits as $key => $_limit) {
                $state['rate_limits'][$key][] = $now;
            }
            if (count($state['rate_limits']) > 4096) {
                uasort($state['rate_limits'], static fn(array $a, array $b): int => (max($a ?: [0])) <=> (max($b ?: [0])));
                $state['rate_limits'] = array_slice($state['rate_limits'], -4096, null, true);
            }
            return true;
        });
    }

    public function countAccounts(): int
    {
        return $this->withLock(LOCK_SH, static fn(array $state): int => count(array_filter(
            $state['accounts'],
            static fn(mixed $account): bool => is_array($account) && isset($account['username'])
        )));
    }

    /** @return list<array{account_id:string,status:string}> */
    public function pendingDeletions(): array
    {
        return $this->withLock(LOCK_SH, static function (array $state): array {
            $pending = [];
            foreach ($state['accounts'] as $account) {
                if (!is_array($account)
                    || !in_array(($account['status'] ?? null), ['deletion_requested', 'deletion_processing', 'deletion_failed'], true)
                    || !is_string($account['account_id'] ?? null)) {
                    continue;
                }
                $pending[] = [
                    'account_id' => $account['account_id'],
                    'status' => (string) $account['status'],
                ];
            }
            return $pending;
        });
    }

    /** Replace credentials with a minimal tombstone after owned data is verified absent. */
    public function completeDeletion(string $accountId, string $learnerId): bool
    {
        return $this->mutate(static function (array &$state) use ($accountId, $learnerId): bool {
            foreach ($state['accounts'] as $username => $account) {
                if (!is_array($account)
                    || ($account['account_id'] ?? null) !== $accountId
                    || ($account['learner_id'] ?? null) !== $learnerId) {
                    continue;
                }
                if (($account['status'] ?? null) !== 'deletion_processing') {
                    return ($account['status'] ?? null) === 'deletion_completed';
                }

                $normalizedUsername = strtolower(trim((string) ($account['username'] ?? '')));
                unset($state['accounts'][$username]);
                $state['accounts']['deleted:' . hash('sha256', $accountId)] = [
                    'schema_version' => 1,
                    'account_id' => $accountId,
                    'learner_id' => $learnerId,
                    'status' => 'deletion_completed',
                ];
                foreach ([
                    'reauth-account:' . hash('sha256', strtolower($accountId)),
                    'login-identifier:' . hash('sha256', $normalizedUsername),
                    'recovery-identifier:' . hash('sha256', $normalizedUsername),
                ] as $bucket) {
                    unset($state['rate_limits'][$bucket]);
                }
                return true;
            }

            foreach ($state['accounts'] as $account) {
                if (is_array($account)
                    && ($account['account_id'] ?? null) === $accountId
                    && ($account['status'] ?? null) === 'deletion_completed') {
                    return true;
                }
            }
            return false;
        });
    }

    private function isOutsideDocumentRoot(string $path): bool
    {
        $documentRoot = realpath(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public');
        if (!is_string($documentRoot)) {
            return true;
        }
        $documentRoot = rtrim($documentRoot, DIRECTORY_SEPARATOR);
        return $path !== $documentRoot && !str_starts_with($path, $documentRoot . DIRECTORY_SEPARATOR);
    }

    private function protect(string $path, int $mode): void
    {
        @chmod($path, $mode);
        $permissions = @fileperms($path);
        if (!is_int($permissions)
            || ($permissions & 0077) !== 0
            || ($permissions & 0700) !== ($mode & 0700)) {
            throw new RuntimeException('Private account storage permissions are insufficient.');
        }
    }

    private function withLock(int $mode, callable $callback): mixed
    {
        $handle = fopen($this->lockPath, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Private account storage is unavailable.');
        }
        try {
            $this->protect($this->lockPath, 0600);
        } catch (\Throwable $exception) {
            fclose($handle);
            throw $exception;
        }
        try {
            if (!flock($handle, $mode)) {
                throw new RuntimeException('Private account storage is busy.');
            }
            return $callback($this->readState());
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function mutate(callable $callback): mixed
    {
        $handle = fopen($this->lockPath, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Private account storage is unavailable.');
        }
        try {
            $this->protect($this->lockPath, 0600);
        } catch (\Throwable $exception) {
            fclose($handle);
            throw $exception;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Private account storage is busy.');
            }
            $state = $this->readState();
            $result = $callback($state);
            $this->writeState($state);
            return $result;
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function readState(): array
    {
        if (!is_file($this->dataPath)) {
            return ['schema_version' => 1, 'accounts' => [], 'rate_limits' => []];
        }
        if (is_link($this->dataPath)) {
            throw new RuntimeException('Private account storage contains an unsafe symbolic link.');
        }
        $contents = file_get_contents($this->dataPath);
        if (!is_string($contents)) {
            throw new RuntimeException('Private account storage could not be read.');
        }
        try {
            $state = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Private account storage is corrupt; no account data was changed.', 0, $exception);
        }
        if (!is_array($state)
            || ($state['schema_version'] ?? null) !== 1
            || !is_array($state['accounts'] ?? null)
            || !is_array($state['rate_limits'] ?? null)) {
            throw new RuntimeException('Private account storage has an unsupported format; no account data was changed.');
        }
        return $state;
    }

    private function writeState(array $state): void
    {
        try {
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Private account storage could not be encoded.', 0, $exception);
        }
        $temporary = tempnam($this->directory, '.accounts-');
        if (!is_string($temporary)) {
            throw new RuntimeException('Private account storage could not prepare an atomic write.');
        }
        try {
            $this->protect($temporary, 0600);
            if ($this->writeFault !== null) {
                ($this->writeFault)('before_temporary_write');
            }
            $written = file_put_contents($temporary, $json . "\n", LOCK_EX);
            if ($written === false) {
                throw new RuntimeException('Private account storage could not write its atomic replacement.');
            }
            if ($this->writeFault !== null) {
                ($this->writeFault)('before_atomic_commit');
            }
            if (!rename($temporary, $this->dataPath)) {
                throw new RuntimeException('Private account storage could not complete an atomic write.');
            }
            $this->protect($this->dataPath, 0600);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
