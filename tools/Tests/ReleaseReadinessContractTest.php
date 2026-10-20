<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Readiness\ReadinessClosureService;
use App\Services\RepositoryHealth\Release\ReleaseReadinessService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$critical = [
    'LEARNER_CORE_FLOWS', 'ATTEMPT_SAFETY', 'RESULT_REVIEW', 'PROGRESS_STATE',
    'HISTORY', 'SIMULATOR_RECOVERY', 'ADMIN_CORE_FLOWS', 'SHARED_QUESTION_SAFETY',
    'DEVELOPER_HEALTH', 'STUDY_TRACEABILITY', 'FLASHCARD_INTEGRITY', 'CALCULATION_INTEGRITY',
    'ELIGIBILITY_INTEGRITY', 'SECURITY_SANITY', 'RELEASE_TEST_STORAGE',
];
$passing = [];
foreach ($critical as $domain) $passing[$domain] = ['status' => 'PASS'];

$gate = ReleaseReadinessService::evaluateGate($passing, []);
$assert(($gate['status'] ?? '') === 'PASS', 'Passing critical domains did not open the release gate.');
$blocked = ReleaseReadinessService::evaluateGate($passing, [['priority' => 'P0']]);
$assert(($blocked['status'] ?? '') === 'FAIL', 'A real P0 blocker did not close the release gate.');
$reviewing = $passing;
$reviewing['LEARNER_CORE_FLOWS'] = ['status' => 'REVIEW'];
$reviewGate = ReleaseReadinessService::evaluateGate($reviewing, []);
$assert(($reviewGate['status'] ?? '') === 'REVIEW', 'Unknown/review critical evidence was incorrectly treated as PASS.');

$closure = ReadinessClosureService::summary([
    'board_id' => 'LET',
    'closure_state' => 'EVIDENCE_EXHAUSTED',
    'operational_state' => 'FOUNDATION_SUFFICIENT',
    'closure_reason' => 'Evidence-backed high-value headroom is exhausted.',
    'evidence_exhausted' => true,
    'high_value_headroom' => 0,
]);
$assert(($closure['board_id'] ?? '') === 'let', 'Closure normalization did not canonicalize the board ID.');
$assert(($closure['operational_state'] ?? '') === 'FOUNDATION_SUFFICIENT', 'Closure state was not retained.');

$routes = ReleaseReadinessService::routeCompleteness(dirname(__DIR__, 2));
$assert((int) ($routes['active'] ?? 0) > 0, 'Release route scan found no registered routes.');
$assert((int) ($routes['stub'] ?? 0) === 0, 'Release route scan found an unexpected stub.');

$releaseSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/RepositoryHealth/Release/ReleaseReadinessService.php');
$assert(str_contains($releaseSource, 'createFlowTestStorage')
    && str_contains($releaseSource, 'rebuildFlowTestStorage')
    && str_contains($releaseSource, 'RELEASE_TEST_MIN_FREE_BYTES')
    && str_contains($releaseSource, 'register_shutdown_function')
    && str_contains($releaseSource, 'session_cleanup_status')
    && str_contains($releaseSource, 'temporaryDirectoryInventory')
    && str_contains($releaseSource, "['APP_STORAGE_PATH']"),
    'Release flow tests no longer rebuild isolated storage with free-space and interruption safeguards.');
$assert(str_contains($releaseSource, 'tools/Tests/BackupManifestPathSafetyTest.php'),
    'The backup restore path traversal regression is not registered in release security validation.');

$production = ReleaseReadinessService::productionConfiguration(dirname(__DIR__, 2));
$assert(($production['status'] ?? '') === 'PASS', 'Production configuration contract did not pass.');
$assert((int) ($production['secret_findings_count'] ?? -1) === 0, 'Production configuration reported a tracked secret.');
$assert((int) ($production['debug_leakage_count'] ?? -1) === 0, 'Production configuration reported executable debug leakage.');

$unknownReport = RepositoryHealthEngine::analyzeCollections([]);
$unknownRelease = ReleaseReadinessService::audit($unknownReport);
$assert(($unknownRelease['release_domains']['LEARNER_CORE_FLOWS']['status'] ?? '') === 'UNKNOWN', 'Missing flow evidence was invented as a passing result.');
$assert(($unknownRelease['release_domains']['DEVELOPER_HEALTH']['status'] ?? '') === 'UNKNOWN', 'Missing Doctor integrity evidence was invented as a passing result.');
$assert(($unknownRelease['release_gate']['status'] ?? '') === 'REVIEW', 'Missing flow evidence did not keep the release gate in review.');

