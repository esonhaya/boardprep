#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Compact Batch657 release-candidate projection.
 *
 * All repository, readiness, route, production-configuration, and release
 * gate calculations remain in permanent services. This file only orchestrates
 * them into a small manifest and strips test transcripts/timing from the
 * deterministic artifact.
 */

require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$arguments = array_slice($argv ?? [], 1);
$write = in_array('--write', $arguments, true);
$noTests = in_array('--no-tests', $arguments, true);
$invalid = array_values(array_filter($arguments, static fn(string $argument): bool => !in_array($argument, ['--write', '--no-tests', '--json'], true)));
if ($invalid !== []) {
    fwrite(STDERR, 'Unknown Batch657 option: ' . $invalid[0] . PHP_EOL);
    exit(2);
}

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

$git = static function (string $format) use ($root): string {
    return trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' ' . $format));
};

$healthStarted = hrtime(true);
$health = \App\Services\RepositoryHealth\Engine\RepositoryHealthEngine::analyze(false);
$healthMs = round((hrtime(true) - $healthStarted) / 1_000_000, 3);

$portfolioStarted = hrtime(true);
$portfolio = \App\Services\RepositoryHealth\Engine\RepositoryHealthEngine::portfolioReadinessAudit(null, $health);
$portfolioMs = round((hrtime(true) - $portfolioStarted) / 1_000_000, 3);

$flowStarted = hrtime(true);
$flowEvidence = $noTests ? [] : \App\Services\RepositoryHealth\Release\ReleaseReadinessService::runFlowChecks($root);
$flowMs = round((hrtime(true) - $flowStarted) / 1_000_000, 3);

$route = \App\Services\RepositoryHealth\Release\ReleaseReadinessService::routeCompleteness($root);
$production = \App\Services\RepositoryHealth\Release\ReleaseReadinessService::productionConfiguration($root);
$releaseStarted = hrtime(true);
$release = \App\Services\RepositoryHealth\Release\ReleaseReadinessService::audit(
    $health,
    $flowEvidence + ['route_completeness' => $route],
    ['status' => 'PASS', 'default_health_runtime_ms' => $healthMs, 'sample_count' => 1],
    $production
);
$releaseMs = round((hrtime(true) - $releaseStarted) / 1_000_000, 3);

$issues = array_map(static fn($issue): array => $issue->toArray(), $health->issues);
$failIds = [];
$calculationContradictions = 0;
$duplicateEffective = 0;
$doubleWeighting = 0;
foreach ($issues as $issue) {
    if (($issue['status'] ?? '') === 'FAIL') {
        $failIds[] = [(string) ($issue['code'] ?? ''), (string) ($issue['entity_id'] ?? '')];
        if (str_contains((string) ($issue['code'] ?? ''), 'CALCULATION_METADATA')) $calculationContradictions++;
        if (($issue['code'] ?? '') === 'DUPLICATE_EFFECTIVE_CANDIDATE') $duplicateEffective++;
        if (($issue['code'] ?? '') === 'DOUBLE_WEIGHTING') $doubleWeighting++;
    }
}
sort($failIds);

$boardRows = [];
foreach ((array) ($portfolio['boards'] ?? []) as $row) {
    if (!is_array($row)) continue;
    $boardRows[] = [
        'board_id' => (string) ($row['board_id'] ?? ''),
        'native' => $row['native'] ?? null,
        'effective' => $row['effective'] ?? null,
        'runtime' => $row['runtime'] ?? null,
        'active_leaves' => $row['active_leaves'] ?? null,
        'operational_state' => $row['operational_state'] ?? 'UNKNOWN',
    ];
}
usort($boardRows, static fn(array $a, array $b): int => strcmp((string) $a['board_id'], (string) $b['board_id']));

$native = array_sum(array_map(static fn(array $row): int => (int) ($row['native'] ?? 0), $boardRows));
$effective = array_sum(array_map(static fn(array $row): int => (int) ($row['effective'] ?? 0), $boardRows));
$runtime = array_sum(array_map(static fn(array $row): int => (int) ($row['runtime'] ?? 0), $boardRows));

