<?php

declare(strict_types=1);

require_once __DIR__ . '/Doctor/Project/BoardPrep/Simulation/TemporaryTestArtifactCleanup.php';

use Tools\Doctor\Project\BoardPrep\Simulation\TemporaryTestArtifactCleanup;

$arguments = array_slice($argv, 1);
$apply = in_array('--apply', $arguments, true);
$unknown = array_values(array_diff($arguments, ['--apply', '--help']));
if ($unknown !== []) {
    fwrite(STDERR, "Usage: php tools/cleanup-stale-release-test-artifacts.php [--apply]\n");
    exit(2);
}
if (in_array('--help', $arguments, true)) {
    fwrite(STDOUT, "Dry-run by default. --apply removes only stale, unreferenced release-test fixtures and session trees.\n");
    exit(0);
}

try {
    $results = TemporaryTestArtifactCleanup::prune(sys_get_temp_dir(), $apply);
    $removedBytes = 0;
    foreach ($results as $result) {
        if (($result['action'] ?? '') === 'REMOVED') {
            $removedBytes += (int) ($result['bytes'] ?? 0);
        }
    }
    $payload = [
        'mode' => $apply ? 'apply' : 'dry_run',
        'temporary_root' => realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(),
        'candidate_count' => count($results),
        'removed_bytes' => $removedBytes,
        'results' => $results,
    ];
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Cleanup blocked: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
