<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\RepositoryHealth\CivilService\CivilServiceCoverageModel;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;

$root = dirname(__DIR__);
$fail = static function (string $message): never { fwrite(STDERR, 'BATCH652_TEST_FAIL=' . $message . PHP_EOL); exit(1); };
$read = static function (string $file) use ($root): array {
    $value = json_decode((string)file_get_contents($root . '/storage/' . $file), true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};
$artifact = $read('batch652-let-close-cse-readiness.json');
$readiness = $read('source-readiness-civil-service.json');
$questions = $read('questions.json');
$coverage = $read('board-concept-coverage.json');
$sources = $read('sources.json');
$materials = $read('study-materials.json');
$links = $read('study-question-links.json');
$expectedPlanIds = ['b652-2c2b1e0630f0ab7e','b652-540ef67b4683e497','b652-92843504440ecfba','b652-c4688d5efb188489'];
$accepted = (array)($artifact['let_closure']['plans'] ?? []);
if (count($accepted) !== 4 || array_values(array_map(static fn(array $r):string => (string)$r['plan_id'],$accepted)) !== $expectedPlanIds) $fail('Exact Batch651 plan set was not honored.');
if ((int)($artifact['let_closure']['accepted'] ?? 0) !== 4 || (int)($artifact['let_closure']['rejected'] ?? -1) !== 0) $fail('LET acceptance totals incorrect.');
if ((int)($artifact['let_closure']['native_before'] ?? 0) !== 1784 || (int)($artifact['let_closure']['native_after'] ?? 0) !== 1788) $fail('LET count delta incorrect.');
if (($artifact['let_closure']['closure_state'] ?? '') !== 'FOUNDATION_SUFFICIENT_EVIDENCE_EXHAUSTED' || (int)($artifact['let_closure']['remaining_high_value_headroom'] ?? -1) !== 0) $fail('LET evidence headroom was not consumed.');
if (count($readiness) !== 56 || (int)($artifact['cse']['core_exam_leaf_count'] ?? 0) !== 56 || (int)($artifact['cse']['readiness_record_count'] ?? 0) !== 56) $fail('CSE core readiness record count incorrect.');
foreach ($readiness as $record) {
    if (!is_array($record) || ($record['board_id'] ?? '') !== 'civil-service') $fail('Invalid CSE readiness record.');
    if (!hash_equals(SourceReadinessRecord::evidenceHash($record),(string)($record['evidence_hash'] ?? ''))) $fail('Invalid readiness evidence hash for ' . (string)($record['leaf_id'] ?? ''));
    if (!in_array((string)($record['readiness'] ?? ''),SourceReadinessRecord::READINESS,true)) $fail('Unsupported readiness state.');
    if (($record['readiness'] ?? '') === 'REVIEW' && ((int)$record['max_new_questions'] !== 0 || (int)$record['planned_new_questions'] !== 0)) $fail('Review readiness has authorization.');
    if (array_diff((array)$record['source_ids'],array_column($sources,'id')) !== []) $fail('Invalid readiness source reference.');
    if (array_diff((array)$record['study_material_ids'],array_column($materials,'id')) !== []) $fail('Invalid readiness study reference.');
}
$classCounts = (array)($artifact['cse']['row_class_counts'] ?? []);
if (($classCounts['CORE_EXAM_LEAF'] ?? 0) !== 56 || ($classCounts['VALID_SHARED_COMPETENCY'] ?? 0) !== 54 || ($classCounts['OUT_OF_SCOPE'] ?? 0) !== 10 || array_sum($classCounts) !== 120) $fail('CSE structural classification totals incorrect.');
$recomputedClasses = [];
foreach ($coverage as $row) if (CivilServiceCoverageModel::normalizeBoard($row['board_id'] ?? '') === 'civil-service') $recomputedClasses[CivilServiceCoverageModel::classifyRow($row)] = ($recomputedClasses[CivilServiceCoverageModel::classifyRow($row)] ?? 0) + 1;
foreach ($recomputedClasses as $class=>$count) if (($classCounts[$class] ?? 0) !== $count) $fail('CSE classification drift for ' . $class);
if (($artifact['cse']['native'] ?? null) !== 1550 || ($artifact['cse']['effective_declared'] ?? null) !== 1804 || ($artifact['cse']['runtime'] ?? null) !== 2113) $fail('CSE global counts changed.');
if (($artifact['cse']['raw_health']['zero'] ?? null) !== 67 || ($artifact['cse']['raw_health']['under5'] ?? null) !== 67 || ($artifact['cse']['raw_health']['under10'] ?? null) !== 67 || ($artifact['cse']['raw_health']['under20'] ?? null) !== 80) $fail('CSE raw coverage health changed.');
if (($artifact['cse']['core_native_health']['zero'] ?? null) !== 3 || ($artifact['cse']['core_native_health']['under10'] ?? null) !== 3 || ($artifact['cse']['core_native_health']['under20'] ?? null) !== 16) $fail('CSE core health incorrect.');
if (($artifact['cse']['readiness_counts']['READY_WITH_LIMITS'] ?? null) !== 5 || ($artifact['cse']['readiness_counts']['REVIEW'] ?? null) !== 51) $fail('CSE readiness states incorrect.');
if (count((array)($artifact['cse']['wave1'] ?? [])) !== 4 || count((array)($artifact['cse']['wave2'] ?? [])) !== 1 || (int)($artifact['cse']['wave3_preauthorized'] ?? -1) !== 0) $fail('CSE deterministic production waves incorrect.');
if (($artifact['cse']['safe_range']['min'] ?? 0) !== 4 || ($artifact['cse']['safe_range']['preferred'] ?? 0) !== 5 || ($artifact['cse']['safe_range']['max'] ?? 0) !== 5) $fail('CSE safe range incorrect.');
if (count((array)($artifact['cse']['scope_blockers'] ?? [])) !== 1 || ($artifact['cse']['scope_blockers'][0]['classification'] ?? '') !== 'REVIEW') $fail('Official area mismatch was not quarantined.');
$clericalPlan = null;
foreach ((array)($artifact['cse']['production_candidates'] ?? []) as $plan) if (($plan['leaf_id'] ?? '') === 'cse-document-control') $clericalPlan = $plan;
if (!is_array($clericalPlan) || !in_array('SUBPROFESSIONAL_CSE_PPT_ONLY',(array)($clericalPlan['allowed_scope'] ?? []),true)) $fail('Clerical candidate is not bounded to Subprofessional CSE-PPT scope.');
if ((int)($artifact['content_freeze']['cse_new_questions'] ?? -1) !== 0 || ($artifact['content_freeze']['cse_eligibility_unchanged'] ?? false) !== true || ($artifact['content_freeze']['question_identical_outside_four_let'] ?? false) !== true || ($artifact['content_freeze']['eligibility_identical_outside_four_let'] ?? false) !== true) $fail('Content freeze failed.');
$newLetIds = array_fill_keys(array_map('strval',(array)($artifact['let_closure']['accepted_ids'] ?? [])),true);
$cseNew = array_filter($questions,static fn(array $q):bool => (string)($q['taxonomy']['board_id'] ?? '') === 'civil-service' && isset($newLetIds[(string)($q['id'] ?? '')]));
if ($cseNew !== []) $fail('Batch652 unexpectedly authored a CSE question.');
foreach ($accepted as $item) {
    $question = null;
    foreach ($questions as $q) if ((string)($q['id'] ?? '') === (string)$item['question_id']) $question = $q;
    if (!is_array($question) || ($question['batch_marker'] ?? '') !== 'batch652-let') $fail('Missing authorized LET question ' . (string)$item['question_id']);
    $hasTrace = false;
    foreach ($links as $link) if ((string)($link['question_id'] ?? '') === (string)$item['question_id'] && (string)($link['material_id'] ?? '') === (string)$question['study_material_id'] && array_diff((array)$question['source_ids'],(array)($link['source_ids'] ?? [])) === []) $hasTrace = true;
    if (!$hasTrace) $fail('LET question study/source chain broken for ' . (string)$item['question_id']);
}
if (($artifact['health_after']['fail_checks'] ?? null) !== 0) $fail('Repository Health final FAIL count is not recorded as zero.');
if (!in_array(($artifact['health_after']['overall_status'] ?? ''), ['PASS','REVIEW'], true)) $fail('Repository Health final status was not recorded.');
$native = (array)($artifact['global_regressions']['native_by_board'] ?? []);
foreach (['nursing'=>878,'criminologist'=>451,'civil-engineering'=>922,'mechanical-engineering'=>511,'registered-electrical-engineering'=>842,'architecture'=>472,'psychometrician'=>401,'cpa'=>1132] as $board=>$count) if (($native[$board] ?? null) !== $count) $fail('Unrelated board native count changed for ' . $board);
fwrite(STDOUT,'BATCH652_TEST=PASS' . PHP_EOL);
