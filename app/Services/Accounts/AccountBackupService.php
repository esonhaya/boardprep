<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use JsonException;
use RuntimeException;

/** Creates and verifies password-encrypted account-only archives. */
final class AccountBackupService
{
    /** @param null|callable():void $afterSnapshot Test-only synchronization point. */
    public function __construct(private $afterSnapshot = null)
    {
    }

    public function create(string $accountId, string $outputPath, string $passphrase): array
    {
        return AccountLifecycleLock::withLock(function () use ($accountId, $outputPath, $passphrase): array {
            AccountBackupCrypto::validatePassphrase($passphrase);
            $account = (new AccountRepository())->findById($accountId);
            if (!is_array($account) || ($account['status'] ?? null) !== 'active') {
                throw new RuntimeException('BACKUP_ACCOUNT_UNAVAILABLE: only an active account can be backed up.');
            }
            $inventory = (new AccountDataInventoryService())->inspectServerAccount($account);
            if (is_callable($this->afterSnapshot)) {
                ($this->afterSnapshot)();
            }
            $rawAccount = $inventory['account'];
            $credential = [
                'account_id' => (string) $rawAccount['account_id'],
                'learner_id' => (string) $rawAccount['learner_id'],
                'username' => (string) $rawAccount['username'],
                'password_hash' => (string) $rawAccount['password_hash'],
                'created_at' => (string) $rawAccount['created_at'],
                'updated_at' => (string) ($rawAccount['updated_at'] ?? $rawAccount['created_at']),
                'auth_version' => (int) ($rawAccount['auth_version'] ?? 1),
                'status' => 'active',
                'preferences' => is_array($rawAccount['preferences'] ?? null) ? $rawAccount['preferences'] : [],
                'recovery_code_hashes' => array_values((array) ($rawAccount['recovery_code_hashes'] ?? [])),
            ];
            $records = $inventory['records'];
            foreach ($records as &$collection) {
                foreach ($collection as &$record) {
                    unset($record['session_id']);
                }
                unset($record);
            }
            unset($collection);

            $counts = [];
            $schemas = [];
            foreach ($inventory['collections'] as $name => $definition) {
                // The encrypted archive calls the credential/profile bundle
                // credential_profile; do not also expose the non-secret export
                // inventory's account_profile name in the backup manifest.
                if ($name === 'account_profile') {
                    continue;
                }
                $counts[$name] = (int) $definition['count'];
                $schemas[$name] = (int) $definition['schema_version'];
            }
            $counts['credential_profile'] = 1;
            $schemas['credential_profile'] = 1;
            $payload = [
                'payload_schema_version' => 1,
                'manifest' => [
                    'format' => 'boardprep-account-backup-payload-v1',
                    'created_at' => gmdate(DATE_ATOM),
                    'included_collections' => array_keys($counts),
                    'record_counts' => $counts,
                    'collection_schema_versions' => $schemas,
                    'excluded' => [
                        'active_php_session_identifiers',
                        'session_authorization_records',
                        'csrf_tokens',
                        'rate_limit_bucket_secrets',
                        'private_encryption_keys',
                        'shared_question_usage_aggregates',
                        'anonymous_learner_records',
                    ],
                ],
                'account' => $credential,
                'collections' => $records,
            ];

            try {
                $plaintext = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('BACKUP_DATA_INVALID: account data could not be encoded.', 0, $exception);
            }
            if (strlen($plaintext) > AccountBackupCrypto::MAX_ARCHIVE_BYTES / 2) {
                throw new RuntimeException('BACKUP_SIZE_LIMIT: account data exceeds the supported archive size.');
            }

            try {
                $envelope = AccountBackupCrypto::encrypt($plaintext, $passphrase);
            } finally {
                if ($plaintext !== '' && function_exists('sodium_memzero')) {
                    sodium_memzero($plaintext);
                }
            }
            try {
                $archive = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            } catch (JsonException $exception) {
                throw new RuntimeException('BACKUP_FORMAT_UNSUPPORTED: encrypted archive could not be encoded.', 0, $exception);
            }
            self::writePrivateFile($outputPath, $archive);

            return ['path' => $outputPath, 'record_counts' => $counts, 'schema_version' => 1];
        });
    }

    /** @return array{record_counts:array<string,int>,schema_version:int,created_at:string} */
    public function verify(string $archivePath, string $passphrase): array
    {
        $archive = self::readPrivateFile($archivePath);
        $payload = AccountBackupCrypto::decrypt($archive, $passphrase);
        AccountRestoreService::validatePayload($payload);
        return [
            'record_counts' => $payload['manifest']['record_counts'],
            'schema_version' => (int) $payload['payload_schema_version'],
            'created_at' => (string) $payload['manifest']['created_at'],
        ];
    }