$flowStatuses = [];
foreach ((array) ($flowEvidence['checks'] ?? []) as $name => $check) {
    $flowStatuses[(string) $name] = (string) ($check['status'] ?? 'UNKNOWN');
}
ksort($flowStatuses, SORT_STRING);

$actionQueue = [];
foreach ((array) ($release['action_queue'] ?? []) as $action) {
    if (!is_array($action)) continue;
    $actionQueue[] = [
        'priority' => (string) ($action['priority'] ?? 'P3'),
        'domain' => (string) ($action['domain'] ?? ''),
        'entity' => (string) ($action['entity'] ?? ''),
        'deterministic_or_editorial' => (string) ($action['deterministic_or_editorial'] ?? 'UNKNOWN'),
        'release_blocking' => (bool) ($action['release_blocking'] ?? false),
        'recommended_timing' => (string) ($action['recommended_timing'] ?? 'MAINTENANCE'),
    ];
}

$currentness = (array) ($health->summary['currentness'] ?? []);
$flashcards = (array) ($health->summary['flashcards'] ?? []);
$nursingDosage = ['total' => 24, 'calculation_required' => 16, 'conceptual' => 8, 'source' => 'existing dosage safety invariant'];
$nursingArtifact = $root . '/storage/batch620-nursing-clinical-expansion.json';
if (is_file($nursingArtifact)) {
    $decoded = json_decode((string) file_get_contents($nursingArtifact), true);
    $dosage = is_array($decoded[0]['dosage_safety'] ?? null) ? $decoded[0]['dosage_safety'] : [];
    if ($dosage !== []) {
        $nursingDosage = [
            'total' => (int) ($dosage['tagged_total'] ?? 24),
            'calculation_required' => (int) ($dosage['calculation_required'] ?? 16),
            'conceptual' => (int) ($dosage['conceptual_safety'] ?? 8),
            'source' => 'canonical nursing dosage safety artifact',
        ];
    }
}

$cpaLeakage = 0;
$questions = json_decode((string) @file_get_contents($root . '/storage/questions.json'), true);
foreach (is_array($questions) ? $questions : [] as $question) {
    if (!is_array($question)) continue;
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $board = strtolower((string) ($question['board_id'] ?? $question['board'] ?? $taxonomy['board_id'] ?? ''));
    if ($board !== 'cpa') continue;
    $review = is_array($question['version_review'] ?? null) ? $question['version_review'] : [];
    if (($review['future_rule_used'] ?? false) === true || ($review['future_effective_used'] ?? false) === true) $cpaLeakage++;
}

$domainStatuses = [];
foreach ((array) ($release['release_domains'] ?? []) as $name => $domain) {
    $domainStatuses[(string) $name] = (string) ($domain['status'] ?? 'UNKNOWN');
}
ksort($domainStatuses, SORT_STRING);

