<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$storageRoot = $root . '/storage';
$diagnosticRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . '/boardprep-doctor-simulator-state-'
    . getmypid()
    . '-'
    . bin2hex(random_bytes(6));
if (!mkdir($diagnosticRoot, 0700, true)) {
    throw new RuntimeException('Unable to create bounded Doctor simulator diagnostics directory.');
}
$stdoutPath = $diagnosticRoot . '/stdout.log';
$stderrPath = $diagnosticRoot . '/stderr.log';

$storageState = static function () use ($storageRoot): array {
    $state = [];
    if (!is_dir($storageRoot)) {
        throw new RuntimeException('Repository storage directory is missing.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $entry) {
        if (!$entry->isFile() && !$entry->isLink()) continue;
        $relative = substr($entry->getPathname(), strlen($storageRoot) + 1);
        $state[$relative] = $entry->isLink()
            ? 'link:' . (string) readlink($entry->getPathname())
            : hash_file('sha256', $entry->getPathname());
    }
    ksort($state, SORT_STRING);
    return $state;
};

$before = $storageState();
$simulatorEnvironment = getenv();
if (!is_array($simulatorEnvironment)) {
    $simulatorEnvironment = [];
}
// The simulator loads the full question collection into an isolated fixture.
// Match the bounded 1 GiB Doctor release-runner budget so memory pressure does
// not turn an otherwise successful isolation check into a false P0 finding.
$simulatorEnvironment['APP_MEMORY_LIMIT'] = '1024M';
$process = proc_open(
    [PHP_BINARY, '-d', 'memory_limit=1024M', $root . '/tools/doctor.php', '--simulate'],
    [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $stdoutPath, 'w'],
        2 => ['file', $stderrPath, 'w'],
    ],
    $pipes,
    $root,
    $simulatorEnvironment
);
if (!is_resource($process)) {
    throw new RuntimeException('Unable to start the isolated Doctor simulator command.');
}
$exitCode = proc_close($process);
$after = $storageState();
$stdout = (string) file_get_contents($stdoutPath);
$stderr = (string) file_get_contents($stderrPath);

if (
    $exitCode !== 0
    || $before !== $after
    || !str_contains($stdout, 'SIMULATION_STATUS=PASS')
    || str_contains($stderr, 'SIMULATOR_FIXTURE_PRESERVED=')
) {
    fwrite(STDERR, 'DOCTOR_SIMULATOR_DIAGNOSTICS=' . $diagnosticRoot . PHP_EOL);
    fwrite(STDERR, 'DOCTOR_SIMULATOR_EXIT=' . $exitCode . PHP_EOL);
    fwrite(STDERR, "--- stdout tail ---\n" . substr($stdout, -8000) . PHP_EOL);
    fwrite(STDERR, "--- stderr tail ---\n" . substr($stderr, -8000) . PHP_EOL);
    throw new RuntimeException('Doctor simulator did not pass in isolation or modified repository storage.');
}

foreach ([$stdoutPath, $stderrPath] as $path) {
    if (is_file($path)) unlink($path);
}
rmdir($diagnosticRoot);
echo '[PASS] Full Doctor --simulate left all ' . count($before) . " repository storage files and learner sessions byte-for-byte unchanged.\n";