    public static function readPrivateFile(string $path): string
    {
        $resolved = self::resolvePrivatePath($path, false);
        $size = filesize($resolved);
        if (!is_int($size) || $size < 1 || $size > AccountBackupCrypto::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('BACKUP_FORMAT_UNSUPPORTED: encrypted archive size is invalid.');
        }
        $contents = file_get_contents($resolved);
        if (!is_string($contents)) {
            throw new RuntimeException('BACKUP_READ_FAILED: encrypted archive could not be read.');
        }
        return $contents;
    }

    public static function resolvePrivatePath(string $path, bool $mustNotExist): string
    {
        if (!str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('#(?:^|/)\.\.?(/|$)#', $path) === 1
            || str_contains($path, "\0")) {
            throw new RuntimeException('BACKUP_PATH_UNSAFE: use an absolute path without traversal segments.');
        }
        $parent = dirname($path);
        self::rejectSymlinkComponents($parent);
        $realParent = realpath($parent);
        if (!is_string($realParent) || !is_dir($realParent)) {
            throw new RuntimeException('BACKUP_PATH_UNSAFE: the private parent directory does not exist.');
        }
        self::assertOutsideProtectedRoots($realParent);
        self::assertPrivateDirectory($realParent);
        $target = rtrim($realParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
        if ($mustNotExist && (file_exists($target) || is_link($target))) {
            throw new RuntimeException('BACKUP_DESTINATION_CONFLICT: output already exists; it was not overwritten.');
        }
        if (!$mustNotExist) {
            if (is_link($target) || !is_file($target)) {
                throw new RuntimeException('BACKUP_PATH_UNSAFE: encrypted archive must be a regular private file.');
            }
            $permissions = @fileperms($target);
            if (!is_int($permissions) || ($permissions & 0077) !== 0) {
                throw new RuntimeException('BACKUP_PERMISSIONS_UNSAFE: encrypted archive permissions must be owner-only.');
            }
            $resolved = realpath($target);
            if (!is_string($resolved)) {
                throw new RuntimeException('BACKUP_READ_FAILED: encrypted archive path could not be resolved.');
            }
            self::assertOutsideProtectedRoots(dirname($resolved));
            return $resolved;
        }
        return $target;
    }

    private static function writePrivateFile(string $path, string $contents): void
    {
        $target = self::resolvePrivatePath($path, true);
        $temporary = tempnam(dirname($target), '.boardprep-account-backup-');
        if (!is_string($temporary)) {
            throw new RuntimeException('BACKUP_WRITE_FAILED: private temporary archive could not be created.');
        }
        try {
            @chmod($temporary, 0600);
            $handle = fopen($temporary, 'wb');
            if ($handle === false) {
                throw new RuntimeException('BACKUP_WRITE_FAILED: private encrypted archive could not be opened safely.');
            }
            try {
                $offset = 0;
                $length = strlen($contents);
                while ($offset < $length) {
                    $written = fwrite($handle, substr($contents, $offset));
                    if (!is_int($written) || $written < 1) {
                        throw new RuntimeException('BACKUP_WRITE_FAILED: private encrypted archive could not be written completely.');
                    }
                    $offset += $written;
                }
                if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                    throw new RuntimeException('BACKUP_WRITE_FAILED: private encrypted archive could not be synchronized.');
                }
            } finally {
                fclose($handle);
            }
            $permissions = @fileperms($temporary);
            if (!is_int($permissions) || ($permissions & 0077) !== 0) {
                throw new RuntimeException('BACKUP_WRITE_FAILED: private encrypted archive could not be written safely.');
            }
            if (!link($temporary, $target)) {
                throw new RuntimeException('BACKUP_DESTINATION_CONFLICT: output could not be committed without overwrite.');
            }
            @unlink($temporary);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private static function assertPrivateDirectory(string $directory): void
    {
        $permissions = @fileperms($directory);
        if (!is_int($permissions)
            || ($permissions & 0077) !== 0
            || !is_readable($directory)
            || !is_writable($directory)) {
            throw new RuntimeException('BACKUP_PERMISSIONS_UNSAFE: parent directory must be readable, writable, and owner-only.');
        }
    }

    private static function assertOutsideProtectedRoots(string $path): void
    {
        $repository = realpath(dirname(__DIR__, 3));
        $documentRoot = realpath(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public');
        foreach ([$repository, $documentRoot] as $protected) {
            if (is_string($protected)
                && ($path === $protected || str_starts_with($path, rtrim($protected, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('BACKUP_PATH_UNSAFE: account archives and restore destinations must be outside the repository and web root.');
            }
        }
    }

    private static function rejectSymlinkComponents(string $path): void
    {
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : '';
        $current = $absolute;
        foreach (array_filter(explode(DIRECTORY_SEPARATOR, $path), static fn(string $part): bool => $part !== '') as $part) {
            $current = rtrim($current, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $part;
            if (is_link($current)) {
                throw new RuntimeException('BACKUP_PATH_UNSAFE: symbolic-link path components are not allowed.');
            }
        }
    }
}
