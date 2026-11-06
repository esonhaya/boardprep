<?php

declare(strict_types=1);

require_once __DIR__ . '/../Doctor/Project/BoardPrep/Simulation/SimulationStorageFixture.php';

use Tools\Doctor\Project\BoardPrep\Simulation\SimulationStorageFixture;

$temporaryRoot = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR);
$testRoot = $temporaryRoot . '/boardprep-simulator-question-fixture-' . getmypid() . '-' . bin2hex(random_bytes(6));
$storageRoot = $testRoot . '/storage';
$sessionRoot = $testRoot . '/sessions';
$questionsPath = $storageRoot . '/questions.json';
$questionFixture = "[{\"id\":\"isolated-clean-question\",\"answer\":\"A\"}]\n";
$environmentKeys = ['APP_STORAGE_PATH', 'APP_SESSION_PATH', 'DB_DRIVER', 'DB_SQLITE_PATH'];
$originalEnvironment = [];
$originalEnvValues = [];
foreach ($environmentKeys as $key) {
    $originalEnvironment[$key] = getenv($key);
    $originalEnvValues[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null];
}

$removeOwnedTree = static function (string $path) use ($temporaryRoot): void {
    $resolved = realpath($path);
    if ($resolved === false) return;
    if (!str_starts_with($resolved, $temporaryRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Refusing to remove simulator question test data outside the temporary directory.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            chmod($entry->getPathname(), 0700);
            rmdir($entry->getPathname());
        } else {
            chmod($entry->getPathname(), 0600);
            unlink($entry->getPathname());
        }
    }
    chmod($resolved, 0700);
    rmdir($resolved);
};

try {
    if (!mkdir($storageRoot, 0700, true) || !mkdir($sessionRoot, 0700, true)) {
        throw new RuntimeException('Unable to create isolated question fixture directories.');
    }
    if (file_put_contents($questionsPath, $questionFixture, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write the isolated question fixture.');
    }
    if (!chmod($questionsPath, 0400) || !chmod($storageRoot, 0500)) {
        throw new RuntimeException('Unable to make the sanitized question fixture read-only.');
    }

    putenv('APP_STORAGE_PATH=' . $storageRoot);
    putenv('APP_SESSION_PATH=' . $sessionRoot);
    SimulationStorageFixture::activate(dirname(__DIR__, 2));

    if (SimulationStorageFixture::storagePath() !== $storageRoot) {
        throw new RuntimeException('The simulator did not reuse the provided isolated storage fixture.');
    }
    if (file_get_contents($questionsPath) !== $questionFixture || is_file($questionsPath . '.simulator')) {
        throw new RuntimeException('An already-sanitized question fixture was rewritten or duplicated.');
    }
    if (!is_dir(SimulationStorageFixture::sessionPath())) {
        throw new RuntimeException('The simulator did not create its private session namespace.');
    }

    echo "Simulation question fixture sanitization PASS (clean read-only fixture reused without a temporary copy).\n";
} finally {
    @chmod($storageRoot, 0700);
    @chmod($questionsPath, 0600);
    $removeOwnedTree($testRoot);
    foreach ($environmentKeys as $key) {
        if (is_string($originalEnvironment[$key])) {
            putenv($key . '=' . $originalEnvironment[$key]);
        } else {
            putenv($key);
        }
        if ($originalEnvValues[$key][0]) {
            $_ENV[$key] = $originalEnvValues[$key][1];
        } else {
            unset($_ENV[$key]);
        }
    }
}
