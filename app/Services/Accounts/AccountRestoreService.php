<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use JsonException;
use RuntimeException;
use Throwable;

/** Restores only into a new, private JSON destination using staged directory commit. */
final class AccountRestoreService
{
    private const COLLECTIONS = [
        'quiz_attempts' => 'attempts',
        'weakness_summaries' => 'weakness',
        'flashcard_reviews' => 'flashcard_state',
    ];

    /** @param null|callable(string):void $afterFile Test-only interruption point. */
    public function __construct(private $afterFile = null)
    {
    }

    /** @return array{dry_run:bool,destination:string,record_counts:array<string,int>,schema_version:int} */
    public function restore(string $archivePath, string $destination, string $passphrase, bool $dryRun, bool $explicitIsolatedRestore = false): array
    {
        if (!$dryRun && !$explicitIsolatedRestore) {
            throw new RuntimeException('RESTORE_CONFIRMATION_REQUIRED: isolated restore requires explicit operator confirmation.');
        }
        $archive = AccountBackupService::readPrivateFile($archivePath);
        $payload = AccountBackupCrypto::decrypt($archive, $passphrase);
        self::validatePayload($payload);
        $destination = self::validateDestination($destination);
        return AccountLifecycleLock::withLock(function () use ($payload, $destination, $dryRun): array {
            return $this->restoreValidatedPayload($payload, $destination, $dryRun);
        });
    }

