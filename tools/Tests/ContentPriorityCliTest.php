<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $root . '/storage');

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/content-priorities.php') . ' --json 2>/dev/null';
$output = shell_exec($command);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('content-priorities JSON CLI returned no output');
}
$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
if (($report['portfolio']['question_count'] ?? 0) !== 1808
    || count($report['boards'] ?? []) !== 10
    || ($report['portfolio']['integrity_status'] ?? '') !== 'PASS_WITH_JUSTIFIED_EXCLUSIONS'
    || count($report['priorities'] ?? []) > 10) {
    throw new RuntimeException('content-priorities JSON CLI contract failed');
}

echo "[PASS] Content priorities JSON CLI contract verified.\n";
