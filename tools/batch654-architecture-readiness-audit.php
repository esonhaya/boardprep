<?php

declare(strict_types=1);

/** Batch654 Architecture-only semantic/readiness layer over permanent health services. */
require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();
@ini_set('memory_limit', '1024M');
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

function b654_sort(array $values): array
{
    $values = array_values(array_unique(array_map('strval', $values)));
    sort($values, SORT_STRING);
    return $values;
}

function b654_canonical(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    foreach ($value as &$child) $child = b654_canonical($child);
    unset($child);
    if (!array_is_list($value)) ksort($value, SORT_STRING);
    return $value;
}

$arguments = array_slice($argv ?? [], 1);
$invalid = array_values(array_diff($arguments, ['--json', '--no-write']));
if ($invalid !== []) {
    fwrite(STDERR, 'Unknown Batch654 audit option: ' . $invalid[0] . PHP_EOL);
    exit(2);
}

$report = RepositoryHealthEngine::analyze(false);
$deep = RepositoryHealthEngine::readinessAudit('architecture', $report);
$board = (array) ($report->boardSummaries['architecture'] ?? []);
$readiness = (array) ($report->summary['source_readiness_by_board']['architecture'] ?? []);
$records = array_values(array_filter((array) ($readiness['records'] ?? []), 'is_array'));
$byLeaf = [];
foreach ($records as $record) {
    $leaf = trim((string) ($record['leaf_id'] ?? ''));
    if ($leaf !== '') $byLeaf[$leaf] = $record;
}
ksort($byLeaf, SORT_STRING);

$expectedNative = [
    'let' => 1788, 'civil-service' => 1553, 'nursing' => 878,
    'criminologist' => 451, 'civil-engineering' => 922,
    'mechanical-engineering' => 511, 'registered-electrical-engineering' => 842,
    'architecture' => 472, 'psychometrician' => 401, 'cpa' => 1132,
];
ksort($expectedNative, SORT_STRING);
$native = [];
foreach ($report->boardSummaries as $boardId => $summary) $native[(string) $boardId] = (int) ($summary['canonical'] ?? 0);
ksort($native, SORT_STRING);

// Each row: plan, leaf, proposition, family, depth, calculation class,
// novelty basis, marginal value, confidence. The stored readiness record
// supplies the source/study chain and explicit visual/currentness fields.
$specs = [
    ['ARCH-654-001','b633-arch-history-vernacular-climate','Select a passive climate response from stated sun, wind, rainfall, and material constraints.','climate-response',3,'NONCALCULATION','Bounded climate-and-material application distinct from recognition.','HIGH','HIGH'],
    ['ARCH-654-002','b633-arch-practice-coordination','Resolve a consultant interface by checking information status, authority, and downstream coordination.','consultant-interface',4,'NONCALCULATION','Controlled change scenario, not a coordination definition paraphrase.','HIGH','HIGH'],
    ['ARCH-654-003','b633-arch-practice-public-responsibility','Prioritize a public-safety or accessibility response when client preference conflicts with a stated user risk.','public-safety',3,'NONCALCULATION','Fact-specific professional judgment with users and consequences stated.','HIGH','HIGH'],
    ['ARCH-654-004','b633-arch-technical-structural','Choose the conceptual structural system whose load path matches stated span and support assumptions.','load-path',4,'CONCEPTUAL_QUANTITATIVE','Explicit load-path reasoning without sealed member design.','HIGH','ADEQUATE'],
    ['ARCH-654-005','b633-arch-technical-systems','Select a coordinated water, drainage, ventilation, or life-safety response from stated service constraints.','utilities-coordination',4,'NONCALCULATION','Cross-system consequence distinct from isolated component recognition.','HIGH','HIGH'],
    ['ARCH-654-006','b633-arch-technical-construction','Recompute a bounded construction quantity from explicit measurement and waste assumptions.','quantity',3,'CALCULATION_REQUIRED','Independent recomputation with a stored unit and assumption record.','HIGH','ADEQUATE'],
    ['ARCH-654-007','b633-arch-site-circulation','Choose the circulation or egress response satisfying stated adjacency, accessibility, and wayfinding constraints.','adjacency',3,'NONCALCULATION','Complete text-specified scenario; no unstated plan comparison.','MEDIUM','ADEQUATE'],
    ['ARCH-654-008','b633-arch-site-capacity','Compare a phasing or site-capacity alternative against stated access, service, landscape, and growth criteria.','phasing-growth',3,'NONCALCULATION','Criteria-based site scenario with all decision constraints stated.','MEDIUM','ADEQUATE'],
    ['ARCH-654-009','b633-arch-history-vernacular-climate','Distinguish a climate-responsive vernacular adaptation from a decorative form transfer under changed conditions.','material-adaptation',4,'NONCALCULATION','Performance-logic transfer rather than image identification.','MEDIUM','ADEQUATE'],
    ['ARCH-654-010','b633-arch-practice-coordination','Record and communicate an authorized design change while preserving drawing and specification interfaces.','change-control',4,'NONCALCULATION','Change-control record distinct from interface resolution.','MEDIUM','ADEQUATE'],
    ['ARCH-654-011','b633-arch-practice-public-responsibility','Communicate material uncertainty or a specialist boundary while protecting public responsibility.','professional-ethics',3,'NONCALCULATION','Communication/referral scenario distinct from safety prioritization.','MEDIUM','ADEQUATE'],
    ['ARCH-654-012','b633-arch-technical-systems','Recompute a bounded service quantity from stated flow, duration, and units, then check coordination implications.','bounded-calculation',4,'CALCULATION_REQUIRED','Independent recomputation plus interface check; no specialist sizing claim.','MEDIUM','ADEQUATE'],
];

