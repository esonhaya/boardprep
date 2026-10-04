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
    'STUDY_TRACEABILITY', 'FLASHCARD_INTEGRITY', 'CALCULATION_INTEGRITY',
    'ELIGIBILITY_INTEGRITY', 'SECURITY_SANITY',
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

$unknownReport = RepositoryHealthEngine::analyzeCollections([]);
$unknownRelease = ReleaseReadinessService::audit($unknownReport);
$assert(($unknownRelease['release_domains']['LEARNER_CORE_FLOWS']['status'] ?? '') === 'UNKNOWN', 'Missing flow evidence was invented as a passing result.');
$assert(($unknownRelease['release_gate']['status'] ?? '') === 'REVIEW', 'Missing flow evidence did not keep the release gate in review.');

$allFlowEvidence = ['checks' => []];
foreach ($critical as $domain) {
    $key = strtolower($domain);
    $allFlowEvidence['checks'][$key] = ['status' => 'PASS'];
}
$allFlowEvidence['route_completeness'] = ['active' => 1, 'stub' => 0, 'broken' => 0, 'unknown' => 0];
$editorialRelease = ReleaseReadinessService::audit(
    RepositoryHealthEngine::analyzeCollections([]),
    $allFlowEvidence,
    ['status' => 'PASS']
);
$assert(($editorialRelease['release_gate']['status'] ?? '') === 'PASS', 'Nonblocking evidence/thinness debt incorrectly blocked an otherwise passing gate.');
$assert(($editorialRelease['priority_counts']['P0'] ?? -1) === 0, 'Nonblocking editorial debt became a P0 action.');

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
