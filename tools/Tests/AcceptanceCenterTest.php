<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Acceptance\AcceptanceFeedbackReport;
use App\Services\Acceptance\AcceptanceScenarioCatalog;
use App\Services\Acceptance\AcceptanceSessionStore;

$root = dirname(__DIR__, 2);
$fixture = sys_get_temp_dir() . '/boardprep-acceptance-test-' . bin2hex(random_bytes(8));
$storePath = $fixture . '/private-feedback';
$canonicalFiles = [
    'storage/questions.json',
    'storage/attempts.json',
    'storage/weakness.json',
    'storage/flashcard_state.json',
    'storage/question-statistics.json',
];
$canonicalBefore = [];
foreach ($canonicalFiles as $relative) {
    if (is_file($root . '/' . $relative)) {
        $canonicalBefore[$relative] = hash_file('sha256', $root . '/' . $relative);
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    $assert(false, $message);
};
$probeLocalBoundary = static function (string $remoteAddress, string $hostHeader) use ($root): array {
    $rootLiteral = var_export($root, true);
    $script = "require_once {$rootLiteral} . '/app/Core/Autoloader.php';"
        . " App\\Core\\Autoloader::register();"
        . " require {$rootLiteral} . '/bootstrap/app.php';"
        . ' $_SERVER["REMOTE_ADDR"] = getenv("ACCEPTANCE_PROBE_REMOTE_ADDR") ?: "";'
        . ' $_SERVER["HTTP_HOST"] = getenv("ACCEPTANCE_PROBE_HOST") ?: "";'
        . " register_shutdown_function(static function (): void { fwrite(STDERR, 'STATUS=' . http_response_code()); });"
        . ' App\\Controllers\\AcceptanceCenterController::build();';
    $environment = getenv();
    $environment = is_array($environment) ? $environment : [];
    $environment['APP_ENV'] = 'testing';
    $environment['APP_DEBUG'] = 'false';
    $environment['ACCEPTANCE_PROBE_REMOTE_ADDR'] = $remoteAddress;
    $environment['ACCEPTANCE_PROBE_HOST'] = $hostHeader;
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, '-r', $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
        $environment
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to run the local Acceptance Center boundary fixture.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), (string) $stdout, (string) $stderr];
};

