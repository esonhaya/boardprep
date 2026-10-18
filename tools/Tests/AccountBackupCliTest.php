<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Accounts\AccountBackupCrypto;
use App\Services\Accounts\AccountRepository;
use App\Services\Accounts\AccountService;

function accountBackupCliAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{status:int,stdout:string,stderr:string} */
function accountBackupCliRun(string $script, string $root, array $environment, array $arguments, ?string $passphrase = null): array
{
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    if ($passphrase !== null) {
        $descriptors[3] = ['pipe', 'r'];
    }
    $process = proc_open([PHP_BINARY, $script, ...$arguments], $descriptors, $pipes, $root, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the isolated account backup CLI fixture.');
    }
    fclose($pipes[0]);
    if ($passphrase !== null) {
        fwrite($pipes[3], $passphrase . "\n");
        fclose($pipes[3]);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [
        'status' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

function accountBackupCliSafeOutput(array $result, array $secrets): bool
{
    $output = (string) ($result['stdout'] ?? '') . (string) ($result['stderr'] ?? '');
    foreach ($secrets as $secret) {
        if (is_string($secret) && $secret !== '' && str_contains($output, $secret)) {
            return false;
        }
    }
    return true;
}

$root = dirname(__DIR__, 2);
$fixture = sys_get_temp_dir() . '/boardprep-account-backup-cli-' . bin2hex(random_bytes(8));
$storage = $fixture . '/storage';
$private = $fixture . '/private';
$sessions = $fixture . '/sessions';
$restoreParent = $private . '/restore';
mkdir($storage, 0700, true);
mkdir($private, 0700, true);
mkdir($sessions, 0700, true);
mkdir($restoreParent, 0700, true);
$environment = [
    'PATH' => (string) getenv('PATH'),
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'BOARDPREP_ACCOUNTS_ENABLED' => 'true',
    'DB_DRIVER' => 'json',
    'APP_STORAGE_PATH' => $storage,
    'DB_SQLITE_PATH' => $fixture . '/runtime.sqlite',
    'APP_SESSION_PATH' => $sessions,
];
$username = 'synthetic-backup-cli';
$password = 'Synthetic CLI account password phrase';
$passphrase = 'Synthetic CLI encrypted backup passphrase';
$wrongPassphrase = 'Different synthetic CLI passphrase';
$account = null;

try {
    foreach ($environment as $key => $value) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
    App::boot();
    $registered = (new AccountService())->register($username, $password);
    $account = (new AccountRepository())->findByUsername($username);
    accountBackupCliAssert(is_array($registered['account']) && is_array($account), 'Synthetic CLI account could not be created.');

    $backupScript = $root . '/tools/account-backup.php';
    $restoreScript = $root . '/tools/account-restore.php';
    $backupPath = $private . '/cli-created.bpk';
    $destination = $restoreParent . '/cli-restored';
    $accountId = (string) $account['account_id'];
    $learnerId = (string) $account['learner_id'];
    $secrets = [$password, $passphrase, $wrongPassphrase, $accountId, $learnerId, ...$registered['recovery_codes']];

    $help = accountBackupCliRun($backupScript, $root, $environment, ['--help']);
    accountBackupCliAssert($help['status'] === 0 && str_contains($help['stdout'], '--passphrase-fd=3'), 'Backup CLI help omitted the descriptor-based secret interface.');
    accountBackupCliAssert(accountBackupCliSafeOutput($help, $secrets), 'Backup CLI help exposed a synthetic private value.');

    $argumentSecret = accountBackupCliRun($backupScript, $root, $environment, [
        '--verify', '--input=' . $private . '/not-created.bpk', '--passphrase=' . $passphrase,
    ]);
    accountBackupCliAssert($argumentSecret['status'] === 2
        && str_contains($argumentSecret['stderr'], 'CLI_ARGUMENT_INVALID')
        && str_contains($argumentSecret['stderr'], 'file descriptor'),
        'Backup CLI accepted a passphrase argument or returned an unclear failure.');
    accountBackupCliAssert(accountBackupCliSafeOutput($argumentSecret, $secrets), 'Backup CLI echoed a rejected passphrase argument.');

    $missingSecret = accountBackupCliRun($backupScript, $root, $environment, [
        '--create', '--account-id=' . $accountId, '--output=' . $backupPath,
    ]);
    accountBackupCliAssert($missingSecret['status'] === 2
        && str_contains($missingSecret['stderr'], 'BACKUP_SECRET_MISSING')
        && !file_exists($backupPath),
        'Backup CLI did not fail closed when the passphrase descriptor was missing.');
    accountBackupCliAssert(accountBackupCliSafeOutput($missingSecret, $secrets), 'Missing-secret failure exposed a synthetic private value.');

    $created = accountBackupCliRun($backupScript, $root, $environment, [
        '--create', '--account-id=' . $accountId, '--output=' . $backupPath, '--passphrase-fd=3',
    ], $passphrase);
    accountBackupCliAssert(accountBackupCliSafeOutput($created, $secrets), 'Backup creation CLI output exposed a synthetic private value.');
    if (!AccountBackupCrypto::available()) {
        accountBackupCliAssert($created['status'] === 2
            && str_contains($created['stderr'], 'DEPENDENCY_MISSING')
            && !file_exists($backupPath),
            'CLI backup did not fail closed without ext-sodium.');
        echo "[PASS] Backup CLI rejects visible passphrase arguments, fails closed on a missing secret and missing ext-sodium, and never echoes synthetic private values.\n";
    } else {
        accountBackupCliAssert($created['status'] === 0 && is_file($backupPath)
            && (fileperms($backupPath) & 0077) === 0,
            'Descriptor-fed backup CLI did not create an owner-only encrypted archive.');
        $createdResult = json_decode($created['stdout'], true, 32, JSON_THROW_ON_ERROR);
        accountBackupCliAssert(($createdResult['status'] ?? '') === 'BACKUP_CREATED', 'Backup CLI returned an invalid success report.');

        $verified = accountBackupCliRun($backupScript, $root, $environment, [
            '--verify', '--input=' . $backupPath, '--passphrase-fd=3',
        ], $passphrase);
        $verifiedResult = json_decode($verified['stdout'], true, 32, JSON_THROW_ON_ERROR);
        accountBackupCliAssert($verified['status'] === 0 && ($verifiedResult['status'] ?? '') === 'BACKUP_VERIFIED', 'Descriptor-fed backup verification failed.');
        accountBackupCliAssert(accountBackupCliSafeOutput($verified, $secrets), 'Backup verification CLI output exposed a synthetic private value.');

        $wrong = accountBackupCliRun($backupScript, $root, $environment, [
            '--verify', '--input=' . $backupPath, '--passphrase-fd=3',
        ], $wrongPassphrase);
        accountBackupCliAssert($wrong['status'] === 2
            && str_contains($wrong['stderr'], 'BACKUP_AUTHENTICATION_FAILED')
            && accountBackupCliSafeOutput($wrong, $secrets),
            'Descriptor-fed wrong passphrase was not rejected without echoing secrets.');

        $restoreArgumentSecret = accountBackupCliRun($restoreScript, $root, $environment, [
            '--backup=' . $backupPath,
            '--destination=' . $destination,
            '--dry-run',
            '--passphrase=' . $passphrase,
        ]);
        accountBackupCliAssert($restoreArgumentSecret['status'] === 2
            && str_contains($restoreArgumentSecret['stderr'], 'CLI_ARGUMENT_INVALID')
            && accountBackupCliSafeOutput($restoreArgumentSecret, $secrets),
            'Restore CLI accepted or echoed a passphrase argument.');

        $restoreMissingSecret = accountBackupCliRun($restoreScript, $root, $environment, [
            '--backup=' . $backupPath, '--destination=' . $destination, '--dry-run',
        ]);
        accountBackupCliAssert($restoreMissingSecret['status'] === 2
            && str_contains($restoreMissingSecret['stderr'], 'BACKUP_SECRET_MISSING')
            && !file_exists($destination)
            && accountBackupCliSafeOutput($restoreMissingSecret, $secrets),
            'Restore CLI did not fail closed on a missing descriptor.');

        $dryRun = accountBackupCliRun($restoreScript, $root, $environment, [
            '--backup=' . $backupPath,
            '--destination=' . $destination,
            '--dry-run',
            '--passphrase-fd=3',
        ], $passphrase);
        $dryRunResult = json_decode($dryRun['stdout'], true, 32, JSON_THROW_ON_ERROR);
        accountBackupCliAssert($dryRun['status'] === 0
            && ($dryRunResult['status'] ?? '') === 'RESTORE_DRY_RUN_PASS'
            && !file_exists($destination)
            && accountBackupCliSafeOutput($dryRun, $secrets),
            'Descriptor-fed restore dry-run failed or modified its destination.');

        echo "[PASS] Backup and restore CLIs accept secrets only through a bounded descriptor, reject visible/missing secrets, keep output sanitized, and complete create/verify/dry-run operations.\n";
    }
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
    foreach (array_keys($environment) as $key) {
        putenv($key);
        unset($_ENV[$key]);
    }
}
