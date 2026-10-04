#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$arguments = array_slice($argv ?? [], 1);
$output = null;
$dryRun = false;
$json = false;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--output=')) $output = substr($argument, 9);
    elseif ($argument === '--dry-run') $dryRun = true;
    elseif ($argument === '--json') $json = true;
    else { fwrite(STDERR, "Usage: php tools/backup.php --output=/path/to/backup [--dry-run] [--json]\n"); exit(2); }
}
if ($output === null || trim($output) === '') { fwrite(STDERR, "--output is required.\n"); exit(2); }

try {
    $report = \App\Services\Operations\BackupService::create(dirname(__DIR__), $output, $dryRun);
    if ($json) echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    else printf("BACKUP=%s DESTINATION=%s FILES=%d\n", $report['status'], $report['destination'], count((array) ($report['manifest']['files'] ?? [])));
    exit($report['status'] === 'PASS' ? 0 : 1);
} catch (Throwable $exception) {
    if ($json) echo json_encode(['status' => 'FAIL', 'error' => 'Backup failed.'], JSON_PRETTY_PRINT), PHP_EOL;
    else fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