$allFlowEvidence = ['checks' => []];
foreach ($critical as $domain) {
    if ($domain === 'DEVELOPER_HEALTH') {
        $allFlowEvidence['checks']['doctor_integrity'] = ['status' => 'PASS'];
        continue;
    }
    $key = strtolower($domain);
    $allFlowEvidence['checks'][$key] = ['status' => 'PASS'];
}
$allFlowEvidence['checks']['flow_storage'] = ['status' => 'PASS', 'cleanup_status' => 'PASS'];
$allFlowEvidence['route_completeness'] = ['active' => 1, 'stub' => 0, 'broken' => 0, 'unknown' => 0];
$editorialRelease = ReleaseReadinessService::audit(
    RepositoryHealthEngine::analyzeCollections([]),
    $allFlowEvidence,
    ['status' => 'PASS']
);
$assert(($editorialRelease['release_gate']['status'] ?? '') === 'PASS', 'Nonblocking evidence/thinness debt incorrectly blocked an otherwise passing gate.');
$incompleteStorageRelease = ReleaseReadinessService::audit(
    RepositoryHealthEngine::analyzeCollections([]),
    ['checks' => ['flow_storage' => ['status' => 'REVIEW', 'reason' => 'Insufficient test storage; no scripts ran.']]],
    ['status' => 'PASS']
);
$assert(($incompleteStorageRelease['release_domains']['RELEASE_TEST_STORAGE']['status'] ?? '') === 'REVIEW', 'Insufficient storage was not surfaced as a release evidence review.');
$assert(($incompleteStorageRelease['release_gate']['status'] ?? '') === 'REVIEW', 'Incomplete storage evidence was allowed to pass the release gate.');
$assert(($editorialRelease['priority_counts']['P0'] ?? -1) === 0, 'Nonblocking editorial debt became a P0 action.');
$contentAction = array_values(array_filter(
    (array) ($editorialRelease['action_queue'] ?? []),
    static fn(array $action): bool => ($action['entity'] ?? '') === 'evidence-exhausted-and-thin-leaves'
))[0] ?? [];
$assert(array_key_exists('release_blocking', $contentAction) && ($contentAction['release_blocking'] ?? true) === false, 'Action queue omitted release-blocking classification.');
$assert(($contentAction['recommended_timing'] ?? '') === 'POST_RELEASE', 'Action queue omitted post-release timing.');

$storedCurrentness = RepositoryHealthEngine::analyzeCollections([
    'questions' => [
        ['id' => 'stored-pass', 'board_id' => 'civil-service', 'version_sensitive' => true, 'version_review_status' => 'PASS'],
        ['id' => 'stored-limits', 'board_id' => 'architecture', 'version_sensitive' => true, 'currentness_review_status' => 'CURRENT_WITH_LIMITS'],
    ],
]);
$currentness = (array) (($storedCurrentness->summary['currentness'] ?? []));
$assert(($currentness['review_required'] ?? -1) === 0, 'Explicit stored currentness outcomes were treated as unresolved.');
$assert(($currentness['reviewed'] ?? -1) === 2, 'Explicit stored currentness outcomes were not counted as reviewed.');
$assert(($currentness['reviewed_status_counts']['PASS'] ?? 0) === 1 && ($currentness['reviewed_status_counts']['CURRENT_WITH_LIMITS'] ?? 0) === 1, 'Stored currentness states were not summarized.');

$futureReport = RepositoryHealthEngine::analyzeCollections([
    'questions' => [[
        'id' => 'future-firewall-fixture',
        'version_sensitive' => true,
        'version_review' => ['future_rule_used' => true],
    ]],
]);
$futureRelease = ReleaseReadinessService::audit($futureReport, $allFlowEvidence, ['status' => 'PASS']);
$assert(($futureRelease['release_domains']['CURRENTNESS']['status'] ?? '') === 'FAIL', 'Future-effective content was not release-blocking.');
$assert(($futureRelease['release_gate']['status'] ?? '') === 'FAIL', 'Future-effective leakage did not close the release gate.');

echo "release_readiness_contract=PASS\n";