    /** @param array<string,mixed> $payload @return array{dry_run:bool,destination:string,record_counts:array<string,int>,schema_version:int} */
    private function restoreValidatedPayload(array $payload, string $destination, bool $dryRun): array
    {
        $existing = (new AccountRepository())->findById((string) $payload['account']['account_id']);
        if (is_array($existing) && ($existing['status'] ?? null) === 'deletion_completed') {
            throw new RuntimeException('RESTORE_DELETED_ACCOUNT: a completed account deletion permanently blocks restoration of these credentials.');
        }
        $report = [
            'dry_run' => $dryRun,
            'destination' => $destination,
            'record_counts' => $payload['manifest']['record_counts'],
            'schema_version' => (int) $payload['payload_schema_version'],
        ];
        if ($dryRun) {
            return $report;
        }

        $lockPath = dirname($destination) . DIRECTORY_SEPARATOR . '.' . basename($destination) . '.boardprep-restore.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('RESTORE_PATH_UNSAFE: destination lock is an unsafe symbolic link.');
        }
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('RESTORE_LOCK_FAILED: private destination lock could not be opened.');
        }
        @chmod($lockPath, 0600);
        $lockPermissions = @fileperms($lockPath);
        if (!is_int($lockPermissions) || ($lockPermissions & 0077) !== 0) {
            fclose($lock);
            throw new RuntimeException('RESTORE_PERMISSIONS_UNSAFE: destination lock is not private.');
        }

        $stage = dirname($destination) . DIRECTORY_SEPARATOR . '.boardprep-restore-' . bin2hex(random_bytes(12));
        $createdDirectories = [];
        $createdFiles = [];
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('RESTORE_LOCK_FAILED: private destination is busy.');
            }
            if (file_exists($destination) || is_link($destination)) {
                throw new RuntimeException('RESTORE_DESTINATION_CONFLICT: destination already exists and was not changed.');
            }
            if (!mkdir($stage, 0700)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: private staging directory could not be created.');
            }
            $createdDirectories[] = $stage;
            $privateDirectory = $stage . DIRECTORY_SEPARATOR . 'private';
            $accountDirectory = $privateDirectory . DIRECTORY_SEPARATOR . 'learner-accounts';
            if (!mkdir($privateDirectory, 0700)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: private credential directories could not be created.');
            }
            $createdDirectories[] = $privateDirectory;
            if (!mkdir($accountDirectory, 0700)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: private credential directories could not be created.');
            }
            $createdDirectories[] = $accountDirectory;

            $username = (string) $payload['account']['username'];
            self::writeJson($accountDirectory . DIRECTORY_SEPARATOR . 'accounts.json', [
                'schema_version' => 1,
                'accounts' => [$username => $payload['account']],
                'rate_limits' => [],
            ], $createdFiles);
            $this->interruptAfter('credential_profile');

            foreach (self::COLLECTIONS as $exportName => $storageName) {
                self::writeJson($stage . DIRECTORY_SEPARATOR . $storageName . '.json', $payload['collections'][$exportName], $createdFiles);
                $this->interruptAfter($exportName);
            }
            self::writeJson($stage . DIRECTORY_SEPARATOR . 'restore-report.json', [
                'format' => 'boardprep-account-restore-report-v1',
                'restored_at' => gmdate(DATE_ATOM),
                'schema_version' => (int) $payload['payload_schema_version'],
                'record_counts' => $payload['manifest']['record_counts'],
                'mode' => 'new isolated private JSON destination; not activated',
            ], $createdFiles);
            $this->interruptAfter('restore_report');

            if (file_exists($destination) || is_link($destination) || !rename($stage, $destination)) {
                throw new RuntimeException('RESTORE_COMMIT_FAILED: destination changed or atomic commit failed; source state is unchanged.');
            }
            $createdDirectories = [];
            $createdFiles = [];
            $report['dry_run'] = false;
            return $report;
        } catch (Throwable $exception) {
            self::removeStaging($createdFiles, $createdDirectories);
            throw $exception;
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array<string,mixed> $payload */
    public static function validatePayload(array $payload): void
    {
        if (array_diff(['payload_schema_version', 'manifest', 'account', 'collections'], array_keys($payload)) !== []
            || count($payload) !== 4
            || ($payload['payload_schema_version'] ?? null) !== 1
            || !is_array($payload['manifest'] ?? null)
            || !is_array($payload['account'] ?? null)
            || !is_array($payload['collections'] ?? null)) {
            throw new RuntimeException('RESTORE_SCHEMA_UNSUPPORTED: required account backup collections or schema are missing.');
        }

        $manifest = $payload['manifest'];
        $expectedNames = ['quiz_attempts', 'weakness_summaries', 'flashcard_reviews', 'credential_profile'];
        if (($manifest['format'] ?? null) !== 'boardprep-account-backup-payload-v1'
            || !is_string($manifest['created_at'] ?? null)
            || ($manifest['included_collections'] ?? null) !== $expectedNames
            || !is_array($manifest['record_counts'] ?? null)
            || !is_array($manifest['collection_schema_versions'] ?? null)
            || $manifest['collection_schema_versions'] !== array_fill_keys($expectedNames, 1)) {
            throw new RuntimeException('RESTORE_SCHEMA_UNSUPPORTED: encrypted backup manifest is incomplete or unsupported.');
        }
        $account = $payload['account'];
        $expectedAccountFields = [
            'account_id', 'learner_id', 'username', 'password_hash', 'created_at', 'updated_at',
            'auth_version', 'status', 'preferences', 'recovery_code_hashes',
        ];
        if (array_diff($expectedAccountFields, array_keys($account)) !== []
            || count($account) !== count($expectedAccountFields)
            || preg_match('/\Aaccount-[a-f0-9]{32}\z/', (string) ($account['account_id'] ?? '')) !== 1
            || preg_match('/\Aaccount-learner-[a-f0-9]{32}\z/', (string) ($account['learner_id'] ?? '')) !== 1
            || AccountService::normalizeUsername((string) ($account['username'] ?? '')) !== ($account['username'] ?? null)
            || !is_string($account['password_hash'] ?? null)
            || (password_get_info((string) ($account['password_hash'] ?? ''))['algoName'] ?? 'unknown') === 'unknown'
            || ($account['status'] ?? null) !== 'active'
            || !is_int($account['auth_version'] ?? null)
            || $account['auth_version'] < 1
            || !is_array($account['preferences'] ?? null)
            || !is_array($account['recovery_code_hashes'] ?? null)
            || count($account['recovery_code_hashes']) > 8) {
            throw new RuntimeException('RESTORE_CREDENTIAL_INVALID: account credentials or profile failed validation.');
        }
        $recoverySeen = [];
        foreach ($account['recovery_code_hashes'] as $hash) {
            if (!is_string($hash) || preg_match('/\A[a-f0-9]{64}\z/', $hash) !== 1 || isset($recoverySeen[$hash])) {
                throw new RuntimeException('RESTORE_CREDENTIAL_INVALID: recovery-code digests failed validation.');
            }
            $recoverySeen[$hash] = true;
        }

        if (array_keys($payload['collections']) !== ['quiz_attempts', 'weakness_summaries', 'flashcard_reviews']) {
            throw new RuntimeException('RESTORE_SCHEMA_UNSUPPORTED: required learner data collections are missing or unexpected.');
        }
        foreach (self::COLLECTIONS as $exportName => $_storageName) {
            $records = $payload['collections'][$exportName];
            if (!is_array($records) || !array_is_list($records)) {
                throw new RuntimeException('RESTORE_SCHEMA_UNSUPPORTED: a learner collection is not a record list.');
            }
            if (($manifest['record_counts'][$exportName] ?? null) !== count($records)) {
                throw new RuntimeException('RESTORE_MANIFEST_MISMATCH: collection count differs from the encrypted manifest.');
            }
            $seen = [];
            foreach ($records as $record) {
                if (!is_array($record)
                    || ($record['user_id'] ?? null) !== $account['learner_id']
                    || array_key_exists('session_id', $record)
                    || (!is_string($record['id'] ?? null) && !is_int($record['id'] ?? null))
                    || trim((string) ($record['id'] ?? '')) === ''
                    || isset($seen[(string) $record['id']])) {
                    throw new RuntimeException('RESTORE_OWNERSHIP_INVALID: learner records have mismatched or duplicate ownership references.');
                }
                $seen[(string) $record['id']] = true;
            }
        }
        if (($manifest['record_counts']['credential_profile'] ?? null) !== 1
            || count($manifest['record_counts']) !== count($expectedNames)) {
            throw new RuntimeException('RESTORE_MANIFEST_MISMATCH: account profile count is invalid.');
        }
    }

    private static function validateDestination(string $path): string
    {
        if (!str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('#(?:^|/)\.\.?(/|$)#', $path) === 1
            || str_contains($path, "\0")) {
            throw new RuntimeException('RESTORE_PATH_UNSAFE: use an absolute destination path without traversal segments.');
        }
        $parent = dirname($path);
        self::rejectSymlinkComponents($parent);
        $resolvedParent = realpath($parent);
        if (!is_string($resolvedParent) || !is_dir($resolvedParent)) {
            throw new RuntimeException('RESTORE_PATH_UNSAFE: private destination parent does not exist.');
        }
        $permissions = @fileperms($resolvedParent);
        if (!is_int($permissions) || ($permissions & 0077) !== 0 || !is_writable($resolvedParent)) {
            throw new RuntimeException('RESTORE_PERMISSIONS_UNSAFE: destination parent must be writable and owner-only.');
        }
        $target = rtrim($resolvedParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException('RESTORE_DESTINATION_CONFLICT: destination already exists and was not changed.');
        }
        self::assertOutsideProtectedRoots($resolvedParent);
        return $target;
    }

    private static function writeJson(string $path, array $data, array &$createdFiles): void
    {
        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('RESTORE_DATA_INVALID: a validated collection could not be encoded.', 0, $exception);
        }
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('RESTORE_STAGE_FAILED: private staged file could not be created.');
        }
        $createdFiles[] = $path;
        @chmod($path, 0600);
        try {
            $offset = 0;
            $length = strlen($json);
            while ($offset < $length) {
                $written = fwrite($handle, substr($json, $offset, 65_536));
                if (!is_int($written) || $written < 1) {
                    throw new RuntimeException('RESTORE_STAGE_FAILED: private staged file could not be written completely.');
                }
                $offset += $written;
            }
            if (!fflush($handle)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: staged file could not be flushed.');
            }
            if (function_exists('fsync') && !fsync($handle)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: staged file could not be synchronized.');
            }
        } finally {
            fclose($handle);
        }
        $permissions = @fileperms($path);
        if (!is_int($permissions) || ($permissions & 0077) !== 0) {
            throw new RuntimeException('RESTORE_PERMISSIONS_UNSAFE: staged file permissions are not private.');
        }
    }

    private function interruptAfter(string $name): void
    {
        if (is_callable($this->afterFile)) {
            ($this->afterFile)($name);
        }
    }

    private static function removeStaging(array $files, array $directories): void
    {
        foreach (array_reverse($files) as $file) {
            if (is_file($file) && !is_link($file)) {
                @unlink($file);
            }
        }
        foreach (array_reverse($directories) as $directory) {
            if (is_dir($directory) && !is_link($directory)) {
                @rmdir($directory);
            }
        }
    }

    private static function assertOutsideProtectedRoots(string $path): void
    {
        $repository = realpath(dirname(__DIR__, 3));
        $documentRoot = realpath(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public');
        foreach ([$repository, $documentRoot] as $protected) {
            if (is_string($protected)
                && ($path === $protected || str_starts_with($path, rtrim($protected, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('RESTORE_PATH_UNSAFE: restore destinations must be outside the repository and web root.');
            }
        }
    }

    private static function rejectSymlinkComponents(string $path): void
    {
        $current = '';
        foreach (array_filter(explode(DIRECTORY_SEPARATOR, $path), static fn(string $part): bool => $part !== '') as $part) {
            $current = rtrim($current, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $part;
            if (is_link($current)) {
                throw new RuntimeException('RESTORE_PATH_UNSAFE: symbolic-link path components are not allowed.');
            }
        }
    }
}
