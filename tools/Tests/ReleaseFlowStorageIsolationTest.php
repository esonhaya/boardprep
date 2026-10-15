<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$realStorage = $root . '/storage';
$service = \App\Services\RepositoryHealth\Release\ReleaseReadinessService::class;
$create = new ReflectionMethod($service, 'createFlowTestStorage');
$rebuild = new ReflectionMethod($service, 'rebuildFlowTestStorage');
$remove = new ReflectionMethod($service, 'removeFlowTestStorage');
$plan = new ReflectionMethod($service, 'flowTestStoragePlan');
$inventory = new ReflectionMethod($service, 'temporaryDirectoryInventory');
$runScript = new ReflectionMethod($service, 'runScript');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$treeHashes = static function (string $path): array {
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $entry) {
        if (!$entry->isFile() && !$entry->isLink()) continue;
        $relative = substr($entry->getPathname(), strlen($path) + 1);
        $hashes[$relative] = $entry->isLink() ? 'link:' . (string) readlink($entry->getPathname()) : hash_file('sha256', $entry->getPathname());
    }
    ksort($hashes, SORT_STRING);
    return $hashes;
};
$removeTree = static function (string $path): void {
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($path);
};

$tempPrefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'boardprep-release-test-storage-';
$fixturePath = getenv('APP_STORAGE_PATH');
$ownsFixture = !is_string($fixturePath) || !str_starts_with($fixturePath, $tempPrefix);
$scratch = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/boardprep-release-runner-regression-' . bin2hex(random_bytes(12));
if (!mkdir($scratch, 0700, true)) throw new RuntimeException('Unable to create release-runner regression scratch storage.');
$realStorageBefore = $treeHashes($realStorage);
$createdFixturePath = null;