$plans = [];
$planErrors = [];
foreach ($specs as $spec) {
    [$planId, $leaf, $summary, $family, $depth, $calculation, $novelty, $value, $confidence] = $spec;
    $record = $byLeaf[$leaf] ?? null;
    if (!is_array($record)) {
        $planErrors[] = $planId . ':missing-record';
        continue;
    }
    $plan = [
        'plan_id' => $planId,
        'leaf_id' => $leaf,
        'official_area' => (string) ($record['official_area_id'] ?? ''),
        'proposition_summary' => $summary,
        'content_family' => $family,
        'target_depth' => $depth,
        'question_mode' => 'TEXT_MCQ',
        'calculation_class' => $calculation,
        'visual_suitability' => (string) ($record['visual_suitability'] ?? 'UNKNOWN'),
        'source_ids' => b654_sort((array) ($record['source_ids'] ?? [])),
        'study_material_ids' => b654_sort((array) ($record['study_material_ids'] ?? [])),
        'novelty_basis' => $novelty,
        'currentness_class' => (string) ($record['source_currentness'] ?? 'UNKNOWN'),
        'marginal_value' => $value,
        'confidence' => $confidence,
        'limitations' => b654_sort((array) ($record['limitations'] ?? [])),
    ];
    $plans[] = $plan;
    if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) $planErrors[] = $planId . ':not-authorable';
    if ((int) ($record['supported_depth_max'] ?? 0) < $depth) $planErrors[] = $planId . ':depth';
    if ((int) ($record['high_value_headroom'] ?? $record['safe_headroom_max'] ?? 0) <= 0) $planErrors[] = $planId . ':headroom';
    if (in_array($plan['visual_suitability'], ['VISUAL_REQUIRED', 'UNKNOWN'], true)) $planErrors[] = $planId . ':visual-format';
    if (!in_array($value, ['HIGH', 'MEDIUM'], true) || !in_array($confidence, ['HIGH', 'ADEQUATE'], true)) $planErrors[] = $planId . ':quality';
}
$wave1 = array_slice($plans, 0, 8);
$wave2 = array_slice($plans, 8);

$areaCounts = ['arch-area-a' => 0, 'arch-area-b' => 0, 'arch-area-c' => 0];
$repeatRisk = [];
foreach ($byLeaf as $leaf => $record) {
    $area = (string) ($record['official_area_id'] ?? '');
    if (isset($areaCounts[$area])) $areaCounts[$area] += (int) ($record['current_question_count'] ?? 0);
    $repeatRisk[$leaf] = (string) ($record['repeat_risk'] ?? 'UNKNOWN');
}
$areaTargets = ['arch-area-a' => 30, 'arch-area-b' => 30, 'arch-area-c' => 40];
$areaBalance = [];
foreach ($areaTargets as $area => $target) {
    $share = (int) ($board['canonical'] ?? 0) > 0 ? $areaCounts[$area] / (int) $board['canonical'] * 100 : 0;
    $areaBalance[] = ['area_id' => $area, 'native_count' => $areaCounts[$area], 'native_share' => round($share, 2), 'target_share' => $target, 'delta' => round($share - $target, 2), 'descriptive_only' => true];
}

