<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/account-cli-common.php';

function accountBackupUsage(): void
{
    fwrite(STDOUT, <<<TEXT
BoardPrep encrypted account backup

Usage:
  php tools/account-backup.php --create --account-id=ACCOUNT_ID --output=/private/path/account.bpk --passphrase-fd=3
  php tools/account-backup.php --verify --input=/private/path/account.bpk --passphrase-fd=3
  php tools/account-backup.php --help

The passphrase is read from a restricted inherited file descriptor, never an
argument or environment value. Output must be a new owner-only file outside
the repository and public web root. Requires the explicit nonproduction
account preview gate and ext-sodium.
TEXT . "\n");
}

$passphrase = null;
try {
    $options = accountCliOptions($argv);
    if (($options['help'] ?? false) === true) {
        accountBackupUsage();
        exit(0);
    }
    accountCliAssertOptions($options, ['create', 'verify', 'account-id', 'output', 'input', 'passphrase-fd']);
    accountCliBootNonproduction();
    $service = new \App\Services\Accounts\AccountBackupService();
    $passphrase = accountCliPassphrase($options['passphrase-fd'] ?? null);
    if (($options['create'] ?? false) === true && !isset($options['verify'])) {
        $accountId = is_string($options['account-id'] ?? null) ? $options['account-id'] : '';
        $output = is_string($options['output'] ?? null) ? $options['output'] : '';
        if ($accountId === '' || $output === '') {
            throw new RuntimeException('CLI_ARGUMENT_INVALID: --create requires --account-id and --output.');
        }
        $result = $service->create($accountId, $output, $passphrase);
        accountCliPrint(['status' => 'BACKUP_CREATED', 'record_counts' => $result['record_counts'], 'schema_version' => $result['schema_version'], 'path' => $result['path']]);
        accountCliErasePassphrase($passphrase);
        exit(0);
    }
    if (($options['verify'] ?? false) === true && !isset($options['create'])) {
        $input = is_string($options['input'] ?? null) ? $options['input'] : '';
        if ($input === '') {
            throw new RuntimeException('CLI_ARGUMENT_INVALID: --verify requires --input.');
        }
        $result = $service->verify($input, $passphrase);
        accountCliPrint(['status' => 'BACKUP_VERIFIED', 'record_counts' => $result['record_counts'], 'schema_version' => $result['schema_version'], 'created_at' => $result['created_at']]);
        accountCliErasePassphrase($passphrase);
        exit(0);
    }
    throw new RuntimeException('CLI_ARGUMENT_INVALID: choose exactly one of --create or --verify.');
} catch (Throwable $exception) {
    accountCliErasePassphrase($passphrase);
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(2);
}
