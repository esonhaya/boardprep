#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/account-cli-common.php';

function accountDeletionUsage(): void
{
    fwrite(STDOUT, <<<TEXT
BoardPrep account deletion retry (nonproduction technical verification only)

Usage:
  php tools/account-delete.php --list-pending
  php tools/account-delete.php --account-id=ACCOUNT_ID --confirm-account-id=ACCOUNT_ID
  php tools/account-delete.php --help

This tool cannot initiate deletion for an active account. It retries only a
previously password-confirmed deletion request. It is restricted to APP_ENV=testing
with BOARDPREP_ACCOUNTS_ENABLED=true and reports only pseudonymous account IDs,
states, and record counts. Use synthetic fixture storage only.
TEXT . "\n");
}

try {
    $options = accountCliOptions($argv);
    if (($options['help'] ?? false) === true) {
        accountDeletionUsage();
        exit(0);
    }
    accountCliAssertOptions($options, ['list-pending', 'account-id', 'confirm-account-id']);
    accountCliBootNonproduction();
    if (\App\Core\App::config('environment') !== 'testing') {
        throw new RuntimeException('ACCOUNT_TOOLING_DISABLED: deletion retry is restricted to APP_ENV=testing fixture storage.');
    }

    $repository = new \App\Services\Accounts\AccountRepository();
    if (($options['list-pending'] ?? false) === true && !isset($options['account-id'], $options['confirm-account-id'])) {
        accountCliPrint(['status' => 'PENDING_DELETIONS', 'accounts' => $repository->pendingDeletions()]);
        exit(0);
    }

    $accountId = is_string($options['account-id'] ?? null) ? $options['account-id'] : '';
    $confirmation = is_string($options['confirm-account-id'] ?? null) ? $options['confirm-account-id'] : '';
    if (preg_match('/\Aaccount-[a-f0-9]{32}\z/', $accountId) !== 1 || !hash_equals($accountId, $confirmation)) {
        throw new RuntimeException('CLI_ARGUMENT_INVALID: provide a valid account ID and repeat it as --confirm-account-id.');
    }
    $result = (new \App\Services\Accounts\AccountDeletionService())->process($accountId);
    accountCliPrint([
        'status' => strtoupper($result['status']),
        'already_completed' => $result['already_completed'],
        'deleted_record_counts' => $result['deleted'],
    ]);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(2);
}