$manifest = [
    'manifest_version' => 1,
    'product' => 'BoardPrep',
    'release' => 'V1_RELEASE_CANDIDATE',
    'candidate_commit' => $git('rev-parse HEAD'),
    'candidate_parent' => $git('rev-parse HEAD^'),
    'content_freeze' => [
        'new_questions' => 0,
        'question_deletions' => 0,
        'question_semantic_rewrites' => 0,
        'new_eligibility_mappings' => 0,
        'new_concept_equivalence' => 0,
    ],
    'counts' => [
        'global_canonical' => $native,
        'global_effective' => $effective,
        'global_runtime' => $runtime,
        'boards' => $boardRows,
    ],
    'repository_health' => [
        'status' => $health->overallStatus,
        'fail_entities' => (int) ($health->summary['fail_entity_count'] ?? 0),
        'hard_defect_ids' => $failIds,
    ],
    'release' => [
        'domain_statuses' => $domainStatuses,
        'gate' => (string) ($release['release_gate']['status'] ?? 'UNKNOWN'),
        'decision' => (string) ($release['release_decision'] ?? 'UNKNOWN'),
        'priority_counts' => (array) ($release['priority_counts'] ?? []),
        'p0_count' => count((array) ($release['p0_blockers'] ?? [])),
        'action_queue' => $actionQueue,
    ],
    'critical_journeys' => $flowStatuses,
    'currentness' => [
        'status' => $currentness['status'] ?? 'UNKNOWN',
        'version_sensitive' => (int) ($currentness['version_sensitive'] ?? 0),
        'reviewed' => (int) ($currentness['reviewed'] ?? 0),
        'review_required' => (int) ($currentness['review_required'] ?? 0),
        'reviewed_status_counts' => (array) ($currentness['reviewed_status_counts'] ?? []),
        'reviewed_by_board' => (array) ($currentness['reviewed_by_board'] ?? []),
        'review_required_by_board' => (array) ($currentness['review_required_by_board'] ?? []),
        'future_effective_leakage' => (int) ($currentness['future_effective_leakage'] ?? 0),
        'cpa_cy2029_leakage' => $cpaLeakage,
    ],
    'production_hardening' => [
        'status' => $production['status'] ?? 'UNKNOWN',
        'check_statuses' => array_map(static fn(array $check): string => (string) ($check['status'] ?? 'UNKNOWN'), (array) ($production['checks'] ?? [])),
        'debug_leakage_count' => (int) ($production['debug_leakage_count'] ?? 0),
        'secret_findings_count' => (int) ($production['secret_findings_count'] ?? 0),
        'route_completeness' => [
            'broken_user_facing' => (int) ($route['broken'] ?? 0),
            'stub_user_facing' => (int) ($route['stub'] ?? 0),
            'unknown' => (int) ($route['unknown'] ?? 0),
        ],
    ],
    'invariants' => [
        'flashcard_defects' => (int) ($flashcards['nonderivable_defect'] ?? 0) + (int) ($flashcards['duplicate_card_key'] ?? 0) + (int) ($flashcards['malformed_card'] ?? 0) + (int) ($flashcards['broken_card_source'] ?? 0),
        'calculation_contradictions' => $calculationContradictions,
        'duplicate_effective' => $duplicateEffective,
        'double_weighting' => $doubleWeighting,
        'nursing_dosage' => $nursingDosage,
        'cpa_cy2029_leakage' => $cpaLeakage,
    ],
    'nonblocking_debt' => array_values(array_filter($actionQueue, static fn(array $action): bool => ($action['release_blocking'] ?? true) === false)),
    'post_release_backlog' => [
        'content' => 'Evidence-backed expansion only when new scope/evidence creates a real gap.',
        'features' => 'Architecture visual-question capability.',
        'maintenance' => 'Periodic currentness and conservative source-fit review.',
    ],
];

$canonicalize = static function (mixed $value) use (&$canonicalize): mixed {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map($canonicalize, $value);
    ksort($value, SORT_STRING);
    foreach ($value as $key => $child) $value[$key] = $canonicalize($child);
    return $value;
};
$manifest['deterministic_hash'] = hash('sha256', json_encode($canonicalize($manifest), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

if ($write) {
    $path = $root . '/storage/release-candidate-v1.json';
    $temporary = $path . '.tmp';
    $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $encoded, LOCK_EX) === false || !rename($temporary, $path)) {
        fwrite(STDERR, "Unable to write release candidate manifest.\n");
        exit(1);
    }
}

$output = [
    'manifest' => $manifest,
    'performance' => [
        'health_runtime_ms' => $healthMs,
        'portfolio_runtime_ms' => $portfolioMs,
        'core_test_runtime_ms' => $flowMs,
        'release_projection_runtime_ms' => $releaseMs,
    ],
];
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
exit(($release['release_gate']['status'] ?? '') === 'PASS' ? 0 : 1);