try {
    if ($ownsFixture) {
        $fixturePath = $create->invoke(null, $root);
        $createdFixturePath = $fixturePath;
    }
    if (!is_string($fixturePath) || !is_dir($fixturePath)) {
        throw new RuntimeException('Release runner did not provide a private storage fixture.');
    }
    $fixtureInventory = $inventory->invoke(null, 'boardprep-release-test-storage-');
    $assert(isset($fixtureInventory[basename($fixturePath)]), 'Temporary fixture inventory omitted the active private fixture.');
    $fixturePlan = $plan->invoke(null, $realStorage);
    $fixtureBytes = 0;
    $fixtureFiles = 0;
    $fixtureIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixturePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($fixtureIterator as $entry) {
        if ($entry->isLink()) throw new RuntimeException('Isolated release fixture contains a symbolic link.');
        if ($entry->isFile()) {
            $fixtureBytes += $entry->getSize();
            $fixtureFiles++;
        }
    }
    $assert($fixtureBytes === ($fixturePlan['fixture_bytes'] ?? -1), 'Storage preflight byte estimate does not match the copied fixture.');
    $assert($fixtureFiles === ($fixturePlan['fixture_files'] ?? -1), 'Storage preflight file estimate does not match the copied fixture.');
    $assert(!is_file($fixturePath . '/repository-health.json') && !is_file($fixturePath . '/release-readiness.json'), 'Cached release evidence was copied into the test fixture.');
    $assert(!is_file($fixturePath . '/doctor-report.json') && !is_file($fixturePath . '/doctor/latest-report.json'), 'Cached Doctor evidence was copied into the test fixture.');
    $assert(!is_dir($fixturePath . '/recovery') && !is_dir($fixturePath . '/runtime'), 'Historical recovery or runtime storage was copied into the test fixture.');
    foreach (['attempts.json', 'weakness.json', 'question-statistics.json', 'flashcard_state.json'] as $learnerState) {
        $assert(trim((string) file_get_contents($fixturePath . '/' . $learnerState)) === '[]', "Release fixture did not reset {$learnerState}.");
    }

    ini_set('memory_limit', '512M');
    $questionsPath = $fixturePath . '/questions.json';
    $baselineQuestionsHash = hash_file('sha256', $questionsPath);
    $questionInode = fileinode($questionsPath);
    $sourceQuestionInode = fileinode($realStorage . '/questions.json');
    $assert($questionInode !== false && $sourceQuestionInode !== false && $questionInode !== $sourceQuestionInode, 'Canonical question fixture is not an independent copy.');
    $questions = json_decode((string) file_get_contents($questionsPath), true, 512, JSON_THROW_ON_ERROR);
    $assert(is_array($questions) && $questions !== [], 'Release fixture has no valid canonical questions.');
    foreach ($questions as $question) {
        foreach (['timesUsed', 'timesCorrect', 'timesIncorrect', 'updatedAt'] as $usageKey) {
            $assert(!is_array($question) || !array_key_exists($usageKey, $question), 'Release question fixture retained learner usage counters.');
        }
    }

    file_put_contents($fixturePath . '/attempts.json', "[{\"fixture\":true}]\n", LOCK_EX);
    file_put_contents($questionsPath, "[]\n", LOCK_EX);
    file_put_contents($fixturePath . '/runner-created.json', "{}\n", LOCK_EX);
    $assert($treeHashes($realStorage) === $realStorageBefore, 'Writes to private release fixtures changed repository learner or canonical storage.');
    $rebuild->invoke(null, $root, $fixturePath);
    $assert(hash_file('sha256', $questionsPath) === $baselineQuestionsHash, 'Fixture rebuild did not restore canonical content from repository storage.');
    $assert(!is_file($fixturePath . '/runner-created.json'), 'Fixture rebuild retained content created by a prior test.');
    foreach (['attempts.json', 'weakness.json', 'question-statistics.json', 'flashcard_state.json'] as $learnerState) {
        $assert(trim((string) file_get_contents($fixturePath . '/' . $learnerState)) === '[]', "Fixture rebuild retained learner state in {$learnerState}.");
    }

    // Exercise fixture creation with a tiny synthetic repository without another full canonical-tree copy.
    $smallRoot = $scratch . '/small-repository';
    mkdir($smallRoot . '/storage', 0700, true);
    file_put_contents($smallRoot . '/storage/questions.json', "[\n  {\n    \"id\": \"fixture-q\",\n    \"timesUsed\": 5\n  }\n]\n");
    file_put_contents($smallRoot . '/storage/attempts.json', "[{\"private\":true}]\n");
    file_put_contents($smallRoot . '/storage/doctor-report.json', "{\"stale\":true}\n");
    $smallFixture = $create->invoke(null, $smallRoot);
    try {
        $smallQuestions = json_decode((string) file_get_contents($smallFixture . '/questions.json'), true, 512, JSON_THROW_ON_ERROR);
        $assert(!array_key_exists('timesUsed', $smallQuestions[0] ?? []), 'Fixture creation retained usage data in canonical questions.');
        $assert(trim((string) file_get_contents($smallFixture . '/attempts.json')) === '[]', 'Fixture creation copied learner attempts.');
        $assert(!is_file($smallFixture . '/doctor-report.json'), 'Fixture creation copied stale Doctor evidence.');
    } finally {
        $remove->invoke(null, $smallFixture);
    }
    $assert(!is_dir($smallFixture), 'Successful fixture cleanup left temporary files behind.');

    $successScript = $scratch . '/session-success.php';
    $failureScript = $scratch . '/session-failure.php';
    $interruptedScript = $scratch . '/session-interrupted.php';
    file_put_contents($successScript, "<?php echo getenv('APP_SESSION_PATH'), PHP_EOL, sys_get_temp_dir(), PHP_EOL; file_put_contents(sys_get_temp_dir() . '/descendant-temp-proof', 'private'); usleep(200000);\n");
    file_put_contents($failureScript, "<?php fwrite(STDOUT, getenv('APP_SESSION_PATH') . PHP_EOL . sys_get_temp_dir() . PHP_EOL); file_put_contents(sys_get_temp_dir() . '/descendant-temp-proof', 'private'); fwrite(STDERR, 'intentional diagnostic'); exit(7);\n");
    file_put_contents($interruptedScript, "<?php fwrite(STDOUT, getenv('APP_SESSION_PATH') . PHP_EOL . sys_get_temp_dir() . PHP_EOL); file_put_contents(sys_get_temp_dir() . '/descendant-temp-proof', 'private'); fwrite(STDERR, 'simulated interrupted child'); exit(143);\n");
    [$successCodeA, $successOutputA, $successMetricsA] = $runScript->invoke(null, $successScript, $root, $fixturePath);
    [$successCodeB, $successOutputB] = $runScript->invoke(null, $successScript, $root, $fixturePath);
    $sessionA = trim(strtok($successOutputA, "\n") ?: '');
    $sessionB = trim(strtok($successOutputB, "\n") ?: '');
    $temporaryA = trim(explode("\n", $successOutputA)[1] ?? '');
    $temporaryB = trim(explode("\n", $successOutputB)[1] ?? '');
    $assert($successCodeA === 0 && $successCodeB === 0, 'Successful isolated child scripts did not return PASS exit codes.');
    $assert($sessionA !== '' && $sessionB !== '' && $sessionA !== $sessionB, 'Sequential release tests reused a session namespace.');
    $assert($temporaryA === $sessionA && $temporaryB === $sessionB, 'A test subprocess temp directory escaped its private session namespace.');
    $assert(!is_dir($sessionA) && !is_dir($sessionB), 'Successful release child left a session directory behind.');
    $assert(($successMetricsA['session_bytes'] ?? null) === 7, 'Descendant temp growth was not measured before cleanup.');
    $assert(($successMetricsA['session_cleanup_status'] ?? 0) === 1, 'Runner did not verify cleanup after a successful test.');

    [$failureCode, $failureOutput] = $runScript->invoke(null, $failureScript, $root, $fixturePath);
    $failureLines = explode("\n", $failureOutput);
    $failureSession = trim($failureLines[0] ?? '');
    $failureTemporary = trim($failureLines[1] ?? '');
    $assert($failureCode === 7 && str_contains($failureOutput, 'intentional diagnostic'), 'Failed test diagnostics or exit status were lost.');
    $assert($failureSession !== '' && $failureTemporary === $failureSession && !is_dir($failureSession), 'Failed release child left session or descendant temp files behind.');

    [$interruptedCode, $interruptedOutput] = $runScript->invoke(null, $interruptedScript, $root, $fixturePath);
    $interruptedLines = explode("\n", $interruptedOutput);
    $interruptedSession = trim($interruptedLines[0] ?? '');
    $interruptedTemporary = trim($interruptedLines[1] ?? '');
    $assert($interruptedCode === 143 && str_contains($interruptedOutput, 'simulated interrupted child'), 'Interrupted test was incorrectly reported as successful or lost diagnostics.');
    $assert($interruptedSession !== '' && $interruptedTemporary === $interruptedSession && !is_dir($interruptedSession), 'Interrupted release child left session or descendant temp files behind.');

    // Two concurrent runner processes must allocate independent session directories.
    $concurrentChild = $scratch . '/session-concurrent.php';
    $worker = $scratch . '/runner-worker.php';
    file_put_contents($concurrentChild, "<?php echo getenv('APP_SESSION_PATH'), PHP_EOL; usleep(400000);\n");
    $workerSource = "<?php require " . var_export($root . '/app/Core/Autoloader.php', true) . "; "
        . "\\App\\Core\\Autoloader::register(); "
        . "\$method = new ReflectionMethod(\\App\\Services\\RepositoryHealth\\Release\\ReleaseReadinessService::class, 'runScript'); "
        . "[\$code, \$output] = \$method->invoke(null, \$argv[1], \$argv[2], \$argv[3]); "
        . "echo json_encode(['code' => \$code, 'output' => \$output]);\n";
    file_put_contents($worker, $workerSource);
    $workerCommand = static fn(): array => [PHP_BINARY, $worker, $concurrentChild, $root, $fixturePath];
    $workerDescriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $workerA = proc_open($workerCommand(), $workerDescriptors, $workerPipesA, $root);
    $workerB = proc_open($workerCommand(), $workerDescriptors, $workerPipesB, $root);
    $assert(is_resource($workerA) && is_resource($workerB), 'Unable to start concurrent release-runner probes.');
    $workerOutputA = stream_get_contents($workerPipesA[1]) ?: '';
    $workerErrorA = stream_get_contents($workerPipesA[2]) ?: '';
    $workerOutputB = stream_get_contents($workerPipesB[1]) ?: '';
    $workerErrorB = stream_get_contents($workerPipesB[2]) ?: '';
    fclose($workerPipesA[1]); fclose($workerPipesA[2]);
    fclose($workerPipesB[1]); fclose($workerPipesB[2]);
    $workerCodeA = proc_close($workerA);
    $workerCodeB = proc_close($workerB);
    $workerResultA = json_decode($workerOutputA, true, 512, JSON_THROW_ON_ERROR);
    $workerResultB = json_decode($workerOutputB, true, 512, JSON_THROW_ON_ERROR);
    $assert($workerCodeA === 0 && $workerCodeB === 0, 'Concurrent runner probes exited with errors.');
    $assert(($workerResultA['code'] ?? -1) === 0 && ($workerResultB['code'] ?? -1) === 0, 'Concurrent release child scripts did not pass.');
    $concurrentSessionA = trim(strtok((string) ($workerResultA['output'] ?? ''), "\n") ?: '');
    $concurrentSessionB = trim(strtok((string) ($workerResultB['output'] ?? ''), "\n") ?: '');
    $assert($concurrentSessionA !== '' && $concurrentSessionB !== '' && $concurrentSessionA !== $concurrentSessionB, 'Concurrent tests collided on a session namespace.');
    $assert(!is_dir($concurrentSessionA) && !is_dir($concurrentSessionB), 'Concurrent release children left session directories behind.');
    $assert($treeHashes($realStorage) === $realStorageBefore, 'Release storage regressions changed repository learner or canonical data.');

    echo '[PASS] Release tests use one independently copied fixture, reset from repository inputs, avoid source writes, isolate concurrent sessions, preserve failure diagnostics, and clean success/failure sessions.' . PHP_EOL;
} finally {
    if ($createdFixturePath !== null) $remove->invoke(null, $createdFixturePath);
    $removeTree($scratch);
}