try {
    $scenarios = AcceptanceScenarioCatalog::all($root);
    $expectedIds = array_map(static fn(int $number): string => sprintf('MVP-%02d', $number), range(1, 12));
    $assert(array_column($scenarios, 'case_id') === $expectedIds, 'The acceptance guide did not load all canonical MVP scenarios in order.');
    foreach ($scenarios as $scenario) {
        $assert($scenario['routes'] !== [], 'A canonical case has no real learner entry route.');
        foreach ($scenario['routes'] as $route) {
            $assert(str_starts_with($route['path'], '/') && !str_starts_with($route['path'], '//'), 'A guided route can navigate outside the application.');
        }
    }
    foreach ([['192.0.2.4', '127.0.0.1'], ['127.0.0.1', 'example.invalid']] as [$remoteAddress, $hostHeader]) {
        [$exitCode, $stdout, $stderr] = $probeLocalBoundary($remoteAddress, $hostHeader);
        $assert(
            $exitCode === 0 && $stdout === 'Not found.' && str_contains($stderr, 'STATUS=404'),
            'The Acceptance Center did not reject a nonlocal request with a not-found response.'
        );
    }

    $questionsHash = $canonicalBefore['storage/questions.json'] ?? '';
    $store = new AcceptanceSessionStore($storePath, $root);
    $ownerOne = hash('sha256', 'fixture owner one');
    $ownerTwo = hash('sha256', 'fixture owner two');
    $build = str_repeat('a', 40);
    $store->ownerOverview($ownerOne);
    $first = $store->createSession($ownerOne, $build);
    $sessionId = $first['session_id'];
    $assert(($first['build_sha'] ?? '') === $build, 'The feedback session did not retain the exact committed build SHA.');
    $assert(($first['environment'] ?? '') === 'local-isolated-preview', 'The session environment was not recorded accurately.');
    $store->saveMetadata($ownerOne, $sessionId, [
        'tester_alias' => 'Owner fixture',
        'device' => 'Android phone fixture',
        'operating_system' => 'Android 15 fixture',
        'browser' => 'Chrome fixture',
        'viewport' => '390x844 CSS px',
    ]);

    $firstComment = "Keep these exact words — including | and **Markdown**.\nA second line with ``` markers.";
    $draft = AcceptanceSessionStore::emptyObservation();
    $draft['category'] = 'CONFUSING_UX';
    $draft['priority'] = 'MEDIUM';
    $draft['comments'] = $firstComment;
    $draft['expected_behavior'] = "I expected a clear button.\nI expected it to save.";
    $draft['actual_behavior'] = 'I could not tell whether it saved.';
    $draft['suggested_improvement'] = 'Show a saved message.';
    $draft['reproduction_steps'] = "1. Open the page.\n2. Tap Save.";
    $draft['evidence_reference'] = 'phone-note-01.txt';
    $store->saveDraft($ownerOne, $sessionId, 'MVP-03', $draft);

    // A new store instance represents an interrupted process or a closed browser.
    $resumedStore = new AcceptanceSessionStore($storePath, $root);
    $resumed = $resumedStore->getSession($ownerOne, $sessionId);
    $assert(($resumed['cases']['MVP-03']['draft']['comments'] ?? '') === $firstComment, 'An interrupted draft did not recover exactly.');
    $assert(($resumed['active_case'] ?? '') === 'MVP-03', 'The current guided test did not resume.');
    $assert(($resumed['build_sha'] ?? '') === $build, 'Resuming changed the build identity.');

    $store->addObservation($ownerOne, $sessionId, 'MVP-03', $draft);
    $second = AcceptanceSessionStore::emptyObservation();
    $second['category'] = 'ACCESSIBILITY';
    $second['priority'] = 'HIGH';
    $second['comments'] = 'The keyboard focus was difficult to see.';
    $store->addObservation($ownerOne, $sessionId, 'MVP-03', $second);
    $record = $store->getSession($ownerOne, $sessionId);
    $observationIds = array_column($record['cases']['MVP-03']['observations'], 'observation_id');
    $store->moveObservation($ownerOne, $sessionId, 'MVP-03', $observationIds[1], 'up');
    $ordered = $store->getSession($ownerOne, $sessionId)['cases']['MVP-03']['observations'];
    $assert(($ordered[0]['comments'] ?? '') === $second['comments'], 'Personal observations could not be reorganized.');
    $edited = $ordered[1];
    $edited['comments'] = 'Edited without changing the other note.';
    $store->editObservation($ownerOne, $sessionId, 'MVP-03', $edited['observation_id'], $edited);
    $store->deleteObservation($ownerOne, $sessionId, 'MVP-03', $ordered[0]['observation_id']);
    $remaining = $store->getSession($ownerOne, $sessionId)['cases']['MVP-03']['observations'];
    $assert(count($remaining) === 1 && $remaining[0]['comments'] === 'Edited without changing the other note.', 'Editing or deleting one note damaged other feedback.');
    $assert(($remaining[0]['expected_behavior'] ?? '') === $draft['expected_behavior'], 'Observation edits did not preserve the other saved fields.');

    $throws(
        static fn() => $store->addObservation($ownerOne, $sessionId, 'MVP-03', AcceptanceSessionStore::emptyObservation()),
        'An empty observation was accepted.'
    );
    $throws(
        static fn() => $store->setOutcome($ownerOne, $sessionId, 'MVP-03', 'PASS', false),
        'PASS was accepted without explicit human confirmation.'
    );
    $store->setOutcome($ownerOne, $sessionId, 'MVP-03', 'PASS', true);

    $general = AcceptanceSessionStore::emptyObservation();
    $general['category'] = 'IMPROVEMENT';
    $general['priority'] = 'UNSURE';
    $general['comments'] = 'Please keep suggestions separate from failures.';
    $store->addGeneralFeedback($ownerOne, $sessionId, $general);
    $generalTwo = AcceptanceSessionStore::emptyObservation();
    $generalTwo['category'] = 'BUG';
    $generalTwo['comments'] = 'A separate general bug note.';
    $store->addGeneralFeedback($ownerOne, $sessionId, $generalTwo);
    $generalRecords = $store->getSession($ownerOne, $sessionId)['general_feedback'];
    $generalId = $generalRecords[0]['observation_id'];
    $general['comments'] = 'Updated general suggestion.';
    $store->editGeneralFeedback($ownerOne, $sessionId, $generalId, $general);
    $store->moveGeneralFeedback($ownerOne, $sessionId, $generalId, 'down');
    $assert(($store->getSession($ownerOne, $sessionId)['general_feedback'][1]['comments'] ?? '') === 'Updated general suggestion.', 'General feedback could not be edited and reordered.');

    $store->recordLaunch($ownerOne, $sessionId, 'MVP-10', '/quiz?exam=LET&topic=__no_such_topic__');
    $throws(
        static fn() => $store->recordLaunch($ownerOne, $sessionId, 'MVP-10', 'https://example.invalid/'),
        'An external or arbitrary guided path was accepted.'
    );
    $throws(
        static fn() => $store->getSession($ownerTwo, $sessionId),
        'A different browser owner accessed another tester session.'
    );
    $throws(
        static fn() => $store->getSession($ownerOne, '../../storage/questions.json'),
        'An unsafe session ID was accepted as a filesystem path.'
    );
    $throws(
        static fn() => $store->setOutcome($ownerOne, $sessionId, 'MVP-99', 'PASS', true),
        'A noncanonical test case was accepted.'
    );

    $report = AcceptanceFeedbackReport::build($store->getSession($ownerOne, $sessionId), $root);
    $encoded = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $assert(($report['PROJECT'] ?? '') === 'BoardPrep' && ($report['REPORT_TYPE'] ?? '') === 'HUMAN_ACCEPTANCE_FEEDBACK', 'The feedback export identity is invalid.');
    $assert(($report['REPORT_SCHEMA_VERSION'] ?? '') === '1.0', 'The feedback export schema version is missing.');
    $assert(($report['TESTER_ALIAS'] ?? '') === 'Owner fixture'
        && ($report['DEVICE'] ?? '') === 'Android phone fixture'
        && ($report['OS_AND_VERSION'] ?? '') === 'Android 15 fixture'
        && ($report['BROWSER'] ?? '') === 'Chrome fixture'
        && ($report['VIEWPORT'] ?? '') === '390x844 CSS px', 'The report omitted real tester environment metadata fields.');
    $assert(($report['TESTS_TOTAL'] ?? 0) === 12 && ($report['TESTS_PASSED'] ?? 0) === 1, 'The human result counters do not match recorded outcomes.');
    $assert(($report['OVERALL_HUMAN_ACCEPTANCE_STATUS'] ?? '') === 'NOT_RUN', 'Unresolved tests were incorrectly counted as human acceptance.');
    $assert(($report['CASE_RESULTS'][2]['OBSERVATIONS'][0]['comments'] ?? '') === 'Edited without changing the other note.', 'The feedback export changed the original comment text.');
    $assert(str_contains($encoded, 'phone-note-01.txt') && str_contains($encoded, 'Updated general suggestion.'), 'The structured export omitted evidence or general feedback.');
    $assert(!str_contains($encoded, $ownerOne) && !str_contains($encoded, $ownerTwo), 'The export exposed a private owner token hash.');
    $assert(!str_contains($encoded, 'PHPSESSID') && !str_contains($encoded, 'BOARDPREP_ACCEPTANCE_OWNER'), 'The export contains session credentials.');
    $assert(count((array) ($report['ALL_REPORTED_BUGS'] ?? [])) === 1, 'The export did not group actual bug observations.');
    $requestedImprovements = (array) ($report['ALL_REQUESTED_IMPROVEMENTS'] ?? []);
    $assert(
        count($requestedImprovements) === 2
            && ($report['CASE_RESULTS'][2]['HUMAN_RESULT'] ?? '') === 'PASS'
            && in_array('Show a saved message.', array_column($requestedImprovements, 'suggested_improvement'), true),
        'The export did not group explicit improvement requests while preserving the test result.'
    );
    $assert(!str_contains($encoded, 'learner_identity') && !str_contains($encoded, 'raw_export'), 'The feedback export contains learner collection fields.');

    $ownerOverview = $store->ownerOverview($ownerOne);
    $secondSession = $store->createSession($ownerOne, str_repeat('b', 40));
    $assert($secondSession['session_id'] !== $sessionId, 'Starting a new test session overwrote the earlier session.');
    $assert(count($ownerOverview['sessions']) === 1, 'Unexpected session history before starting a second session.');
    $assert(count($store->ownerOverview($ownerOne)['sessions']) === 2, 'Earlier sessions were not preserved.');
    $store->selectSession($ownerOne, $sessionId);
    $assert(($store->ownerOverview($ownerOne)['session_id'] ?? '') === $sessionId, 'An earlier saved session could not be resumed.');

    $insideRepository = $root . '/storage/.acceptance-test-must-not-exist';
    $throws(
        static fn() => new AcceptanceSessionStore($insideRepository, $root),
        'Private acceptance data was allowed inside the application repository.'
    );
    $assert((fileperms($storePath) & 0777) === 0700, 'The private report directory is not owner-only.');
    $assert((fileperms($storePath . '/sessions/' . $sessionId . '.json') & 0777) === 0600, 'A private feedback report is not owner-only.');

    foreach ($canonicalBefore as $relative => $hash) {
        $assert(hash_file('sha256', $root . '/' . $relative) === $hash, 'Acceptance Center tests modified canonical learner data: ' . $relative);
    }
    echo "[PASS] Canonical 12-case mapping, isolated durable sessions, draft recovery, outcomes, editable and reorderable feedback, safe routes, CSRF-ready private storage, and export integrity.\n";
} finally {
    if (is_dir($fixture)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($fixture);
    }
}
