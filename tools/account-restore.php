<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/account-cli-common.php';

function accountRestoreUsage(): void
{
    fwrite(STDOUT, <<<TEXT
BoardPrep encrypted account restore

Usage:
  php tools/account-restore.php --backup=/private/path/account.bpk --destination=/private/restore/account --dry-run --passphrase-fd=3
  php tools/account-restore.php --backup=/private/path/account.bpk --destination=/private/restore/account --commit-isolated-restore --passphrase-fd=3
  php tools/account-restore.php --help

Dry-run is the default. A committed restore creates a new, private JSON
destination atomically and never activates it or overwrites existing data.
The destination parent must already exist with owner-only permissions and
must be outside the repository and web root. The passphrase is read from a
restricted inherited file descriptor. Requires the explicit nonproduction
account preview gate and ext-sodium.
TEXT . "\n");
}

$passphrase = null;
try {
    $options = accountCliOptions($argv);
    if (($options['help'] ?? false) === true) {
        accountRestoreUsage();
        exit(0);
    }
    accountCliAssertOptions($options, ['backup', 'destination', 'dry-run', 'commit-isolated-restore', 'passphrase-fd']);
    accountCliBootNonproduction();
    $archive = is_string($options['backup'] ?? null) ? $options['backup'] : '';
    $destination = is_string($options['destination'] ?? null) ? $options['destination'] : '';
    if ($archive === '' || $destination === '') {
        throw new RuntimeException('CLI_ARGUMENT_INVALID: --backup and --destination are required.');
    }
    $dryRun = ($options['dry-run'] ?? false) === true;
    $commit = ($options['commit-isolated-restore'] ?? false) === true;
    if ($dryRun && $commit) {
        throw new RuntimeException('CLI_ARGUMENT_INVALID: choose --dry-run or --commit-isolated-restore.');
    }
    $passphrase = accountCliPassphrase($options['passphrase-fd'] ?? null);
    $result = (new \App\Services\Accounts\AccountRestoreService())->restore(
        $archive,
        $destination,
        $passphrase,
        $dryRun || !$commit,
        $commit
    );
    accountCliPrint(['status' => $result['dry_run'] ? 'RESTORE_DRY_RUN_PASS' : 'RESTORE_CREATED_ISOLATED_DESTINATION', 'record_counts' => $result['record_counts'], 'schema_version' => $result['schema_version'], 'destination' => $result['destination']]);
    accountCliErasePassphrase($passphrase);
    exit(0);
} catch (Throwable $exception) {
    accountCliErasePassphrase($passphrase);
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(2);
}