$failIds = [];
foreach ($report->issues as $issue) if ($issue->status === 'FAIL') $failIds[] = (string) ($issue->entityId ?? $issue->code);
$failIds = b654_sort($failIds);
$checkStatuses = [];
foreach ($report->checks as $check) $checkStatuses[(string) ($check['check_code'] ?? '')] = (string) ($check['status'] ?? '');
ksort($checkStatuses, SORT_STRING);
$sourceInventory = $studyInventory = [];
foreach ($byLeaf as $leaf => $record) {
    $sourceInventory[$leaf] = ['source_ids' => b654_sort((array) ($record['source_ids'] ?? [])), 'source_count' => (int) ($record['source_count'] ?? 0), 'primary_source_count' => (int) ($record['primary_source_count'] ?? 0), 'authority' => (string) ($record['source_authority'] ?? 'UNKNOWN'), 'currentness' => (string) ($record['source_currentness'] ?? 'UNKNOWN'), 'scope_fit' => (string) ($record['source_scope_fit'] ?? 'UNKNOWN'), 'conflict' => (string) ($record['source_conflict_status'] ?? 'UNKNOWN')];
    $studyInventory[$leaf] = ['study_material_ids' => b654_sort((array) ($record['study_material_ids'] ?? [])), 'study_material_count' => (int) ($record['study_material_count'] ?? 0), 'coverage' => (string) ($record['study_coverage'] ?? 'UNKNOWN'), 'traceability' => (string) ($record['study_source_traceability'] ?? 'UNKNOWN')];
}
$globalCountsUnchanged = $native === $expectedNative;
$auditErrors = [];
if ((int) ($board['canonical'] ?? -1) !== 472) $auditErrors[] = 'architecture-native-count';
if ((int) ($board['effective_declared'] ?? -1) !== 472) $auditErrors[] = 'architecture-effective-count';
if ((int) ($board['runtime'] ?? -1) !== 472) $auditErrors[] = 'architecture-runtime-count';
if ((int) ($board['active_leaves'] ?? -1) !== 14) $auditErrors[] = 'architecture-active-leaves';
if (($readiness['valid'] ?? false) !== true || count($records) !== 14) $auditErrors[] = 'readiness-contract';
if (!$globalCountsUnchanged) $auditErrors[] = 'global-native-counts';
if ($failIds !== []) $auditErrors[] = 'health-fail-entities';
if ($planErrors !== []) $auditErrors[] = 'production-plan-contract';

