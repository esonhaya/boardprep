<?php

declare(strict_types=1);

/** Bounded production preflight, smoke, backup, and restore contract. */

$root = dirname(__DIR__, 2);

$run = static function (string $command, array $environment = []) use ($root): array {
    $prefix = '';
    foreach ($environment as $key => $value) {
        $prefix .= $key . '=' . escapeshellarg((string) $value) . ' ';
    }
    $descriptor = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($prefix . escapeshellarg(PHP_BINARY) . ' -d display_errors=0 ' . $command, $descriptor, $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('Unable to start production foundation command.');
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$environment = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_TIMEZONE' => 'UTC',
    'DB_DRIVER' => 'json',
    'APP_STORAGE_PATH' => $root . '/storage',
];

[$status, $stdout] = $run('tools/doctor.php --preflight --json', $environment);
$preflight = json_decode(trim($stdout), true);
$assert($status === 0 && is_array($preflight) && ($preflight['status'] ?? '') === 'PASS', 'healthy production preflight did not pass');

[$status] = $run('tools/doctor.php --preflight --json', array_merge($environment, ['APP_DEBUG' => 'true']));
$assert($status !== 0, 'production preflight accepted APP_DEBUG=true');

$sqlitePath = sys_get_temp_dir() . '/boardprep-preflight-' . bin2hex(random_bytes(4)) . '.sqlite';
[$status, $stdout] = $run('tools/doctor.php --preflight --json', array_merge($environment, [
    'DB_DRIVER' => 'sqlite',
    'DB_SQLITE_PATH' => $sqlitePath,
]));
if (extension_loaded('pdo_sqlite')) {
    $sqlitePreflight = json_decode(trim($stdout), true);
    $assert($status === 0 && is_array($sqlitePreflight) && ($sqlitePreflight['status'] ?? '') === 'WARN', 'uninitialized SQLite preflight should be a nonblocking warning');
}

[$status, $stdout] = $run('tools/doctor.php --smoke --json', $environment);
$smoke = json_decode(trim($stdout), true);
$assert($status === 0 && is_array($smoke) && ($smoke['status'] ?? '') === 'PASS', 'operational smoke did not pass');

[$status, $stdout] = $run('public/health.php', $environment);
$health = json_decode(trim($stdout), true);
$assert($status === 0 && is_array($health) && ($health['status'] ?? '') === 'PASS', 'public health response did not pass');

$temporary = sys_get_temp_dir() . '/boardprep-foundation-' . bin2hex(random_bytes(4));
$backup = $temporary . '/backup';
$restore = $temporary . '/restore';
$removeTree = static function (string $directory) use (&$removeTree): void {
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if ($item->isDir()) rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($directory);
};
mkdir($temporary, 0770, true);
try {
    [$status, $stdout] = $run('tools/backup.php --output=' . escapeshellarg($backup) . ' --json', $environment);
    $backupReport = json_decode(trim($stdout), true);
    $assert($status === 0 && is_array($backupReport) && ($backupReport['status'] ?? '') === 'PASS', 'backup helper did not pass');
    $assert(is_file($backup . '/backup-manifest.json'), 'backup manifest missing');

    [$status, $stdout] = $run('tools/restore.php --input=' . escapeshellarg($backup) . ' --destination=' . escapeshellarg($restore) . ' --dry-run --json', $environment);
    $restorePlan = json_decode(trim($stdout), true);
    $assert($status === 0 && is_array($restorePlan) && ($restorePlan['status'] ?? '') === 'PASS', 'restore dry-run did not pass');

    [$status, $stdout] = $run('tools/restore.php --input=' . escapeshellarg($backup) . ' --destination=' . escapeshellarg($restore) . ' --json', $environment);
    $restoreReport = json_decode(trim($stdout), true);
    $assert($status === 0 && is_array($restoreReport) && ($restoreReport['status'] ?? '') === 'PASS', 'restore readback did not pass');
} finally {
    $removeTree($temporary);
    @unlink($sqlitePath);
}

echo "[PASS] production preflight, smoke, health, backup, and restore contracts verified.\n";
