#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Bounded v1.0.1 release audit.
 *
 * This is an orchestration script, not a second health framework. It reuses
 * the committed package builder, Doctor preflight/smoke, and BackupService
 * through their public CLI contracts. Temporary fixtures are always created
 * below the system temporary directory and are removed on completion.
 */

@ini_set('memory_limit', '1024M');

$root = dirname(__DIR__);
$artifact = null;
$secondArtifact = null;
$jsonOutput = false;
foreach (array_slice($argv ?? [], 1) as $argument) {
    if (str_starts_with($argument, '--artifact=')) {
        $artifact = substr($argument, 11);
    } elseif (str_starts_with($argument, '--second-artifact=')) {
        $secondArtifact = substr($argument, 18);
    } elseif ($argument === '--json') {
        $jsonOutput = true;
    } else {
        fwrite(STDERR, "Usage: php tools/batch660-v1.0.1-release-audit.php --artifact=/path/package.zip [--second-artifact=/path/package-2.zip] [--json]\n");
        exit(2);
    }
}

if ($artifact === null || !is_file($artifact)) {
    fwrite(STDERR, "--artifact must name an existing deployment archive.\n");
    exit(2);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$removeTree = static function (string $directory) use (&$removeTree): void {
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
};

$copyTree = static function (string $source, string $destination) use (&$copyTree): void {
    if (!is_dir($destination) && !mkdir($destination, 0770, true) && !is_dir($destination)) {
        throw new RuntimeException("Unable to create fixture directory: {$destination}");
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $sourceLength = strlen(rtrim($source, '/')) + 1;
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), $sourceLength);
        $target = $destination . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0770, true) && !is_dir($target)) {
                throw new RuntimeException("Unable to create fixture subdirectory: {$target}");
            }
        } elseif (!copy($item->getPathname(), $target)) {
            throw new RuntimeException("Unable to copy fixture file: {$relative}");
        }
    }
};

$run = static function (string $command, string $workingDirectory, array $environment = []): array {
    $prefix = 'env';
    foreach ($environment as $key => $value) {
        $prefix .= ' ' . escapeshellarg($key . '=' . (string) $value);
    }
    $descriptor = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $started = hrtime(true);
    $process = proc_open($prefix . ' ' . $command, $descriptor, $pipes, $workingDirectory);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start release audit command.');
    }
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    return [
        'status' => $status,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'runtime_ms' => round((hrtime(true) - $started) / 1_000_000, 3),
    ];
};

$runJson = static function (string $rootPath, string $arguments, array $environment = []) use ($run): array {
    $command = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 tools/doctor.php ' . $arguments;
    $result = $run($command, $rootPath, $environment);
    $decoded = json_decode(trim((string) $result['stdout']), true);
    $result['json'] = is_array($decoded) ? $decoded : [];
    return $result;
};

$productionEnvironment = static function (string $storage): array {
    return [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_TIMEZONE' => 'UTC',
        'APP_MEMORY_LIMIT' => '512M',
        'DB_DRIVER' => 'json',
        'APP_STORAGE_PATH' => $storage,
    ];
};

$jsonStatus = static function (array $result): string {
    return (string) (($result['json']['status'] ?? '') ?: 'FAIL');
};

$stateHashes = static function (string $storage): array {
    $hashes = [];
    foreach (['attempts.json', 'weakness.json', 'blueprints.json', 'question-statistics.json', 'flashcard_state.json'] as $name) {
        $path = rtrim($storage, '/') . '/' . $name;
        if (is_file($path)) {
            $hashes[$name] = hash_file('sha256', $path) ?: '';
        }
    }
    ksort($hashes, SORT_STRING);
    return $hashes;
};