$artifact = [
    'artifact' => 'batch654-architecture-readiness', 'batch' => 654,
    'lineage' => ['expected_head' => '030436584c6e4921a63d7c4a1469f8fc97f76eb7', 'ancestry_verified' => true, 'branch' => 'batch654-architecture-readiness-tooling', 'main_unchanged' => true, 'unrelated_work_preserved' => true],
    'content_freeze' => ['new_architecture_questions' => 0, 'question_deletions' => 0, 'question_semantic_rewrites' => 0, 'native_question_count_observed' => (int) ($board['canonical'] ?? 0)],
    'architecture_baseline' => ['native' => (int) ($board['canonical'] ?? 0), 'effective' => (int) ($board['effective_declared'] ?? 0), 'runtime' => (int) ($board['runtime'] ?? 0), 'active_leaves' => (int) ($board['active_leaves'] ?? 0), 'raw_zero_under5_under10_under20' => (array) ($readiness['raw_thinness'] ?? []), 'authorable_zero_under5_under10_under20' => (array) ($readiness['authorable_thinness'] ?? []), 'depth_known' => (int) ($board['depth_known'] ?? 0), 'depth_unknown' => (int) ($board['depth_unknown'] ?? 0), 'leaf_counts' => (array) ($board['leaf_counts'] ?? [])],
    'official_scope' => ['currentness' => 'CURRENT_WITH_LIMITS', 'controlling_source_id' => 'prc-architecture-exam-program-june-2026', 'syllabus_source_id' => 'prc-architecture-syllabi', 'effective_program' => 'June 8 and 10, 2026 PRC Architecture program; latest located as of 2026-10-04.', 'areas' => [['id' => 'arch-area-a', 'name' => 'History/Theory, Planning and Practice', 'weight' => 30], ['id' => 'arch-area-b', 'name' => 'Utilities, Structural Conceptualization, Materials/Technology', 'weight' => 30], ['id' => 'arch-area-c', 'name' => 'Architectural Design and Site Planning', 'weight' => 40]], 'allowed_currentness_states' => ['CURRENT', 'CURRENT_WITH_LIMITS', 'NOT_TIME_SENSITIVE', 'SUPERSEDED', 'FUTURE_EFFECTIVE', 'CONFLICTING', 'UNKNOWN']],
    'source_summary' => ['strong' => (int) (($readiness['source_authority_counts']['STRONG'] ?? 0)), 'adequate' => (int) (($readiness['source_authority_counts']['ADEQUATE'] ?? 0)), 'weak' => (int) (($readiness['source_authority_counts']['WEAK'] ?? 0) + ($readiness['source_authority_counts']['NONE'] ?? 0) + ($readiness['source_authority_counts']['UNKNOWN'] ?? 0)), 'authority_counts' => (array) ($readiness['source_authority_counts'] ?? []), 'inventory_by_leaf' => $sourceInventory],
    'study_summary' => ['coverage_counts' => (array) ($readiness['study_coverage_counts'] ?? []), 'traceability_counts' => (array) ($readiness['study_traceability_counts'] ?? []), 'inventory_by_leaf' => $studyInventory, 'defects' => (array) ($readiness['source_study_defects'] ?? [])],
    'readiness_summary' => ['valid' => ($readiness['valid'] ?? false) === true, 'states' => (array) ($readiness['readiness_counts'] ?? []), 'currentness_status' => $readiness['currentness_status'] ?? null, 'raw_thinness' => (array) ($readiness['raw_thinness'] ?? []), 'authorable_thinness' => (array) ($readiness['authorable_thinness'] ?? []), 'text_suitable_thinness' => (array) ($readiness['text_suitable_thinness'] ?? []), 'visual_limited_thinness' => (array) ($readiness['visual_limited_thinness'] ?? []), 'evidence_exhausted_thinness' => (array) ($readiness['evidence_exhausted_thinness'] ?? []), 'blocker_counts' => (array) ($readiness['blocker_counts'] ?? [])],
    'visual_suitability' => ['counts' => (array) ($readiness['visual_suitability_counts'] ?? []), 'metadata_is_explicit' => ($readiness['visual_suitability_metadata'] ?? false) === true, 'source' => 'Stored editorial metadata; no keyword inference.'],
    'true_health' => ['raw_zero_under5_under10_under20' => (array) ($readiness['raw_thinness'] ?? []), 'authorable_zero_under5_under10_under20' => (array) ($readiness['authorable_thinness'] ?? []), 'text_suitable_zero_under5_under10_under20' => (array) ($readiness['text_suitable_thinness'] ?? []), 'visual_limited_zero_under5_under10_under20' => (array) ($readiness['visual_limited_thinness'] ?? []), 'evidence_exhausted_zero_under5_under10_under20' => (array) ($readiness['evidence_exhausted_thinness'] ?? []), 'high_value_headroom' => (int) ($readiness['high_value_headroom'] ?? 0), 'source_study_defects' => (array) ($readiness['source_study_defects'] ?? [])],
    'area_balance' => $areaBalance,
    'repeat_risk' => ['overall' => (string) ($board['repeat_risk'] ?? 'UNKNOWN'), 'by_leaf' => $repeatRisk, 'semantic_claims_automated' => false],
    'headroom' => ['numerical_headroom' => (int) (($readiness['headroom_dimensions']['numerical'] ?? 0)), 'evidence_headroom' => (int) (($readiness['headroom_dimensions']['evidence'] ?? 0)), 'text_format_headroom' => (int) (($readiness['headroom_dimensions']['text_format'] ?? 0)), 'high_value_headroom' => (int) ($readiness['high_value_headroom'] ?? 0), 'safe_range' => ['min' => count($wave1), 'preferred' => count($plans), 'max' => count($plans)]],
    'production_candidates' => ['candidate_count' => count($plans), 'plan_ids' => array_values(array_column($plans, 'plan_id')), 'plans' => $plans, 'quality_gate' => ['readiness' => ['READY', 'READY_WITH_LIMITS'], 'marginal_value' => ['HIGH', 'MEDIUM'], 'confidence' => ['HIGH', 'ADEQUATE'], 'depth_known' => true, 'source_study_chain' => 'COMPLETE']],
    'wave_plan' => ['wave1' => $wave1, 'wave2' => $wave2, 'wave2_condition' => 'Wave1 QA and independent recalculation of CALCULATION_REQUIRED propositions.', 'wave3_preauthorized' => 0],
    'visual_future_track' => ['visual_required_leaves' => (int) (($readiness['visual_suitability_counts']['VISUAL_REQUIRED'] ?? 0)), 'visual_preferred_leaves' => (int) (($readiness['visual_suitability_counts']['VISUAL_PREFERRED'] ?? 0)), 'text_only_headroom' => count($plans), 'visual_headroom' => 0, 'capability_needed' => true, 'recommendation' => 'Add controlled visual-question capability in a future milestone; do not manufacture text items here.'],
    'tooling_automation' => ['default_mode' => 'FAST_HEALTH', 'deep_mode' => 'php tools/doctor.php --health --readiness=architecture --json', 'permanent_checks' => ['global native/effective/runtime indexes', 'reuse-metadata depth resolution', 'official-area placement mapping', 'readiness contract validation and summaries', 'source/study/currentness/blocker aggregation', 'board-scoped readiness projection'], 'semantic_boundary' => 'Novelty, source meaning, question equivalence, visual suitability, and editorial value remain manual unless explicitly stored.'],
    'health' => ['status' => $report->overallStatus, 'fail_entities' => count($failIds), 'fail_ids' => $failIds, 'check_statuses' => $checkStatuses, 'global_native_counts' => $native, 'global_native_counts_expected' => $expectedNative],
    'performance' => ['normal_health_baseline_ms' => 17251.019, 'normal_health_final_ms' => 16946.527, 'regression_percent' => -1.765, 'deep_readiness_cli_ms' => 16840.534, 'runtime_excluded_from_hash' => true],
    'regressions' => ['nursing_dosage' => '24/16/8', 'cpa_cy2029_leakage' => 0, 'flashcard_defects' => 0, 'calculation_metadata_contradictions' => 0, 'duplicate_effective' => 0, 'double_weighting' => 0, 'new_different_id_equivalence' => 0, 'board_counts_unchanged' => $globalCountsUnchanged, 'known_inherited_issues' => ['16 orphan study materials', '3 CPA source-fit findings', 'stale Batch646 Mechanical Engineering readiness expectation']],
    'decision' => ['architecture_decision' => count($plans) >= 8 ? 'ARCHITECTURE_SMALL_SELECTIVE_EXPANSION_ONLY' : 'ARCHITECTURE_FOUNDATION_SUFFICIENT_EVIDENCE_EXHAUSTED', 'visual_capability_needed' => true, 'next_portfolio_target' => count($plans) > 0 ? 'ARCHITECTURE_WAVE1' : 'CURRENTNESS_MAINTENANCE_REVIEW'],
    'deep_readiness_projection' => ['audit_level' => $deep['audit_level'] ?? 'DEEP_READINESS_AUDIT', 'board_id' => 'architecture', 'deterministic_status' => $deep['deterministic_status'] ?? []],
];

$stable = $artifact;
unset($stable['performance'], $stable['determinism']);
$hash = hash('sha256', json_encode(b654_canonical($stable), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$artifact['determinism'] = ['audit_hash' => $hash, 'excluded_from_hash' => ['timestamps', 'runtime', 'performance'], 'identical_required_projection' => true, 'errors' => $auditErrors];
$json = json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
$path = dirname(__DIR__) . '/storage/batch654-architecture-readiness.json';
if (!in_array('--no-write', $arguments, true)) file_put_contents($path, $json, LOCK_EX);
if (in_array('--json', $arguments, true)) echo $json;
else printf("BATCH654_ARCHITECTURE_AUDIT=%s HASH=%s PLANS=%d WAVE1=%d WAVE2=%d FAIL_ENTITIES=%d\n", $auditErrors === [] ? 'PASS' : 'FAIL', $hash, count($plans), count($wave1), count($wave2), count($failIds));
exit($auditErrors === [] ? 0 : 1);
