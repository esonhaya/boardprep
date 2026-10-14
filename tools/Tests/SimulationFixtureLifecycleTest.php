<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/SimulationStorageFixture.php';

$repositoryRoot = dirname(__DIR__, 2);
$testRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . '/boardprep-simulator-lifecycle-'
    . getmypid()
    . '-'
    . bin2hex(random_bytes(6));
$storagePath = $testRoot . '/storage';
$modes = ['success', 'failure', 'owned_success'];
if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal') && defined('SIGTERM')) {
    $modes[] = 'interrupt';
    $modes[] = 'owned_interrupt';
}

$removeTestRoot = static function (string $path): void {
    $temporaryRoot = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    $resolved = realpath($path);
    if ($resolved === false || !str_starts_with($resolved, $temporaryRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Refusing to clean a simulator fixture outside the temporary directory.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($resolved);
};

$childCode = static function (string $mode) use ($repositoryRoot, $storagePath): string {
    $ownedStorage = str_starts_with($mode, 'owned_');
    $repositoryStorage = $mode === 'owned_success';
    return ($ownedStorage
            ? ($repositoryStorage
                ? 'putenv("APP_STORAGE_PATH=" . ' . var_export($repositoryRoot . '/storage', true) . ');'
                : 'putenv("APP_STORAGE_PATH");')
            : 'putenv("APP_STORAGE_PATH=" . ' . var_export($storagePath, true) . ');')
        . 'putenv("APP_SESSION_PATH");'
        . 'require ' . var_export(dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/SimulationStorageFixture.php', true) . ';'
        . '\\Tools\\Doctor\\Project\\BoardPrep\\Simulation\\SimulationStorageFixture::activate('
        . var_export($repositoryRoot, true)
        . ');'
        . '$sessionPath=\\Tools\\Doctor\\Project\\BoardPrep\\Simulation\\SimulationStorageFixture::sessionPath();'
        . 'file_put_contents($sessionPath . "/diagnostic.txt", "isolated fixture");'
        . 'echo "SESSION_PATH=" . $sessionPath . PHP_EOL; flush();'
        . ($mode === 'failure'
            ? 'throw new RuntimeException("intentional lifecycle regression failure");'
            : (str_ends_with($mode, 'interrupt') ? 'while (true) { usleep(100000); }' : ''));
};

$results = [];
$fixtureRoots = [$testRoot];
$concurrentProcesses = [];
$lifecyclePassed = false;
try {
    foreach ($modes as $mode) {
        $process = proc_open(
            [PHP_BINARY, '-r', $childCode($mode)],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException("Unable to start {$mode} simulator fixture child.");
        }
        fclose($pipes[0]);
        $line = stream_get_line($pipes[1], 8192, "\n");
        if (!is_string($line) || !str_starts_with($line, 'SESSION_PATH=')) {
            proc_terminate($process);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            throw new RuntimeException("The {$mode} simulator child did not report its isolated session path.");
        }
        $sessionPath = substr($line, strlen('SESSION_PATH='));
        $ownedRoot = str_starts_with($mode, 'owned_') ? dirname($sessionPath, 4) : null;
        if (is_string($ownedRoot)) {
            $fixtureRoots[] = $ownedRoot;
        }

        if (str_ends_with($mode, 'interrupt')) {
            if (!proc_terminate($process, SIGTERM)) {
                throw new RuntimeException('Unable to interrupt the simulator fixture child.');
            }
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (in_array($mode, ['success', 'owned_success'], true)) {
            $fixtureRemains = is_string($ownedRoot) ? is_dir($ownedRoot) : is_dir($sessionPath);
            if ($fixtureRemains || $exitCode !== 0) {
                throw new RuntimeException('A successful simulator process left its private fixture behind.');
            }
        }
        if ($mode === 'failure' && (
            !is_file($sessionPath . '/diagnostic.txt')
            || !str_contains($stderr, 'SIMULATOR_SESSION_FIXTURE_PRESERVED=' . $sessionPath)
            || $exitCode === 0
        )) {
            throw new RuntimeException('A failed simulator process did not preserve its diagnostic fixture.');
        }
        if (str_ends_with($mode, 'interrupt')) {
            $fixtureRemains = is_string($ownedRoot) ? is_dir($ownedRoot) : is_dir($sessionPath);
            if ($fixtureRemains || $exitCode === 0) {
                throw new RuntimeException('An interrupted simulator process did not clean its private fixture.');
            }
        }

        $results[] = $mode . '=PASS';
    }

    for ($index = 0; $index < 2; $index++) {
        $process = proc_open(
            [PHP_BINARY, '-r', $childCode('owned_concurrent_' . $index)],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start concurrent simulator fixture child.');
        }
        fclose($pipes[0]);
        $concurrentProcesses[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }

    $concurrentPaths = [];
    foreach ($concurrentProcesses as $child) {
        $line = stream_get_line($child['stdout'], 8192, "\n");
        if (!is_string($line) || !str_starts_with($line, 'SESSION_PATH=')) {
            throw new RuntimeException('A concurrent simulator child did not report its isolated session path.');
        }
        $sessionPath = substr($line, strlen('SESSION_PATH='));
        $ownedRoot = dirname($sessionPath, 4);
        $fixtureRoots[] = $ownedRoot;
        $concurrentPaths[] = [$ownedRoot, $sessionPath];
        $stdout = stream_get_contents($child['stdout']);
        $stderr = stream_get_contents($child['stderr']);
        fclose($child['stdout']);
        fclose($child['stderr']);
        $exitCode = proc_close($child['process']);
        if ($exitCode !== 0 || is_dir($ownedRoot) || str_contains($stderr, 'SIMULATOR_FIXTURE_PRESERVED=')) {
            throw new RuntimeException('A concurrent simulator fixture did not finish and clean up successfully. ' . substr($stdout . $stderr, -1000));
        }
    }
    if (
        count(array_unique(array_column($concurrentPaths, 0))) !== 2
        || count(array_unique(array_column($concurrentPaths, 1))) !== 2
    ) {
        throw new RuntimeException('Concurrent simulator processes shared a storage or session namespace.');
    }
    $results[] = 'concurrent=PASS';

    echo '[PASS] Simulator fixture lifecycle: ' . implode(', ', $results) . ".\n";
    $lifecyclePassed = true;
} finally {
    if ($lifecyclePassed) {
        foreach (array_unique($fixtureRoots) as $fixtureRoot) {
            if (is_dir($fixtureRoot)) {
                $removeTestRoot($fixtureRoot);
            }
        }
    } else {
        foreach ($concurrentProcesses as $child) {
            if (is_resource($child['process'])) {
                proc_terminate($child['process']);
            }
        }
        foreach (array_unique($fixtureRoots) as $fixtureRoot) {
            if (is_dir($fixtureRoot)) {
                fwrite(STDERR, 'TEST_FIXTURE_PRESERVED=' . $fixtureRoot . PHP_EOL);
            }
        }
    }
}