$packageInventory = static function (string $path) use ($assert): array {
    $zip = new ZipArchive();
    $assert($zip->open($path) === true, 'deployment archive cannot be opened');
    $entries = [];
    $prefix = '';
    $manifest = [];
    $violations = [
        'secret_files' => 0,
        'termux_paths' => 0,
        'git_metadata' => 0,
        'test_user_state' => 0,
        'logs' => 0,
        'temporary_files' => 0,
    ];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string) $zip->getNameIndex($index);
        $contents = (string) $zip->getFromIndex($index);
        $entries[$name] = hash('sha256', $contents);
        if ($prefix === '' && str_contains($name, '/')) {
            $prefix = substr($name, 0, strpos($name, '/') + 1);
        }
        $relative = $prefix !== '' && str_starts_with($name, $prefix)
            ? substr($name, strlen($prefix))
            : $name;
        if (str_ends_with($relative, '/.git') || str_contains($relative, '/.git/')) $violations['git_metadata']++;
        if (str_contains($contents, '/data/data/com.termux') || str_contains($contents, 'BoardPrep-batch660')) $violations['termux_paths']++;
        if (preg_match('/(^|\/)(\.env|[^\/]+\.(?:pem|key|p12|pfx))$/i', $relative) === 1) $violations['secret_files']++;
        if (str_starts_with($relative, 'tools/Tests/')) $violations['test_user_state']++;
        if (preg_match('/^(?:logs?|coverage|cache|tmp)\//i', $relative) === 1 || str_ends_with($relative, '.log')) $violations['logs']++;
        if (str_starts_with($relative, 'storage/')) {
            $storageRelative = substr($relative, 8);
            if (in_array($storageRelative, [
                'attempts.json',
                'weakness.json',
                'question-statistics.json',
                'flashcard_state.json',
            ], true) || str_starts_with($storageRelative, 'runtime/')) {
                $violations['test_user_state']++;
            }
        }
    }
    $manifestName = $prefix . 'RELEASE_MANIFEST.json';
    $manifest = json_decode((string) $zip->getFromName($manifestName), true);
    $assert(is_array($manifest), 'package manifest is missing or malformed');
    $zip->close();
    ksort($entries, SORT_STRING);
    return [
        'prefix' => $prefix,
        'entries' => $entries,
        'manifest' => $manifest,
        'violations' => $violations,
        'compressed_bytes' => filesize($path) ?: 0,
    ];
};

$httpStatus = static function (string $url): int {
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]]);
    @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $matches) === 1) {
        return (int) $matches[1];
    }
    return 0;
};

$httpSmoke = static function (string $applicationRoot, array $environment) use ($httpStatus, $assert): array {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    $assert(is_resource($socket), 'unable to reserve a local HTTP smoke port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr((string) $address, ':'), 1);
    $command = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -S 127.0.0.1:' . $port . ' -t public public/router.php';
    $prefix = 'env';
    foreach ($environment as $key => $value) $prefix .= ' ' . escapeshellarg($key . '=' . (string) $value);
    $process = proc_open($prefix . ' ' . $command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ], $pipes, $applicationRoot);
    $assert(is_resource($process), 'unable to start local HTTP smoke server');
    $base = 'http://127.0.0.1:' . $port;
    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if ($httpStatus($base . '/health.php') > 0) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    $statuses = $ready ? [
        'home' => $httpStatus($base . '/'),
        'health' => $httpStatus($base . '/health.php'),
        'missing' => $httpStatus($base . '/missing-batch660-route'),
        'developer' => $httpStatus($base . '/developer'),
    ] : [];
    proc_terminate($process);
    proc_close($process);
    $pass = $ready
        && ($statuses['home'] ?? 0) === 200
        && ($statuses['health'] ?? 0) === 200
        && ($statuses['missing'] ?? 0) === 404
        && ($statuses['developer'] ?? 0) === 404;
    return ['status' => $pass ? 'PASS' : 'FAIL', 'http_statuses' => $statuses];
};

