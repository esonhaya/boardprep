<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$create = new ReflectionMethod(\App\Services\RepositoryHealth\Release\ReleaseReadinessService::class, 'createFlowTestStorage');
$remove = new ReflectionMethod(\App\Services\RepositoryHealth\Release\ReleaseReadinessService::class, 'removeFlowTestStorage');
$copy = new ReflectionMethod(\App\Services\RepositoryHealth\Release\ReleaseReadinessService::class, 'copyFlowTestStorageTree');
$restore = new ReflectionMethod(\App\Services\RepositoryHealth\Release\ReleaseReadinessService::class, 'restoreFlowTestStorage');
$storagePath = $create->invoke(null, $root);
$baselinePath = $storagePath . '.baseline';

try {
    $copy->invoke(null, $storagePath, $baselinePath);
    foreach ([
        'doctor-report.json',
        'doctor/latest-report.json',
        'repository-health.json',
        'release-readiness.json',
        '.storage.lock',
    ] as $cachedPath) {
        if (is_file($storagePath . '/' . $cachedPath)) {
            throw new RuntimeException("Release test storage copied cached evidence: {$cachedPath}");
        }
    }

    if (is_dir($storagePath . '/doctor/simulation-sessions')) {
        $sessions = array_values(array_diff(scandir($storagePath . '/doctor/simulation-sessions') ?: [], ['.', '..']));
        if ($sessions !== []) {
            throw new RuntimeException('Release test storage copied historical simulator sessions.');
        }
    }

    foreach (['attempts.json', 'weakness.json', 'question-statistics.json', 'flashcard_state.json'] as $learnerState) {
        if (trim((string) file_get_contents($storagePath . '/' . $learnerState)) !== '[]') {
            throw new RuntimeException("Release test storage did not reset {$learnerState}.");
        }
    }

    ini_set('memory_limit', '512M');
    $questions = json_decode((string) file_get_contents($storagePath . '/questions.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($questions) || $questions === []) {
        throw new RuntimeException('Release test questions are not valid JSON content.');
    }
    foreach ($questions as $question) {
        foreach (['timesUsed', 'timesCorrect', 'timesIncorrect', 'updatedAt'] as $usageKey) {
            if (is_array($question) && array_key_exists($usageKey, $question)) {
                throw new RuntimeException('Release test questions retained learner usage counters.');
            }
        }
    }

    $questionsHash = hash_file('sha256', $storagePath . '/questions.json');
    if ($questionsHash === false
        || file_put_contents($storagePath . '/questions.json', "[]\n", LOCK_EX) === false
        || file_put_contents($storagePath . '/test-created-content.json', "{}\n", LOCK_EX) === false) {
        throw new RuntimeException('Unable to mutate the isolated release fixture for restoration coverage.');
    }
    $restore->invoke(null, $baselinePath, $storagePath);
    if (hash_file('sha256', $storagePath . '/questions.json') !== $questionsHash
        || is_file($storagePath . '/test-created-content.json')
        || trim((string) file_get_contents($storagePath . '/attempts.json')) !== '[]') {
        throw new RuntimeException('Release scripts do not restore the same clean content and learner fixture between runs.');
    }

    echo "[PASS] Release test storage excludes cached Doctor evidence and historical sessions, resets learner state, and validates deterministic question JSON.\n";
} finally {
    $remove->invoke(null, $baselinePath);
    $remove->invoke(null, $storagePath);
}
