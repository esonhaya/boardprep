<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Accounts\AccountFeatureGate;

/** @return array<string,string|bool> */
function accountCliOptions(array $arguments): array
{
    $options = [];
    for ($index = 1, $count = count($arguments); $index < $count; $index++) {
        $argument = $arguments[$index];
        if (!str_starts_with($argument, '--')) {
            throw new RuntimeException('CLI_ARGUMENT_INVALID: options must use named --flags.');
        }
        $pair = explode('=', substr($argument, 2), 2);
        $key = $pair[0];
        if ($key === '') {
            throw new RuntimeException('CLI_ARGUMENT_INVALID: option name is empty.');
        }
        if (array_key_exists($key, $options)) {
            throw new RuntimeException('CLI_ARGUMENT_INVALID: duplicate options are not accepted.');
        }
        if (count($pair) === 2) {
            $options[$key] = $pair[1];
            continue;
        }
        if (in_array($key, ['help', 'create', 'verify', 'dry-run', 'commit-isolated-restore', 'list-pending'], true)) {
            $options[$key] = true;
            continue;
        }
        if (!isset($arguments[$index + 1]) || str_starts_with($arguments[$index + 1], '--')) {
            throw new RuntimeException('CLI_ARGUMENT_INVALID: option value is missing.');
        }
        $options[$key] = $arguments[++$index];
    }
    return $options;
}

/** @param array<string,string|bool> $options @param list<string> $allowed */
function accountCliAssertOptions(array $options, array $allowed): void
{
    foreach (array_keys($options) as $key) {
        if (!in_array($key, $allowed, true)) {
            throw new RuntimeException('CLI_ARGUMENT_INVALID: an unsupported option was supplied; passphrases are accepted only through a file descriptor.');
        }
    }
}

function accountCliPassphrase(mixed $fileDescriptor): string
{
    if (!is_string($fileDescriptor) || preg_match('/\A[3-9][0-9]{0,2}\z/', $fileDescriptor) !== 1) {
        throw new RuntimeException('BACKUP_SECRET_MISSING: supply --passphrase-fd=N from a restricted file descriptor.');
    }
    $stream = @fopen('php://fd/' . $fileDescriptor, 'rb');
    if ($stream === false) {
        throw new RuntimeException('BACKUP_SECRET_MISSING: the passphrase file descriptor is unavailable.');
    }
    try {
        $line = fgets($stream, 1026);
        if (!is_string($line) || !str_ends_with($line, "\n")) {
            throw new RuntimeException('BACKUP_SECRET_MISSING: the passphrase descriptor must contain one bounded line.');
        }
        $passphrase = substr($line, 0, -1);
        if (str_ends_with($passphrase, "\r")) {
            $passphrase = substr($passphrase, 0, -1);
        }
        $extra = fread($stream, 1);
        if (strlen($passphrase) > 1024 || str_contains($passphrase, "\r") || str_contains($passphrase, "\n")
            || (is_string($extra) && $extra !== '')) {
            throw new RuntimeException('BACKUP_SECRET_INVALID: the passphrase descriptor must contain one bounded line.');
        }
        return $passphrase;
    } finally {
        fclose($stream);
    }
}

function accountCliErasePassphrase(?string &$passphrase): void
{
    if (is_string($passphrase) && $passphrase !== '' && function_exists('sodium_memzero')) {
        sodium_memzero($passphrase);
    }
    $passphrase = null;
}

function accountCliBootNonproduction(): void
{
    App::boot();
    if (App::config('environment') === 'production' || !AccountFeatureGate::enabled()) {
        throw new RuntimeException('ACCOUNT_TOOLING_DISABLED: account backup and restore CLI require the explicit nonproduction account preview gate.');
    }
}

function accountCliPrint(array $result): void
{
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
}
