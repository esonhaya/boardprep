#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$arguments = array_slice($argv ?? [], 1);
$input = null;
$destination = null;
$dryRun = false;
$confirmLive = false;
$json = false;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--input=')) $input = substr($argument, 8);
    elseif (str_starts_with($argument, '--destination=')) $destination = substr($argument, 14);
    elseif ($argument === '--dry-run') $dryRun = true;
    elseif ($argument === '--confirm-live') $confirmLive = true;
    elseif ($argument === '--json') $json = true;
    else { fwrite(STDERR, "Usage: php tools/restore.php --input=/path/to/backup --destination=/path/to/restore [--dry-run] [--confirm-live] [--json]\n"); exit(2); }
}
if ($input === null || $destination === null || trim($input) === '' || trim($destination) === '') { fwrite(STDERR, "--input and --destination are required.\n"); exit(2); }

try {
    $report = \App\Services\Operations\BackupService::restore(dirname(__DIR__), $input, $destination, $dryRun, $confirmLive);
    if ($json) echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    else printf("RESTORE=%s DESTINATION=%s FILES=%d DRY_RUN=%s\n", $report['status'], $report['destination'], count((array) ($report['plan'] ?? [])), $dryRun ? 'YES' : 'NO');
    exit($report['status'] === 'PASS' ? 0 : 1);
} catch (Throwable $exception) {
    if ($json) echo json_encode(['status' => 'FAIL', 'error' => 'Restore failed.'], JSON_PRETTY_PRINT), PHP_EOL;
    else fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