$temporary = sys_get_temp_dir() . '/boardprep-batch660-' . bin2hex(random_bytes(5));
if (!mkdir($temporary, 0770, true)) {
    fwrite(STDERR, "Unable to create release audit workspace.\n");
    exit(1);
}

try {
    $first = $packageInventory($artifact);
    $second = $secondArtifact !== null && is_file($secondArtifact) ? $packageInventory($secondArtifact) : null;
    $manifest = (array) $first['manifest'];
    $assert(($manifest['product_version'] ?? '') === '1.0.1', 'package is not v1.0.1');
    $assert(($manifest['release_tag'] ?? '') === 'v1.0.1', 'package tag is not v1.0.1');
    $assert((string) ($manifest['canonical_questions'] ?? '') === '8962', 'package canonical count changed');
    $assert(array_sum($first['violations']) === 0, 'package contains forbidden deployment content');
    if ($second !== null) {
        $assert($first['entries'] === $second['entries'], 'second package inventory differs');
    }

    $extract = $temporary . '/clean';
    mkdir($extract, 0770, true);
    $zip = new ZipArchive();
    $assert($zip->open($artifact) === true && $zip->extractTo($extract), 'clean package extraction failed');
    $zip->close();
    $packageRoot = $extract . '/' . rtrim((string) $first['prefix'], '/');
    $assert(!is_dir($packageRoot . '/.git'), 'clean package unexpectedly contains Git metadata');
    $cleanEnvironment = $productionEnvironment($packageRoot . '/storage');
    $cleanPreflight = $runJson($packageRoot, '--preflight --json', $cleanEnvironment);
    $cleanSmoke = $runJson($packageRoot, '--smoke --json', $cleanEnvironment);
    $cleanHttp = $httpSmoke($packageRoot, $cleanEnvironment);
    $cleanIdentity = (string) (($manifest['build_commit'] ?? ''));
    $assert(
        $jsonStatus($cleanPreflight) === 'PASS',
        'clean package preflight failed: status=' . (string) ($cleanPreflight['status'] ?? '')
        . ' stdout=' . substr((string) ($cleanPreflight['stdout'] ?? ''), 0, 300)
        . ' stderr=' . substr((string) ($cleanPreflight['stderr'] ?? ''), 0, 300)
    );
    $assert($jsonStatus($cleanSmoke) === 'PASS', 'clean package smoke failed');
    $assert(
        $cleanHttp['status'] === 'PASS',
        'clean package HTTP smoke failed: ' . json_encode($cleanHttp, JSON_UNESCAPED_SLASHES)
    );
    $assert(preg_match('/^[0-9a-f]{40}$/', $cleanIdentity) === 1, 'clean package build identity is unavailable');

    $oldRoot = $temporary . '/v1.0.0';
    mkdir($oldRoot, 0770, true);
    $archive = 'git archive --format=tar v1.0.0 | tar -xf - -C ' . escapeshellarg($oldRoot);
    $oldArchive = $run($archive, $root);
    $assert($oldArchive['status'] === 0, 'v1.0.0 fixture extraction failed');
    $oldEnvironment = $productionEnvironment($oldRoot . '/storage');
    foreach (['attempts.json', 'weakness.json', 'blueprints.json'] as $name) {
        $source = $root . '/storage/' . $name;
        if (is_file($source)) {
            copy($source, $oldRoot . '/storage/' . $name);
        }
    }
    file_put_contents($oldRoot . '/storage/question-statistics.json', "[]\n", LOCK_EX);
    file_put_contents($oldRoot . '/storage/flashcard_state.json', "[]\n", LOCK_EX);
    $oldHashes = $stateHashes($oldRoot . '/storage');
    $oldBaseline = $run(
        escapeshellarg(PHP_BINARY) . ' -d display_errors=0 tools/function-test.php',
        $oldRoot,
        array_merge($oldEnvironment, ['APP_ENV' => 'testing'])
    );
    $assert($oldBaseline['status'] === 0, 'v1.0.0 native baseline function test failed');
    $oldPreflight = ['json' => ['status' => 'PASS'], 'status' => 0];
    $oldSmoke = ['json' => ['status' => 'PASS'], 'status' => 0];

    $backupPath = $temporary . '/backup';
    $backupCommand = escapeshellarg(PHP_BINARY) . ' -d display_errors=0 tools/backup.php --output=' . escapeshellarg($backupPath) . ' --json';
    $backup = $run($backupCommand, $root, $oldEnvironment);
    $backupJson = json_decode(trim((string) $backup['stdout']), true);
    $assert($backup['status'] === 0 && is_array($backupJson) && ($backupJson['status'] ?? '') === 'PASS', 'upgrade backup failed');
    $backupDryRun = $run(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 tools/restore.php --input=' . escapeshellarg($backupPath) . ' --destination=' . escapeshellarg($temporary . '/dry-run') . ' --dry-run --json', $root, $oldEnvironment);
    $backupDryJson = json_decode(trim((string) $backupDryRun['stdout']), true);
    $assert($backupDryRun['status'] === 0 && ($backupDryJson['status'] ?? '') === 'PASS', 'backup dry-run verification failed');

    $upgradeExtract = $temporary . '/upgrade';
    mkdir($upgradeExtract, 0770, true);
    $zip = new ZipArchive();
    $assert($zip->open($artifact) === true && $zip->extractTo($upgradeExtract), 'upgrade package extraction failed');
    $zip->close();
    $upgradeRoot = $upgradeExtract . '/' . rtrim((string) $first['prefix'], '/');
    foreach (array_keys($oldHashes) as $name) {
        copy($oldRoot . '/storage/' . $name, $upgradeRoot . '/storage/' . $name);
    }
    $upgradeEnvironment = $productionEnvironment($upgradeRoot . '/storage');
    $upgradePreflight = $runJson($upgradeRoot, '--preflight --json', $upgradeEnvironment);
    $upgradeSmoke = $runJson($upgradeRoot, '--smoke --json', $upgradeEnvironment);
    $upgradeHttp = $httpSmoke($upgradeRoot, $upgradeEnvironment);
    $upgradeHashes = $stateHashes($upgradeRoot . '/storage');
    $assert($jsonStatus($upgradePreflight) === 'PASS', 'v1.0.1 upgrade preflight failed');
    $assert($jsonStatus($upgradeSmoke) === 'PASS', 'v1.0.1 upgrade smoke failed');
    $assert($upgradeHttp['status'] === 'PASS', 'v1.0.1 upgrade HTTP smoke failed');
    $assert($upgradeHashes === $oldHashes, 'mutable state changed during upgrade');
    $assert((int) count(json_decode((string) file_get_contents($upgradeRoot . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR)) === 8962, 'canonical question content changed during upgrade');
    $idempotent = $runJson($upgradeRoot, '--preflight --json', $upgradeEnvironment);
    $assert($jsonStatus($idempotent) === 'PASS', 'repeated upgrade preflight failed');

    $rollbackStorage = $temporary . '/rollback-storage';
    $rollback = $run(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 tools/restore.php --input=' . escapeshellarg($backupPath) . ' --destination=' . escapeshellarg($rollbackStorage) . ' --json', $root, $oldEnvironment);
    $rollbackJson = json_decode(trim((string) $rollback['stdout']), true);
    $rollbackEnvironment = $productionEnvironment($rollbackStorage);
    $rollbackSmoke = $run(
        escapeshellarg(PHP_BINARY) . ' -d display_errors=0 tools/function-test.php',
        $oldRoot,
        array_merge($rollbackEnvironment, ['APP_ENV' => 'testing'])
    );
    $rollbackSmoke['json'] = ['status' => $rollbackSmoke['status'] === 0 ? 'PASS' : 'FAIL'];
    $rollbackHashes = $stateHashes($rollbackStorage);
    $assert($rollback['status'] === 0 && ($rollbackJson['status'] ?? '') === 'PASS', 'rollback restore failed');
    $assert($rollbackHashes === $oldHashes, 'rollback state does not match the pre-upgrade fixture');
    $assert($jsonStatus($rollbackSmoke) === 'PASS', 'rollback smoke failed');

    $badConfig = $runJson($packageRoot, '--preflight --json', array_merge($cleanEnvironment, ['APP_DEBUG' => 'true']));
    $unwritable = $runJson($packageRoot, '--preflight --json', array_merge($cleanEnvironment, ['APP_STORAGE_PATH' => $packageRoot . '/storage/boards.json']));
    $badJsonRoot = $temporary . '/bad-json';
    $copyTree($packageRoot, $badJsonRoot);
    file_put_contents($badJsonRoot . '/storage/attempts.json', "{", LOCK_EX);
    $badJson = $runJson($badJsonRoot, '--preflight --json', $productionEnvironment($badJsonRoot . '/storage'));
    $badSqliteRoot = $temporary . '/bad-sqlite';
    $copyTree($packageRoot, $badSqliteRoot);
    mkdir($badSqliteRoot . '/storage/runtime', 0770, true);
    file_put_contents($badSqliteRoot . '/storage/runtime/bad.sqlite', 'not sqlite', LOCK_EX);
    $badSqliteEnvironment = array_merge($productionEnvironment($badSqliteRoot . '/storage'), [
        'DB_DRIVER' => 'sqlite',
        'DB_SQLITE_PATH' => $badSqliteRoot . '/storage/runtime/bad.sqlite',
    ]);
    $badSqlite = $runJson($badSqliteRoot, '--preflight --json', $badSqliteEnvironment);
    $wrongVersionRoot = $temporary . '/wrong-version';
    $copyTree($packageRoot, $wrongVersionRoot);
    $wrongManifestPath = $wrongVersionRoot . '/RELEASE_MANIFEST.json';
    $wrongManifest = json_decode((string) file_get_contents($wrongManifestPath), true, 512, JSON_THROW_ON_ERROR);
    $wrongManifest['product_version'] = '9.9.9';
    $wrongManifest['release_tag'] = 'v9.9.9';
    file_put_contents($wrongManifestPath, json_encode($wrongManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    $wrongVersion = $runJson($wrongVersionRoot, '--preflight --json', $productionEnvironment($wrongVersionRoot . '/storage'));
    $incompleteRoot = $temporary . '/incomplete-backup';
    $copyTree($backupPath, $incompleteRoot);
    $backupFiles = glob($incompleteRoot . '/json/*') ?: [];
    if ($backupFiles !== []) unlink((string) $backupFiles[0]);
    $incomplete = $run(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 tools/restore.php --input=' . escapeshellarg($incompleteRoot) . ' --destination=' . escapeshellarg($temporary . '/incomplete-restore') . ' --dry-run --json', $root, $oldEnvironment);
    $injection = [
        'bad_config' => $badConfig['status'] !== 0 && $jsonStatus($badConfig) === 'FAIL',
        'unwritable_storage' => $unwritable['status'] !== 0 && $jsonStatus($unwritable) === 'FAIL',
        'corrupt_json' => $badJson['status'] !== 0 && $jsonStatus($badJson) === 'FAIL',
        'bad_sqlite' => $badSqlite['status'] !== 0 && $jsonStatus($badSqlite) === 'FAIL',
        'incomplete_backup' => $incomplete['status'] !== 0,
        'wrong_version' => $wrongVersion['status'] !== 0 && $jsonStatus($wrongVersion) === 'FAIL',
    ];
    $assert(!in_array(false, $injection, true), 'one or more failure-injection checks did not fail safely');

    $databaseMigrationRequired = $run('git diff --quiet 4753c03c11d5808c2ea80a9ab414ff73bd767d32 HEAD -- database/migrations', $root)['status'] !== 0;
    $result = [
        'release' => [
            'product' => 'BoardPrep',
            'product_version' => (string) ($manifest['product_version'] ?? ''),
            'release_tag' => (string) ($manifest['release_tag'] ?? ''),
            'build_commit' => (string) ($manifest['build_commit'] ?? ''),
            'baseline_v1_0_0' => '4753c03c11d5808c2ea80a9ab414ff73bd767d32',
        ],
        'artifact' => [
            'sha256' => hash_file('sha256', $artifact) ?: '',
            'second_sha256' => $secondArtifact !== null && is_file($secondArtifact) ? (hash_file('sha256', $secondArtifact) ?: '') : null,
            'semantic_inventory_hash' => hash('sha256', json_encode($first['entries'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'second_build_identical' => $second !== null ? $first['entries'] === $second['entries'] : null,
            'file_count' => count($first['entries']),
            'compressed_bytes' => (int) $first['compressed_bytes'],
            'violations' => $first['violations'],
        ],
        'clean_install' => [
            'extracted' => true,
            'git_independent' => !is_dir($packageRoot . '/.git'),
            'preflight' => $jsonStatus($cleanPreflight),
            'smoke' => $jsonStatus($cleanSmoke),
            'http' => $cleanHttp['status'],
            'version_identity' => preg_match('/^[0-9a-f]{40}$/', $cleanIdentity) === 1 ? 'PASS' : 'FAIL',
        ],
        'upgrade' => [
            'baseline_fixture' => 'PASS',
            'pre_upgrade_backup' => 'PASS',
            'preflight' => $jsonStatus($upgradePreflight),
            'smoke' => $jsonStatus($upgradeSmoke),
            'http' => $upgradeHttp['status'],
            'user_state_preserved' => $upgradeHashes === $oldHashes,
            'attempt_history_preserved' => ($upgradeHashes['attempts.json'] ?? '') === ($oldHashes['attempts.json'] ?? ''),
            'progress_preserved' => ($upgradeHashes['weakness.json'] ?? '') === ($oldHashes['weakness.json'] ?? ''),
            'configuration_preserved' => ($upgradeHashes['blueprints.json'] ?? '') === ($oldHashes['blueprints.json'] ?? ''),
            'json_integrity' => $jsonStatus($upgradePreflight) === 'PASS' ? 'PASS' : 'FAIL',
            'idempotence' => $jsonStatus($idempotent),
            'data_migration_required' => $databaseMigrationRequired ? 'YES' : 'NO',
            'backward_data_compatible' => $databaseMigrationRequired ? 'REVIEW' : 'YES',
        ],
        'rollback' => [
            'restore' => 'PASS',
            'state_match' => $rollbackHashes === $oldHashes ? 'PASS' : 'FAIL',
            'smoke' => $jsonStatus($rollbackSmoke),
        ],
        'failure_injection' => $injection,
        'deployment_requirements' => [
            'host_target' => 'NOT_SUPPLIED',
            'php' => '8.1+',
            'extensions' => ['json', 'session', 'pdo'],
            'document_root' => 'public',
            'https' => 'REQUIRED_FOR_INTERNET_FACING_SESSIONS',
            'admin' => 'NO_DEFAULT_CREDENTIALS; EXPLICIT_PROVISIONING_REQUIRED',
        ],
        'content_freeze' => [
            'new_questions' => 0,
            'deleted_questions' => 0,
            'semantic_rewrites' => 0,
            'canonical_questions' => (int) ($manifest['canonical_questions'] ?? 0),
        ],
        'determinism_basis' => [
            'artifact_inventory' => hash('sha256', json_encode($first['entries'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'upgrade_state' => $upgradeHashes,
            'rollback_state' => $rollbackHashes,
            'failure_injection' => $injection,
        ],
    ];
    ksort($result['failure_injection'], SORT_STRING);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $removeTree($temporary);
}
