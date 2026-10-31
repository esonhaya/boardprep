<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Developer\FullDoctorEvidenceService;
use App\Services\Developer\ReadinessDashboardService;
use App\Storage\JsonStorage;
function readinessAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$fixture = sys_get_temp_dir() . '/boardprep-readiness-evidence-' . bin2hex(random_bytes(8));
$reports = $fixture . '/docs/reports';
mkdir($reports, 0700, true);
$now = new DateTimeImmutable('2026-11-01T01:00:00+00:00');
$head = str_repeat('a', 40);
$reader = new FullDoctorEvidenceService();

$writeArtifact = static function (string $base, array $overrides = []) use ($reports, $head, $fixture): array {
    $generatedAt = (string) ($overrides['generated_at'] ?? '2026-11-01T00:00:00+00:00');
    $generatedDate = new DateTimeImmutable($generatedAt);
    $gate = (string) ($overrides['gate'] ?? 'PASS');
    $exit = (int) ($overrides['exit_code'] ?? 0);
    $counts = is_array($overrides['counts'] ?? null) ? $overrides['counts'] : ['P0' => 0, 'P1' => 0, 'P2' => 1, 'P3' => 2];
    $domains = [
        'LEARNER_CORE_FLOWS' => ['status' => 'PASS', 'evidence' => ['tests' => 10, 'passed' => 10]],
        'ATTEMPT_SAFETY' => ['status' => 'PASS', 'evidence' => ['tests' => 4, 'passed' => 4]],
        'RESULT_REVIEW' => ['status' => 'PASS', 'evidence' => ['tests' => 3, 'passed' => 3]],
        'HISTORY' => ['status' => 'PASS', 'evidence' => ['tests' => 2, 'passed' => 2]],
        'SIMULATOR_RECOVERY' => ['status' => 'PASS', 'evidence' => ['tests' => 1, 'passed' => 1]],
        'SECURITY_SANITY' => ['status' => 'PASS', 'evidence' => ['tests' => 6, 'passed' => 6]],
        'SOURCE_TRACEABILITY' => ['status' => 'REVIEW', 'evidence' => [
            'review_signals' => 2,
            'study_mapping_inventory' => ['exact_links_unreviewed_or_status_unknown' => 17],
        ]],
        'ELIGIBILITY_INTEGRITY' => ['status' => 'PASS', 'evidence' => ['failures' => 0]],
    ];
    if (isset($overrides['browser'])) $domains['BROWSER_ACCEPTANCE'] = ['status' => (string) $overrides['browser'], 'evidence' => []];
    $payload = [
        'generated_at' => $generatedAt,
        'evidence_state' => 'CURRENT_EVIDENCE',
        'health' => ['overall_status' => (string) ($overrides['health'] ?? 'REVIEW')],
        'release_readiness' => [
            'release_gate' => ['status' => $gate], 'release_decision' => 'TECHNICAL_REVIEW',
            'priority_counts' => $counts, 'release_domains' => $domains,
        ],
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $path = $reports . '/' . $base . '.json';
    file_put_contents($path, $json);
    file_put_contents($reports . '/' . $base . '.meta.txt', implode("\n", [
        'COMMAND=php tools/doctor.php --release --json',
        'CWD=' . $fixture,
        'START_UTC=' . $generatedDate->modify('-30 seconds')->format('Y-m-d\TH:i:s\Z'),
        'END_UTC=' . $generatedAt,
        'EXIT_CODE=' . $exit,
        'TESTED_SHA=' . ($overrides['tested_sha'] ?? $head),
        'JSON=' . $base . '.json',
        'ARTIFACT_SHA256=' . hash('sha256', $json),
        '',
    ]));
    return [$path, $json];
};

try {
    $base = 'batch-733-full-doctor-20261101T000000Z';
    [$path, $json] = $writeArtifact($base);
    $latest = $reader->latest($fixture, $now);
    readinessAssert($latest['artifact'] === 'docs/reports/' . $base . '.json'
        && $latest['state'] === 'UNVERIFIED', 'Latest artifact discovery accepted no-current-commit provenance or selected the wrong artifact.');
    $evidence = $reader->read($fixture, 'docs/reports/' . $base . '.json', 'docs/reports/' . $base . '.meta.txt', $head, $now);
    readinessAssert($evidence['state'] === 'CURRENT_PASS', 'Valid current Full Doctor evidence was not accepted: ' . $evidence['reason']);
    readinessAssert(($evidence['priority_counts']['P0'] ?? null) === 0 && ($evidence['priority_counts']['P2'] ?? null) === 1, 'Doctor counts were not projected with their P-level keys.');
    readinessAssert(($evidence['functional_tests']['passed'] ?? null) === 20, 'Bounded learner flow summary was incorrect.');
    readinessAssert(($evidence['integration_tests']['passed'] ?? null) === 10 && ($evidence['integration_tests']['total'] ?? null) === 10, 'Integration test totals were projected incorrectly.');
    readinessAssert(($evidence['security_tests']['status'] ?? null) === 'PASS'
        && ($evidence['security_tests']['passed'] ?? null) === 6
        && ($evidence['security_tests']['total'] ?? null) === 6, 'Security test totals were projected incorrectly.');
    readinessAssert($evidence['browser_acceptance'] === 'NOT_EXECUTED', 'Absent browser evidence was represented as executed.');
    readinessAssert(($evidence['source_traceability']['unreviewed_or_unknown_links'] ?? null) === 17, 'Traceability count lost its source evidence.');

    $missing = $reader->read($fixture, 'docs/reports/batch-733-full-doctor-20261101T000001Z.json', 'docs/reports/batch-733-full-doctor-20261101T000001Z.meta.txt', $head, $now);
    readinessAssert($missing['state'] === 'UNVERIFIED', 'Missing Doctor artifact was not shown as unverified.');
    $traversal = $reader->read($fixture, 'docs/reports/../../etc/passwd', 'docs/reports/../../etc/passwd.meta.txt', $head, $now);
    readinessAssert($traversal['state'] === 'UNVERIFIED' && $traversal['reason'] === 'artifact_path_invalid', 'Artifact path traversal was accepted.');

    $corruptBase = 'batch-733-full-doctor-20261101T000002Z';
    file_put_contents($reports . '/' . $corruptBase . '.json', '{corrupt');
    file_put_contents($reports . '/' . $corruptBase . '.meta.txt', 'EXIT_CODE=0');
    $corrupt = $reader->read($fixture, 'docs/reports/' . $corruptBase . '.json', 'docs/reports/' . $corruptBase . '.meta.txt', $head, $now);
    readinessAssert($corrupt['state'] === 'UNVERIFIED' && $corrupt['reason'] === 'artifact_json_invalid', 'Malformed JSON was trusted.');

    $wrongBase = 'batch-733-full-doctor-20261101T000003Z';
    $writeArtifact($wrongBase, ['tested_sha' => str_repeat('b', 40)]);
    $wrongHead = $reader->read($fixture, 'docs/reports/' . $wrongBase . '.json', 'docs/reports/' . $wrongBase . '.meta.txt', $head, $now);
    readinessAssert($wrongHead['state'] === 'STALE', 'Wrong-commit Doctor evidence was shown as current.');

    $browserBase = 'batch-733-full-doctor-20261101T000006Z';
    $writeArtifact($browserBase, ['browser' => 'PASS', 'tested_sha' => str_repeat('b', 40)]);
    $staleBrowser = $reader->read($fixture, 'docs/reports/' . $browserBase . '.json', 'docs/reports/' . $browserBase . '.meta.txt', $head, $now);
    readinessAssert($staleBrowser['browser_acceptance'] === 'STALE_RECORDED_PASS'
        && $staleBrowser['security_tests']['status'] === 'STALE_RECORDED_PASS', 'Stale browser or security PASS was presented as current.');

    $wrongCommandBase = 'batch-733-full-doctor-20261101T000007Z';
    [$wrongCommandPath, $wrongCommandJson] = $writeArtifact($wrongCommandBase);
    file_put_contents($reports . '/' . $wrongCommandBase . '.meta.txt', str_replace(
        'COMMAND=php tools/doctor.php --release --json', 'COMMAND=php tools/unrelated.php --release --json',
        file_get_contents($reports . '/' . $wrongCommandBase . '.meta.txt')
    ));
    $wrongCommand = $reader->read($fixture, 'docs/reports/' . $wrongCommandBase . '.json', 'docs/reports/' . $wrongCommandBase . '.meta.txt', $head, $now);
    readinessAssert($wrongCommand['state'] === 'UNVERIFIED', 'An unrelated command was accepted as Full Doctor provenance.');

    $oversizedBase = 'batch-733-full-doctor-20261101T000008Z';
    $oversizedPath = $reports . '/' . $oversizedBase . '.json';
    $oversizedHandle = fopen($oversizedPath, 'wb');
    if (!is_resource($oversizedHandle) || !ftruncate($oversizedHandle, FullDoctorEvidenceService::MAX_ARTIFACT_BYTES + 1)) {
        throw new RuntimeException('Unable to build the bounded-read artifact fixture.');
    }
    fclose($oversizedHandle);
    $oversized = $reader->read($fixture, 'docs/reports/' . $oversizedBase . '.json', 'docs/reports/' . $oversizedBase . '.meta.txt', $head, $now);
    readinessAssert($oversized['state'] === 'UNVERIFIED' && $oversized['reason'] === 'artifact_size_invalid', 'Oversized Doctor evidence was accepted.');

    $boundedFixture = $fixture . '/bounded-directory';
    $boundedReports = $boundedFixture . '/docs/reports';
    mkdir($boundedReports, 0700, true);
    for ($index = 0; $index <= FullDoctorEvidenceService::MAX_DIRECTORY_ENTRIES; $index++) {
        file_put_contents($boundedReports . '/ignored-' . $index, '');
    }
    $bounded = $reader->latest($boundedFixture, $now);
    readinessAssert($bounded['reason'] === 'reports_directory_entry_limit_exceeded', 'Doctor report discovery did not stop at its directory-entry bound.');

    $staleBase = 'batch-733-full-doctor-20261030T000000Z';
    $writeArtifact($staleBase, ['generated_at' => '2026-10-30T00:00:00+00:00']);
    $stale = $reader->read($fixture, 'docs/reports/' . $staleBase . '.json', 'docs/reports/' . $staleBase . '.meta.txt', $head, $now);
    readinessAssert($stale['state'] === 'STALE', 'Old Doctor evidence was shown as fresh.');

    $failureBase = 'batch-733-full-doctor-20261101T000004Z';
    $writeArtifact($failureBase, ['gate' => 'FAIL', 'exit_code' => 1, 'counts' => ['P0' => 1, 'P1' => 1, 'P2' => 0, 'P3' => 0]]);
    $failure = $reader->read($fixture, 'docs/reports/' . $failureBase . '.json', 'docs/reports/' . $failureBase . '.meta.txt', $head, $now);
    readinessAssert($failure['state'] === 'FAIL' && $failure['priority_counts']['P1'] === 1, 'Failed release-gate counts were hidden.');

    $metadataBase = 'batch-733-full-doctor-20261101T000005Z';
    [$metadataPath] = $writeArtifact($metadataBase);
    file_put_contents($reports . '/' . $metadataBase . '.meta.txt', 'EXIT_CODE=0');
    $unproven = $reader->read($fixture, 'docs/reports/' . $metadataBase . '.json', 'docs/reports/' . $metadataBase . '.meta.txt', $head, $now);
    readinessAssert($unproven['state'] === 'UNVERIFIED' && $unproven['priority_counts']['P2'] === 1, 'A valid report with missing provenance either lost its recorded counts or claimed PASS.');

    $storagePath = $fixture . '/dashboard-storage';
    $storage = new JsonStorage($storagePath);
    $storage->create('learner-question-reports', [
        'id' => 'qir-' . str_repeat('1', 32), 'user_id' => 'learner-' . str_repeat('1', 32),
        'question_id' => 'private-question-one', 'description' => 'PRIVATE REPORT TEXT', 'status' => 'SUBMITTED',
    ]);
    $storage->create('learner-question-reports', [
        'id' => 'qir-' . str_repeat('2', 32), 'user_id' => 'learner-' . str_repeat('2', 32),
        'question_id' => 'private-question-two', 'description' => 'PRIVATE REPORT TEXT TWO', 'status' => 'UNDER_REVIEW',
    ]);
    $dashboard = (new ReadinessDashboardService($storage))->build(dirname(__DIR__, 2));
    readinessAssert($dashboard['technical_gate']['status'] === 'UNVERIFIED', 'Legacy evidence without tested commit was represented as current PASS.');
    readinessAssert(($dashboard['academic_approval']['pending_proposals'] ?? null) === 276, 'Pending academic proposals were not grounded in the current disposition inventory.');
    readinessAssert(($dashboard['academic_approval']['reviewer_authorization'] ?? '') === 'SCOPED_PER_ACCOUNT; checked by academic workflow; not grantable from this dashboard', 'Dashboard implies reviewer authorization can be granted here.');
    readinessAssert(($dashboard['learner_question_reports']['counts']['total'] ?? null) === 2
        && ($dashboard['learner_question_reports']['counts']['submitted'] ?? null) === 1
        && ($dashboard['learner_question_reports']['counts']['under_review'] ?? null) === 1, 'Learner concern aggregates were incorrect.');
    readinessAssert(!str_contains(json_encode($dashboard, JSON_THROW_ON_ERROR), 'PRIVATE REPORT TEXT'), 'Dashboard exposed private learner report content.');
    readinessAssert(($dashboard['public_release_authorization']['status'] ?? '') === 'NOT_AUTHORIZED', 'Technical evidence implied public release authorization.');

    $largeReportFixture = [];
    for ($index = 1; $index <= 5001; $index++) {
        $largeReportFixture[] = [
            'id' => 'qir-load-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT),
            'user_id' => 'learner-' . str_repeat(($index % 2) === 0 ? '1' : '2', 32),
            'question_id' => 'synthetic-question-' . $index,
            'description' => 'Synthetic private report body ' . $index,
            'status' => 'SUBMITTED',
        ];
    }
    $storage->replace('learner-question-reports', $largeReportFixture);
    $aggregationStart = microtime(true);
    $boundedDashboard = (new ReadinessDashboardService($storage))->build(dirname(__DIR__, 2), $now);
    $aggregationMilliseconds = round((microtime(true) - $aggregationStart) * 1000, 2);
    readinessAssert(($boundedDashboard['learner_question_reports']['status'] ?? '') === 'LIMIT_EXCEEDED'
        && ($boundedDashboard['learner_question_reports']['counts']['total'] ?? -1) === 0
        && !str_contains(json_encode($boundedDashboard, JSON_THROW_ON_ERROR), 'Synthetic private report body'),
        'Large question-report collections were not bounded or private report content leaked.');

    echo "[PASS] Doctor artifact provenance, JSON/schema validation, current/stale/wrong-commit/failure/missing cases, path safety, browser-not-executed, academic backlog, scoped reviewer policy, and private report aggregation.\n";
    echo 'REPORT_AGGREGATION_5001_ROWS_MS=' . $aggregationMilliseconds . "\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($fixture);
}
