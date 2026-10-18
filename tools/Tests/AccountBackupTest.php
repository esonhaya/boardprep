<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Accounts\AccountBackupCrypto;
use App\Services\Accounts\AccountBackupService;
use App\Services\Accounts\AccountDeletionService;
use App\Services\Accounts\AccountRepository;
use App\Services\Accounts\AccountRestoreService;
use App\Services\Accounts\AccountService;

function accountBackupAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function accountBackupThrows(callable $operation, string $category): void
{
    try {
        $operation();
    } catch (RuntimeException $exception) {
        accountBackupAssert(str_contains($exception->getMessage(), $category), 'Expected ' . $category . ', received a different safe failure.');
        return;
    }
    throw new RuntimeException('Expected failure ' . $category . ' did not occur.');
}

function accountBackupWritePayloadFixture(string $path, array $payload, string $passphrase): void
{
    $plaintext = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    try {
        $envelope = AccountBackupCrypto::encrypt($plaintext, $passphrase);
    } finally {
        if ($plaintext !== '' && function_exists('sodium_memzero')) {
            sodium_memzero($plaintext);
        }
    }
    file_put_contents($path, json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    chmod($path, 0600);
}

$fixture = sys_get_temp_dir() . '/boardprep-account-backup-' . bin2hex(random_bytes(8));
$storage = $fixture . '/storage';
$private = $fixture . '/private';
$sessions = $fixture . '/sessions';
$restoreParent = $private . '/restore';
mkdir($storage, 0700, true);
mkdir($private, 0700, true);
mkdir($sessions, 0700, true);
mkdir($restoreParent, 0700, true);
foreach ([
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'BOARDPREP_ACCOUNTS_ENABLED' => 'true',
    'DB_DRIVER' => 'json',
    'APP_STORAGE_PATH' => $storage,
    'APP_SESSION_PATH' => $sessions,
] as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}
App::boot();

try {
    $passphrase = 'Batch693 synthetic fixture backup passphrase';
    if (!AccountBackupCrypto::available()) {
        accountBackupThrows(
            static fn() => AccountBackupCrypto::encrypt('synthetic', $passphrase),
            'DEPENDENCY_MISSING'
        );
        echo "[PASS] ext-sodium is unavailable in this PHP runtime and account backup fails closed with DEPENDENCY_MISSING.\n";
        return;
    }

    $password = 'Batch693 synthetic backup account password';
    $registered = (new AccountService())->register('batch693-backup', $password);
    $account = (new AccountRepository())->findByUsername('batch693-backup');
    accountBackupAssert(is_array($registered['account']) && is_array($account), 'Synthetic backup account could not be created.');
    $learnerId = (string) $account['learner_id'];
    App::storage()->create('attempts', [
        'id' => 'batch693-backup-attempt',
        'user_id' => $learnerId,
        'session_id' => 'synthetic-private-quiz-session',
        'question_ids' => [101, 102],
        'score' => 1,
        'total' => 2,
    ]);
    App::storage()->create('weakness', [
        'id' => $learnerId . '|backup-topic',
        'user_id' => $learnerId,
        'topic' => 'synthetic-backup-topic',
        'wrong' => 1,
        'correct' => 0,
    ]);
    App::storage()->create('flashcard_state', [
        'id' => $learnerId . '|backup-card',
        'user_id' => $learnerId,
        'card_key' => 'synthetic-backup-card',
        'review_count' => 3,
        'state' => 'review',
    ]);

    $backupPath = $private . '/account-one.bpk';
    $backupPathTwo = $private . '/account-two.bpk';
    $backup = new AccountBackupService();
    $created = $backup->create($account['account_id'], $backupPath, $passphrase);
    $backup->create($account['account_id'], $backupPathTwo, $passphrase);
    $archive = (string) file_get_contents($backupPath);
    $archiveTwo = (string) file_get_contents($backupPathTwo);
    accountBackupAssert($archive !== $archiveTwo, 'Independent backups reused identical encrypted data, salt, or nonce.');
    $envelopeOne = json_decode($archive, true, 32, JSON_THROW_ON_ERROR);
    $envelopeTwo = json_decode($archiveTwo, true, 32, JSON_THROW_ON_ERROR);
    accountBackupAssert(($envelopeOne['crypto']['salt'] ?? null) !== ($envelopeTwo['crypto']['salt'] ?? null)
        && ($envelopeOne['crypto']['nonce'] ?? null) !== ($envelopeTwo['crypto']['nonce'] ?? null),
        'Independent backups did not receive independent KDF salts and AEAD nonces.');
    accountBackupAssert((fileperms($backupPath) & 0077) === 0, 'Encrypted archive permissions are not owner-only.');
    foreach ([$password, $registered['recovery_codes'][0], (string) $account['password_hash'], $learnerId, 'synthetic-private-quiz-session'] as $secret) {
        accountBackupAssert(!str_contains($archive, $secret), 'Encrypted account archive exposed plaintext credential or learner data.');
    }
    accountBackupAssert(($created['record_counts']['quiz_attempts'] ?? null) === 1
        && ($created['record_counts']['weakness_summaries'] ?? null) === 1
        && ($created['record_counts']['flashcard_reviews'] ?? null) === 1,
        'Encrypted backup inventory omitted owned records.');
    $verified = $backup->verify($backupPath, $passphrase);
    accountBackupAssert(($verified['record_counts']['credential_profile'] ?? null) === 1, 'Encrypted backup manifest did not verify.');
    accountBackupThrows(static fn() => $backup->verify($backupPath, 'incorrect synthetic fixture passphrase'), 'BACKUP_AUTHENTICATION_FAILED');

    $envelope = json_decode($archive, true, 32, JSON_THROW_ON_ERROR);
    $ciphertext = base64_decode($envelope['payload'], true);
    accountBackupAssert(is_string($ciphertext) && strlen($ciphertext) > 32, 'Encrypted archive ciphertext was unexpectedly short.');
    $ciphertext[strlen($ciphertext) - 1] = chr(ord($ciphertext[strlen($ciphertext) - 1]) ^ 1);
    $envelope['payload'] = base64_encode($ciphertext);
    $tamperedPath = $private . '/tampered.bpk';
    file_put_contents($tamperedPath, json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    chmod($tamperedPath, 0600);
    accountBackupThrows(static fn() => $backup->verify($tamperedPath, $passphrase), 'BACKUP_AUTHENTICATION_FAILED');

    $truncatedPath = $private . '/truncated.bpk';
    file_put_contents($truncatedPath, substr($archive, 0, -29));
    chmod($truncatedPath, 0600);
    accountBackupThrows(static fn() => $backup->verify($truncatedPath, $passphrase), 'BACKUP_FORMAT_UNSUPPORTED');
    $unsupportedEnvelope = json_decode($archive, true, 32, JSON_THROW_ON_ERROR);
    $unsupportedEnvelope['schema_version'] = 99;
    $unsupportedPath = $private . '/unsupported.bpk';
    file_put_contents($unsupportedPath, json_encode($unsupportedEnvelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    chmod($unsupportedPath, 0600);
    accountBackupThrows(static fn() => $backup->verify($unsupportedPath, $passphrase), 'BACKUP_FORMAT_UNSUPPORTED');

    $headerTamper = json_decode($archive, true, 32, JSON_THROW_ON_ERROR);
    $headerTamper['crypto']['nonce'] = base64_encode(str_repeat("\0", SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES));
    $headerTamperPath = $private . '/header-tampered.bpk';
    file_put_contents($headerTamperPath, json_encode($headerTamper, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    chmod($headerTamperPath, 0600);
    accountBackupThrows(static fn() => $backup->verify($headerTamperPath, $passphrase), 'BACKUP_AUTHENTICATION_FAILED');

    $decodedPayload = AccountBackupCrypto::decrypt($archive, $passphrase);
    accountBackupAssert(($decodedPayload['manifest']['included_collections'] ?? null) === [
        'quiz_attempts', 'weakness_summaries', 'flashcard_reviews', 'credential_profile',
    ], 'Encrypted backup manifest did not match the supported restore inventory.');
    $badManifest = $decodedPayload;
    $badManifest['manifest']['record_counts']['quiz_attempts'] = 99;
    $badManifestPath = $private . '/manifest-mismatch.bpk';
    accountBackupWritePayloadFixture($badManifestPath, $badManifest, $passphrase);
    accountBackupThrows(static fn() => $backup->verify($badManifestPath, $passphrase), 'RESTORE_MANIFEST_MISMATCH');

    $missingCollection = $decodedPayload;
    unset($missingCollection['collections']['flashcard_reviews']);
    $missingCollectionPath = $private . '/missing-collection.bpk';
    accountBackupWritePayloadFixture($missingCollectionPath, $missingCollection, $passphrase);
    accountBackupThrows(static fn() => $backup->verify($missingCollectionPath, $passphrase), 'RESTORE_SCHEMA_UNSUPPORTED');

    $ownershipMismatch = $decodedPayload;
    $ownershipMismatch['collections']['quiz_attempts'][0]['user_id'] = 'account-learner-' . str_repeat('f', 32);
    $ownershipMismatchPath = $private . '/ownership-mismatch.bpk';
    accountBackupWritePayloadFixture($ownershipMismatchPath, $ownershipMismatch, $passphrase);
    accountBackupThrows(static fn() => $backup->verify($ownershipMismatchPath, $passphrase), 'RESTORE_OWNERSHIP_INVALID');

    $outsideWebRoot = dirname(__DIR__, 2) . '/public/batch693-synthetic-account-backup.bpk';
    accountBackupThrows(static fn() => $backup->create($account['account_id'], $outsideWebRoot, $passphrase), 'BACKUP_PATH_UNSAFE');
    $symlinkArchive = $private . '/linked-backup.bpk';
    if (function_exists('symlink') && @symlink($backupPath, $symlinkArchive)) {
        accountBackupThrows(static fn() => $backup->verify($symlinkArchive, $passphrase), 'BACKUP_PATH_UNSAFE');
    }

    $destination = $restoreParent . '/restored-account';
    $restore = new AccountRestoreService();
    $publicRestoreDestination = dirname(__DIR__, 2) . '/public/batch693-synthetic-restore';
    accountBackupThrows(
        static fn() => $restore->restore($backupPath, $publicRestoreDestination, $passphrase, true),
        'RESTORE_PATH_UNSAFE'
    );
    $dryRun = $restore->restore($backupPath, $destination, $passphrase, true);
    accountBackupAssert($dryRun['dry_run'] && !file_exists($destination), 'Restore dry-run modified its destination.');
    accountBackupThrows(
        static fn() => $restore->restore($backupPath, $restoreParent . '/../traversal', $passphrase, true),
        'RESTORE_PATH_UNSAFE'
    );
    $symlinkParent = $private . '/restore-link';
    if (function_exists('symlink') && @symlink($restoreParent, $symlinkParent)) {
        accountBackupThrows(
            static fn() => $restore->restore($backupPath, $symlinkParent . '/linked-destination', $passphrase, true),
            'RESTORE_PATH_UNSAFE'
        );
    }

    $sourceHashes = [];
    foreach (['private/learner-accounts/accounts.json', 'attempts.json', 'weakness.json', 'flashcard_state.json'] as $relative) {
        $sourceHashes[$relative] = hash_file('sha256', $storage . '/' . $relative);
    }
    $interrupted = new AccountRestoreService(static function (string $file): void {
        if ($file === 'flashcard_reviews') {
            throw new RuntimeException('synthetic restore interruption');
        }
    });
    accountBackupThrows(static fn() => $interrupted->restore($backupPath, $destination, $passphrase, false, true), 'synthetic restore interruption');
    accountBackupAssert(!file_exists($destination), 'Interrupted restore published a partial destination.');
    accountBackupAssert((glob($restoreParent . '/.boardprep-restore-*') ?: []) === [], 'Interrupted restore left plaintext staging data behind.');
    foreach ($sourceHashes as $relative => $hash) {
        accountBackupAssert(hash_file('sha256', $storage . '/' . $relative) === $hash, 'Interrupted restore changed source application data.');
    }

    $restored = $restore->restore($backupPath, $destination, $passphrase, false, true);
    accountBackupAssert(!$restored['dry_run'] && is_dir($destination), 'Atomic isolated restore did not commit a destination.');
    accountBackupAssert((fileperms($destination) & 0077) === 0
        && (fileperms($destination . '/private/learner-accounts/accounts.json') & 0077) === 0,
        'Restored directory or credential store is not private.');
    $restoredAccounts = json_decode((string) file_get_contents($destination . '/private/learner-accounts/accounts.json'), true, 64, JSON_THROW_ON_ERROR);
    accountBackupAssert(isset($restoredAccounts['accounts']['batch693-backup'])
        && $restoredAccounts['accounts']['batch693-backup']['learner_id'] === $learnerId,
        'Restored credentials and learner identity do not match.');
    accountBackupThrows(static fn() => $restore->restore($backupPath, $destination, $passphrase, false, true), 'RESTORE_DESTINATION_CONFLICT');
    foreach ($sourceHashes as $relative => $hash) {
        accountBackupAssert(hash_file('sha256', $storage . '/' . $relative) === $hash, 'Restore changed source application data.');
    }

    $deletionWorker = __DIR__ . '/AccountDeletionRaceWorker.php';
    $workerPipes = [];
    $workerProcess = null;
    $lockProbe = [];
    $racedArchivePath = $private . '/backup-deletion-race.bpk';
    $username = 'batch693-backup';
    $racedBackup = new AccountBackupService(static function () use (&$workerPipes, &$workerProcess, &$lockProbe, $deletionWorker, $username, $password): void {
        $workerProcess = proc_open([PHP_BINARY, $deletionWorker, 'request-delete'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $workerPipes);
        if (!is_resource($workerProcess)) {
            throw new RuntimeException('Could not start synthetic backup/deletion race worker.');
        }
        fwrite($workerPipes[0], $username . "\n" . $password . "\n");
        fclose($workerPipes[0]);
        $lockProbe = [trim((string) fgets($workerPipes[1])), trim((string) fgets($workerPipes[1]))];
    });
    $racedBackup->create((string) $account['account_id'], $racedArchivePath, $passphrase);
    $workerOutput = is_resource($workerProcess) ? (string) stream_get_contents($workerPipes[1]) : '';
    $workerError = is_resource($workerProcess) ? (string) stream_get_contents($workerPipes[2]) : '';
    if (is_resource($workerProcess)) {
        fclose($workerPipes[1]);
        fclose($workerPipes[2]);
        $workerStatus = proc_close($workerProcess);
    } else {
        $workerStatus = 1;
    }
    $workerError = preg_replace('/^WARNING: linker:.*(?:\r?\n|$)/m', '', trim($workerError)) ?? '';
    accountBackupAssert($lockProbe === ['DELETION_REQUEST_ATTEMPTING', 'DELETION_REQUEST_BLOCKED_BY_LIFECYCLE_LOCK'], 'A concurrent deletion request was not blocked while the backup snapshot and archive were committed.');
    accountBackupAssert($workerStatus === 0 && $workerError === '' && str_contains($workerOutput, 'DELETION_REQUESTED'), 'Concurrent deletion request did not resume after backup completion.');
    accountBackupAssert(($repository->findById((string) $account['account_id'])['status'] ?? null) === 'deletion_requested', 'Concurrent deletion request did not persist its locked state.');
    $deletion = (new AccountDeletionService())->process((string) $account['account_id']);
    accountBackupAssert(($deletion['status'] ?? null) === 'deletion_completed', 'Synthetic backup race account did not complete deletion.');
    accountBackupThrows(
        static fn() => $restore->restore($backupPath, $restoreParent . '/restored-after-deletion', $passphrase, true),
        'RESTORE_DELETED_ACCOUNT'
    );

    echo "[PASS] Authenticated encrypted backup uses per-archive salt/nonce, strict permissions, manifest counts, header/tag tamper rejection, and fail-closed schema checks.\n";
    echo "[PASS] Wrong passphrase, truncation, unsupported version, malformed manifest, missing collection, ownership mismatch, unsafe paths/symlinks, and restore collisions are rejected.\n";
    echo "[PASS] Dry-run is read-only; interrupted staged restore rolls back cleanly and atomic isolated restore preserves its source.\n";
    echo "[PASS] Backup serialization blocks a concurrent deletion request until the archive commits, then a completed deletion tombstone rejects credential restoration.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($fixture);
    foreach (['APP_ENV','APP_DEBUG','BOARDPREP_ACCOUNTS_ENABLED','DB_DRIVER','APP_STORAGE_PATH','APP_SESSION_PATH'] as $key) {
        putenv($key);
        unset($_ENV[$key]);
    }
}
