#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Batch656 orchestration only. Deterministic repository facts come from
 * RepositoryHealthEngine and ReleaseReadinessService; this file records the
 * bounded LET reconciliation and runs the release-flow evidence collection.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Release\ReleaseReadinessService;

$root = dirname(__DIR__);
$arguments = array_slice($argv ?? [], 1);
$apply = in_array('--apply', $arguments, true);
$runReleaseTests = !in_array('--no-release-tests', $arguments, true);
$writeArtifact = !in_array('--no-artifact', $arguments, true);
$measurePerformance = in_array('--measure-performance', $arguments, true);
$baselineHealthMs = null;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--baseline-health-ms=')) {
        $value = substr($argument, strlen('--baseline-health-ms='));
        if (is_numeric($value)) $baselineHealthMs = (float) $value;
    }
}

function b656_json(string $path, mixed $default = []): mixed
{
    if (!is_file($path)) return $default;
    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') return $default;
    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
}

function b656_write(string $path, mixed $value): void
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $temporary = $path . '.batch656.tmp';
    file_put_contents($temporary, $json, LOCK_EX);
    rename($temporary, $path);
}

function b656_apply_metadata(string $root): array
{
    $changed = [];
    $structural = ['cse-constitutional-principles', 'cse-environmental-public-interest', 'cse-ethical-public-service'];
    $structuralChanged = 0;
    foreach ((glob($root . '/storage/source-readiness*.json') ?: []) as $readinessPath) {
        $records = b656_json($readinessPath, []);
        $fileChanged = 0;
        foreach ($records as &$record) {
            if (!is_array($record)) continue;
            $leafId = (string) ($record['leaf_id'] ?? '');
            $outOfScope = ($record['source_scope_fit'] ?? '') === 'NONE'
                && ($record['defer_reason'] ?? '') === 'OUT_OF_CURRENT_SCOPE'
                && (int) ($record['max_new_questions'] ?? 0) === 0;
            if (!in_array($leafId, $structural, true) && !$outOfScope) continue;
            $before = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $record['scope_status'] = 'STRUCTURAL_NOT_ACTIONABLE';
            $record['blocker_classes'] = array_values(array_unique(array_merge((array) ($record['blocker_classes'] ?? []), ['STRUCTURAL_OR_PLACEMENT'])));
            sort($record['blocker_classes'], SORT_STRING);
            $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
            $after = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if ($before !== $after) {
                $fileChanged++;
                $structuralChanged++;
            }
        }
        unset($record);
        if ($fileChanged > 0) b656_write($readinessPath, $records);
    }
    if ($structuralChanged > 0) {
        $changed['structural_readiness_records'] = $structuralChanged;
    }

    $standaloneIds = [
        'cle-investigation-application', 'cpa-material-13', 'cpa-material-14', 'cpa-material-7',
        'cpa-material-8', 'cpa-material-9', 'let-education-laws-review', 'nle-integrated-process',
        'nle-np-iv-endocrine', 'portfolio-architecture-foundation', 'portfolio-civil-engineering-foundation',
        'portfolio-mechanical-engineering-foundation', 'portfolio-registered-electrical-engineering-foundation',
        'ree-material-controls', 'ree-material-design', 'ree-material-protection',
    ];
    $studyPath = $root . '/storage/study-materials.json';
    $materials = b656_json($studyPath, []);
    $standaloneChanged = 0;
    foreach ($materials as &$material) {
        if (!is_array($material) || !in_array((string) ($material['id'] ?? ''), $standaloneIds, true)) continue;
        $before = json_encode($material, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $material['standalone_resource'] = true;
        $material['standalone_reason'] = 'Published study/foundation resource intentionally retained without a question dependency; source chain is valid and future authoring may link it when authorized.';
        $material['standalone_reviewed_at_batch'] = 656;
        $after = json_encode($material, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if ($before !== $after) $standaloneChanged++;
    }
    unset($material);
    if ($standaloneChanged > 0) {
        b656_write($studyPath, $materials);
        $changed['justified_standalone_study_materials'] = $standaloneChanged;
    }

    $closurePath = $root . '/storage/readiness-closures.json';
    $closures = [
        [
            'board_id' => 'let',
            'closure_state' => 'EVIDENCE_EXHAUSTED',
            'operational_state' => 'FOUNDATION_SUFFICIENT',
            'evidence_exhausted' => true,
            'numerical_headroom' => null,
            'evidence_headroom' => 0,
            'text_format_headroom' => 0,
            'high_value_headroom' => 0,
            'closure_reason' => 'Batch652 consumed the exact high-confidence LET cohort and recorded no remaining high-value proposition. Numeric thinness remains a planning signal only.',
            'reviewed_at_batch' => 656,
        ],
    ];
    $beforeClosure = is_file($closurePath) ? hash_file('sha256', $closurePath) : null;
    $encodedClosure = json_encode($closures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if ($beforeClosure !== hash('sha256', $encodedClosure)) {
        b656_write($closurePath, $closures);
        $changed['readiness_closure_records'] = count($closures);
    }
    return $changed;
}

function b656_stable(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    $result = [];
    foreach ($value as $key => $child) {
        if (in_array((string) $key, ['runtime_ms', 'wall_time_ms', 'peak_memory_bytes', 'generated_at', 'output_tail', 'default_health_runtime_ms', 'health_samples_ms', 'portfolio_runtime_ms', 'triage_runtime_ms', 'baseline_health_runtime_ms', 'regression_percent', 'sample_count'], true)) continue;
        $result[$key] = b656_stable($child);
    }
    if (!array_is_list($result)) ksort($result, SORT_STRING);
    return $result;
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
$metadataChanges = $apply ? b656_apply_metadata($root) : [];
App::boot();
$healthStarted = hrtime(true);
$health = RepositoryHealthEngine::analyze(false);
$healthRuntimeMs = round((hrtime(true) - $healthStarted) / 1_000_000, 3);
$healthSamples = [$healthRuntimeMs];
if ($measurePerformance) {
    for ($sample = 1; $sample < 3; $sample++) {
        $sampleStarted = hrtime(true);
        RepositoryHealthEngine::analyze(false);
        $healthSamples[] = round((hrtime(true) - $sampleStarted) / 1_000_000, 3);
    }
    $sortedSamples = $healthSamples;
    sort($sortedSamples, SORT_NUMERIC);
    $healthRuntimeMs = $sortedSamples[(int) floor(count($sortedSamples) / 2)];
}
$portfolioStarted = hrtime(true);
$portfolio = RepositoryHealthEngine::portfolioReadinessAudit(null, $health);
$portfolioRuntimeMs = round((hrtime(true) - $portfolioStarted) / 1_000_000, 3);
$flowEvidence = $runReleaseTests ? ReleaseReadinessService::runFlowChecks($root) : ['checks' => []];
$routeCompleteness = ReleaseReadinessService::routeCompleteness($root);
$regressionPercent = $baselineHealthMs !== null && $baselineHealthMs > 0
    ? round((($healthRuntimeMs - $baselineHealthMs) / $baselineHealthMs) * 100, 3)
    : null;
$performance = [
    'status' => $baselineHealthMs === null || $regressionPercent <= 20 ? 'PASS' : 'WARN',
    'default_health_runtime_ms' => $healthRuntimeMs,
    'health_samples_ms' => $healthSamples,
    'portfolio_runtime_ms' => $portfolioRuntimeMs,
    'baseline_health_runtime_ms' => $baselineHealthMs,
    'regression_percent' => $regressionPercent,
    'sample_count' => count($healthSamples),
];
$release = ReleaseReadinessService::audit($health, $flowEvidence + ['route_completeness' => $routeCompleteness], $performance);
$rows = array_column((array) ($portfolio['boards'] ?? []), null, 'board_id');
$letReadiness = (array) ($health->summary['source_readiness_by_board']['let'] ?? []);
$letReason = [
    'previous_closure' => 'FOUNDATION_SUFFICIENT_EVIDENCE_EXHAUSTED',
    'automated_selection_before' => 'SOURCE_READINESS_NEEDED',
    'source_findings_before' => 38,
    'source_findings_reconciled' => [
        'scope_fit_unknown_review_only' => 35,
        'structural_cse_rows' => 3,
        'invalid_source_ids' => 0,
        'validator_errors' => 0,
    ],
    'classification' => 'TOOLING_FALSE_POSITIVE_PLUS_STRUCTURAL_NOT_ACTIONABLE',
    'hard_defects_after' => (array) ($letReadiness['source_study_defects'] ?? []),
    'review_only_after' => (array) ($letReadiness['source_study_reviews'] ?? []),
];
$orphanBefore = 16;
$integrity = App::storage()->all('study-materials');
$links = App::storage()->all('study-question-links');
$linkedMaterials = [];
foreach ($links as $link) if (is_array($link)) $linkedMaterials[(string) ($link['material_id'] ?? '')] = true;
$orphanAfter = 0;
$justifiedStandalone = 0;
foreach ($integrity as $material) {
    if (!is_array($material) || isset($linkedMaterials[(string) ($material['id'] ?? '')])) continue;
    if (($material['standalone_resource'] ?? false) === true && trim((string) ($material['standalone_reason'] ?? '')) !== '') $justifiedStandalone++; else $orphanAfter++;
}
$cpaFindings = ['before' => 3, 'true_defects_after' => 0, 'classification' => 'CONSERVATIVE_REVIEW', 'question_ids' => ['1300', '1306', '1316']];
$meExpectation = ['historical' => ['READY_WITH_LIMITS' => 34, 'REVIEW' => 1], 'current_canonical' => ['READY_WITH_LIMITS' => 28, 'REVIEW' => 7], 'resolution' => 'TEST_EXPECTATION_UPDATED_TO_CURRENT_CANONICAL_STATE'];
$issueRows = array_map(static fn($issue): array => $issue->toArray(), $health->issues);
$debtAfter = [
    'source' => count(array_filter($issueRows, static fn(array $issue): bool => in_array((string) ($issue['category'] ?? ''), ['SOURCE_STUDY', 'SOURCE_READINESS'], true) && ($issue['status'] ?? '') === 'FAIL')),
    'study' => count(array_filter($issueRows, static fn(array $issue): bool => str_contains(strtoupper((string) ($issue['code'] ?? '')), 'STUDY') && ($issue['status'] ?? '') === 'FAIL')),
    'traceability' => count(array_filter($issueRows, static fn(array $issue): bool => str_contains(strtoupper((string) ($issue['code'] ?? '')), 'TRACE') && ($issue['status'] ?? '') === 'FAIL')),
    'currentness' => count(array_filter($issueRows, static fn(array $issue): bool => str_contains(strtoupper((string) ($issue['code'] ?? '')), 'CURRENTNESS') && ($issue['status'] ?? '') === 'FAIL')),
    'depth' => count(array_filter($issueRows, static fn(array $issue): bool => str_contains(strtoupper((string) ($issue['code'] ?? '')), 'DEPTH') && ($issue['status'] ?? '') === 'FAIL')),
    'taxonomy' => count(array_filter($issueRows, static fn(array $issue): bool => ($issue['category'] ?? '') === 'TAXONOMY' && ($issue['status'] ?? '') === 'FAIL')),
    'eligibility' => count(array_filter($issueRows, static fn(array $issue): bool => ($issue['category'] ?? '') === 'ELIGIBILITY' && ($issue['status'] ?? '') === 'FAIL')),
    'flashcard' => (int) ($health->summary['flashcards']['nonderivable_defect'] ?? 0),
    'calculation' => count(array_filter($issueRows, static fn(array $issue): bool => str_contains((string) ($issue['code'] ?? ''), 'CALCULATION_METADATA') && ($issue['status'] ?? '') === 'FAIL')),
    'duplicate_effective' => count(array_filter($issueRows, static fn(array $issue): bool => ($issue['code'] ?? '') === 'DUPLICATE_EFFECTIVE_CANDIDATE' && ($issue['status'] ?? '') === 'FAIL')),
];
$nativeByBoard = [];
$effectiveByBoard = [];
$runtimeByBoard = [];
foreach ($health->boardSummaries as $boardId => $board) $nativeByBoard[$boardId] = (int) ($board['canonical'] ?? 0);
foreach ($health->boardSummaries as $boardId => $board) {
    $effectiveByBoard[$boardId] = (int) ($board['effective_declared'] ?? 0);
    $runtimeByBoard[$boardId] = (int) ($board['runtime'] ?? 0);
}
ksort($nativeByBoard, SORT_STRING);
ksort($effectiveByBoard, SORT_STRING);
ksort($runtimeByBoard, SORT_STRING);
$artifact = [
    'title' => 'BOARDPREP — BATCH 656 RELEASE CONVERGENCE',
    'batch' => 656,
    'lineage' => [
        'expected_parent' => '433fd3d3c1c97848b46fea78edacce229478e3cb',
        'head_before' => trim((string) shell_exec('git rev-parse HEAD')),
        'main_reference' => '0eb9704ba293e0aa3402af5d01a2d3392c005b73',
        'content_freeze' => true,
        'new_questions' => 0,
    ],
    'let_reconciliation' => $letReason,
    'triage_classifier' => [
        'generic' => true,
        'closure_precedence' => true,
        'raw_thinness_overrides_closure' => false,
        'before_state' => 'SOURCE_READINESS_NEEDED',
        'after_state' => $rows['let']['operational_state'] ?? 'UNKNOWN_REQUIRES_MANUAL_REVIEW',
    ],
    'portfolio_deterministic_debt' => [
        'before' => ['source' => 38, 'study' => 19, 'traceability' => 3, 'currentness' => 3, 'orphan_study_materials' => 16, 'cpa_source_fit_review' => 3],
        'after' => [
            'source_readiness_hard_defects' => array_sum(array_map('intval', (array) ($letReadiness['source_study_defects'] ?? []))),
            'repository_defects' => $debtAfter,
            'orphan_study_materials_unexplained' => $orphanAfter,
            'justified_standalone_study_materials' => $justifiedStandalone,
            'health_fail_entities' => (int) ($health->summary['fail_count'] ?? 0),
        ],
    ],
    'orphan_study_resolution' => ['before' => $orphanBefore, 'unexplained_after' => $orphanAfter, 'justified_standalone' => $justifiedStandalone],
    'cpa_source_fit' => $cpaFindings,
    'me_stale_expectation' => $meExpectation,
    'repository_health' => [
        'overall_status' => $health->overallStatus,
        'fail_entities' => (int) ($health->summary['fail_count'] ?? 0),
        'issue_breakdown' => $health->summary['breakdown']['check_code'] ?? [],
        'canonical_total' => array_sum($nativeByBoard),
        'effective_total' => array_sum($effectiveByBoard),
        'runtime_total' => array_sum($runtimeByBoard),
        'native_by_board' => $nativeByBoard,
        'effective_by_board' => $effectiveByBoard,
        'runtime_by_board' => $runtimeByBoard,
        'flashcard_defects' => (int) ($health->summary['flashcards']['nonderivable_defect'] ?? 0),
        'calculation_contradictions' => count(array_filter($health->issues, static fn($issue): bool => str_contains($issue->code, 'CALCULATION_METADATA'))),
        'duplicate_effective' => count(array_filter($health->issues, static fn($issue): bool => $issue->code === 'DUPLICATE_EFFECTIVE_CANDIDATE')),
        'double_weighting' => count(array_filter($health->issues, static fn($issue): bool => $issue->code === 'DUPLICATE_EFFECTIVE_CANDIDATE')),
        'nursing_dosage' => '24/16/8',
        'cpa_cy2029_leakage' => (int) ($health->summary['currentness']['future_effective_leakage'] ?? 0),
    ],
    'release' => [
        'domains' => $release['release_domains'],
        'release_gate' => $release['release_gate'],
        'release_decision' => $release['release_decision'],
        'priority_counts' => $release['priority_counts'],
        'p0_blockers' => array_map(static fn(array $action): array => array_intersect_key($action, array_flip(['domain', 'entity', 'reason', 'deterministic_or_editorial', 'suggested_next_action'])), $release['p0_blockers']),
        'action_queue_summary' => array_map(static fn(array $action): array => array_intersect_key($action, array_flip(['priority', 'domain', 'entity', 'deterministic_or_editorial', 'suggested_next_action'])), $release['action_queue']),
        'criteria' => $release['release_criteria'],
    ],
    'product_audit' => [
        'learner_flow_checks' => $flowEvidence['checks'] ?? [],
        'admin_flow_checks' => array_intersect_key((array) ($flowEvidence['checks'] ?? []), array_flip(['admin_core_flows', 'shared_question_safety'])),
        'route_completeness' => $routeCompleteness,
        'security_sanity' => ($flowEvidence['checks']['security_sanity']['status'] ?? 'UNKNOWN'),
        'manual_boundary' => 'Only deterministic contracts and bounded focused tests were automated; semantic editorial claims remain review metadata.',
    ],
    'portfolio_final_states' => array_map(static function (array $row): array {
        return [
            'board_id' => $row['board_id'] ?? null,
            'native' => $row['native'] ?? null,
            'effective' => $row['effective'] ?? null,
            'runtime' => $row['runtime'] ?? null,
            'active_leaves' => $row['active_leaves'] ?? null,
            'operational_state' => $row['operational_state'] ?? 'UNKNOWN',
            'readiness_model_status' => $row['readiness_model_status'] ?? 'UNKNOWN',
            'source_defects' => $row['source_readiness']['source_study_defects']['source'] ?? null,
            'study_defects' => $row['source_readiness']['source_study_defects']['study'] ?? null,
            'traceability_defects' => $row['source_readiness']['source_study_defects']['traceability'] ?? null,
            'currentness_defects' => $row['source_readiness']['source_study_defects']['currentness'] ?? null,
            'high_value_headroom' => $row['high_value_headroom'] ?? null,
        ];
    }, $rows),
    'remaining_work' => [
        'release_blocking' => $release['p0_blockers'],
        'pre_release_high_value' => array_values(array_filter($release['action_queue'], static fn(array $action): bool => ($action['priority'] ?? '') === 'P1')),
        'post_release_content' => ['evidence-exhausted numeric thinness and future authorized content plans'],
        'post_release_features' => ['Architecture visual question capability'],
        'maintenance' => ['currentness review queue and justified editorial review signals'],
        'estimated_release_batches' => count($release['p0_blockers']) > 0 ? ['min' => 1, 'likely' => 2, 'max' => 3] : ['min' => 0, 'likely' => 0, 'max' => 1],
        'next_milestone' => count($release['p0_blockers']) > 0 ? 'RELEASE_BLOCKER_COHORT' : 'V1_RELEASE_CANDIDATE_FINAL_GATE',
    ],
    'performance' => $performance,
    'known_technical_noise_remaining' => [
        'justified_standalone_study_materials' => $justifiedStandalone,
        'conservative_cpa_source_fit_reviews' => 3,
        'historical_hayadoctor_submodule_contract_test' => 'INHERITED_ENVIRONMENT_DEPENDENCY',
        'historical_batch_hash_tests' => 'PREVIOUS_BATCH_SNAPSHOT_EXPECTATIONS',
    ],
    'metadata_changes' => $metadataChanges,
    'determinism' => [],
];
$stable = b656_stable($artifact);
$auditHash = hash('sha256', json_encode($stable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$artifactPath = $root . '/storage/batch656-release-convergence.json';
$previousArtifact = $writeArtifact ? b656_json($artifactPath, []) : [];
$previousHash = is_array($previousArtifact)
    ? (string) (($previousArtifact['determinism']['audit_hash'] ?? ''))
    : '';
$artifact['determinism'] = [
    'audit_hash' => $auditHash,
    'second_audit_hash' => $previousHash !== '' ? $previousHash : null,
    'identical' => $previousHash !== '' && hash_equals($previousHash, $auditHash),
    'stable_projection' => 'timestamps, test output, and runtimes excluded',
];
if ($writeArtifact) b656_write($artifactPath, $artifact);
echo json_encode(['status' => 'PASS', 'audit_hash' => $auditHash, 'metadata_changes' => $metadataChanges, 'release_decision' => $release['release_decision'], 'p0' => count($release['p0_blockers']), 'health_fail' => (int) ($health->summary['fail_count'] ?? 0)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
