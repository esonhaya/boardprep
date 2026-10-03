<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$artifact = json_decode((string) file_get_contents($root . '/storage/batch651-let-family-proof.json'), true, 512, JSON_THROW_ON_ERROR);
$readiness = json_decode((string) file_get_contents($root . '/storage/source-readiness-let.json'), true, 512, JSON_THROW_ON_ERROR);
$questions = json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$eligibility = json_decode((string) file_get_contents($root . '/storage/question-eligibility.json'), true, 512, JSON_THROW_ON_ERROR);
$sources = json_decode((string) file_get_contents($root . '/storage/sources.json'), true, 512, JSON_THROW_ON_ERROR);
$materials = json_decode((string) file_get_contents($root . '/storage/study-materials.json'), true, 512, JSON_THROW_ON_ERROR);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$normalize = static function (mixed $node) use (&$normalize): mixed {
    if (!is_array($node)) return $node;
    if (array_is_list($node)) return array_map($normalize, $node);
    ksort($node, SORT_STRING);
    foreach ($node as &$child) $child = $normalize($child);
    unset($child);
    return $node;
};
$hash = static fn(mixed $value): string => hash('sha256', json_encode($normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$assert(($artifact['lineage']['expected_head'] ?? '') === 'f8703dcfb80d13dcd2bc10b0211504817408ca27', 'Batch651 parent is not the authoritative Batch650 checkpoint.');
$assert(($artifact['lineage']['branch'] ?? '') === 'batch651-let-family-proof' && ($artifact['lineage']['ancestry_pass'] ?? false), 'Batch651 lineage/branch contract failed.');
$assert(($artifact['currentness']['classification'] ?? '') === 'UNCHANGED' && !($artifact['currentness']['new_controlling_source_found'] ?? true), 'Current official LET regime is unresolved.');
$assert(($artifact['baseline']['total_canonical'] ?? 0) === 8943 && ($artifact['baseline']['let_native'] ?? 0) === 1784, 'Global/LET canonical baseline changed.');
$assert(($artifact['baseline']['let_effective_declared'] ?? 0) === 1867 && ($artifact['baseline']['let_runtime'] ?? 0) === 1882, 'Effective/runtime LET baseline changed.');

$readinessByLeaf = [];
foreach ($readiness as $record) if (is_array($record)) $readinessByLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
$liveCandidateIds = [];
foreach ($readinessByLeaf as $leafId => $record) {
    if (($record['board_id'] ?? '') !== 'let') continue;
    if (in_array(($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)
        && (int) ($record['current_question_count'] ?? 10) < 10
        && (int) ($record['safe_headroom_max'] ?? 0) > 0) $liveCandidateIds[] = $leafId;
}
sort($liveCandidateIds, SORT_STRING);
$artifactCandidateIds = array_values(array_map('strval', (array) ($artifact['cohort']['candidate_leaf_ids'] ?? [])));
sort($artifactCandidateIds, SORT_STRING);
$assert(count($liveCandidateIds) === 88 && $artifactCandidateIds === $liveCandidateIds, 'Artifact candidate set differs from the live readiness query.');
$records = (array) ($artifact['candidate_records'] ?? []);
$assert(count($records) === 88, 'Exactly 88 candidates must receive a disposition.');
$recordIds = array_values(array_map(static fn(array $r): string => (string) ($r['leaf_id'] ?? ''), $records));
sort($recordIds, SORT_STRING);
$assert($recordIds === $liveCandidateIds && count(array_unique($recordIds)) === 88, 'Candidate record IDs are missing or duplicated.');
$dispositions = array_count_values(array_map(static fn(array $r): string => (string) ($r['primary_disposition'] ?? ''), $records));
$assert(($dispositions['PRODUCTION_READY_WITH_LIMITS'] ?? 0) === 4 && ($dispositions['EVIDENCE_EXHAUSTED'] ?? 0) === 83 && ($dispositions['REVIEW'] ?? 0) === 1, 'Candidate disposition counts differ from the evidence review.');
$assert(($artifact['cohort']['profed_candidates'] ?? -1) === 0 && ($artifact['cohort']['shared_review_contamination'] ?? null) === [], 'Blocked ProfEd/shared-REVIEW records entered the cohort.');
$assert(count((array) ($artifact['cohort']['by_scope_specialization'] ?? [])) === 15 && count((array) ($artifact['cohort']['by_native_specialization'] ?? [])) === 15, 'Candidate partition omitted an official specialization ID.');
$assert(($artifact['cohort']['by_scope_specialization']['secondary-english'] ?? -1) === 0, 'The zero-candidate specialization is not represented explicitly.');

$candidateByLeaf = [];
foreach ($records as $record) {
    $leafId = (string) ($record['leaf_id'] ?? '');
    $candidateByLeaf[$leafId] = $record;
    $assert(($record['readiness'] ?? '') === 'READY_WITH_LIMITS', 'A REVIEW/DEFER readiness record entered the live candidate set: ' . $leafId);
    $assert((int) ($record['native_question_count'] ?? 0) === 5 && (int) ($record['shared_in_declared'] ?? -1) >= 0, 'Native/shared counts are missing or conflated: ' . $leafId);
    $assert(count((array) ($record['existing_proposition_signatures'] ?? [])) === (int) ($record['native_question_count'] ?? 0), 'Not every native question has a proposition signature: ' . $leafId);
    $signatureIds = array_column((array) $record['existing_proposition_signatures'], 'signature_sha256');
    $groupCounts = array_count_values($signatureIds);
    $collisionCount = count(array_filter($groupCounts, static fn(int $count): bool => $count > 1));
    $assert($collisionCount === count((array) ($record['existing_signature_collision_groups'] ?? [])), 'Existing signature collisions are not surfaced: ' . $leafId);
}

$plans = (array) ($artifact['plans'] ?? []);
$assert(count($plans) === 4 && ($artifact['batch652']['authorized_items'] ?? 0) === 4, 'Batch652 authorized plan must contain the four surviving propositions.');
$expectedPlanLeaves = ['cae-art-elements-and-principles', 'environmental-stewardship-protection', 'pe-health-and-wellness', 'social-history-context-and-causation'];
$planLeaves = array_values(array_map(static fn(array $p): string => (string) ($p['leaf_id'] ?? ''), $plans));
sort($planLeaves, SORT_STRING);
$assert($planLeaves === $expectedPlanLeaves, 'Batch652 plan leaf set differs from the item-level novelty review.');
$sourceById = [];
foreach ($sources as $source) if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
$materialById = [];
foreach ($materials as $material) if (is_array($material)) $materialById[(string) ($material['id'] ?? '')] = $material;
foreach ($plans as $plan) {
    $leafId = (string) $plan['leaf_id'];
    $candidate = $candidateByLeaf[$leafId] ?? [];
    $readinessRecord = $readinessByLeaf[$leafId] ?? [];
    $assert(($plan['disposition'] ?? '') === 'PRODUCTION_READY_WITH_LIMITS', 'Plan is not bounded RWL.');
    $assert(in_array($leafId, (array) ($candidate['allowed_scope'] ?? []), true), 'Plan left explicit allowed scope: ' . $leafId);
    $assert((int) ($candidate['safe_headroom']['max'] ?? 0) > 0, 'Plan lacks remaining headroom: ' . $leafId);
    $assert((int) ($plan['depth'] ?? 0) >= (int) ($readinessRecord['supported_depth_min'] ?? 9) && (int) ($plan['depth'] ?? 9) <= (int) ($readinessRecord['supported_depth_max'] ?? 0), 'Plan depth exceeds evidence: ' . $leafId);
    $assert(in_array((string) ($plan['readiness_family'] ?? ''), (array) ($readinessRecord['distinct_content_families'] ?? []), true), 'Plan family is outside permanent readiness support: ' . $leafId);
    $assert(($plan['confidence'] ?? '') !== 'LOW' && ($plan['marginal_value'] ?? '') !== 'LOW', 'Low-confidence/low-value item entered the plan.');
    $assert(($plan['calculation_class'] ?? '') === 'NONCALCULATION' && ($plan['wave'] ?? 0) === 1, 'Unexpected calculation class or wave assignment.');
    $assert(count((array) ($plan['source_ids'] ?? [])) > 0 && count((array) ($plan['study_material_ids'] ?? [])) > 0, 'Plan lacks source/study traceability.');
    foreach ((array) $plan['source_ids'] as $id) {
        $assert(isset($sourceById[$id]) && in_array($id, (array) ($candidate['source_ids'] ?? []), true), 'Plan has invalid/unauthorized content source: ' . $id);
        foreach ((array) $plan['study_material_ids'] as $materialId) $assert(in_array($id, (array) ($materialById[$materialId]['source_ids'] ?? []), true), 'Study material does not trace plan source: ' . $materialId . '/' . $id);
    }
    foreach ((array) ($plan['scope_source_ids'] ?? []) as $id) $assert(($sourceById[$id]['source_role'] ?? '') === 'SCOPE_SOURCE', 'Subject source substituted for official LET scope.');
    $assert(($plan['novelty_review']['same_leaf_exact_or_cosmetic_variant_found'] ?? true) === false, 'Plan lacks explicit novelty review.');
    $assert((int) ($plan['max_items_from_this_proposition'] ?? 0) === 1 && (int) ($plan['novelty_review']['existing_question_count_compared'] ?? 0) === 5, 'Concentration/duplicate safeguards are missing.');
    $assert(($plan['currentness_class'] ?? '') === 'NOT_TIME_SENSITIVE' && ($plan['time_sensitive'] ?? true) === false, 'Plan lacks a valid currentness classification.');
}
$assert(($artifact['batch652']['wave1']['items'] ?? 0) === 4 && ($artifact['batch652']['wave2']['items'] ?? -1) === 0 && ($artifact['batch652']['wave3_preauthorized'] ?? -1) === 0, 'Wave structure or zero Wave3 preauthorization failed.');
$assert(($artifact['batch652']['projected']['let_native'] ?? 0) === 1788 && ($artifact['batch652']['projected']['authorable_under10'] ?? 0) === 88, 'Projected Batch652 health is inconsistent.');
$assert(count((array) ($artifact['batch652']['projected']['specializations'] ?? [])) === 15, 'Projected specialization summary does not cover all 15 pools.');
$assert(($artifact['batch652']['decision'] ?? '') === 'LET_SMALL_SELECTIVE_PRODUCTION_ONLY', 'LET stopping decision is not evidence-based.');

$canonicalQuestions = array_values(array_filter($questions, static fn(array $q): bool => strtolower((string) ($q['status'] ?? 'active')) !== 'archived'));
$assert(count($canonicalQuestions) === 8943, 'Canonical question count changed.');
$questionHash = $hash($questions);
$eligibilityHash = $hash($eligibility);
$assert($questionHash === ($artifact['content_freeze']['question_store_semantic_hash'] ?? ''), 'Question semantics changed after family review.');
$assert($eligibilityHash === ($artifact['content_freeze']['eligibility_semantic_hash'] ?? ''), 'Eligibility mappings changed after family review.');
$assert(($artifact['content_freeze']['question_store_identical'] ?? false) && ($artifact['content_freeze']['eligibility_identical'] ?? false), 'Question/eligibility content freeze failed.');
$assert(($artifact['content_freeze']['new_questions'] ?? -1) === 0 && ($artifact['content_freeze']['question_mapping_changes'] ?? -1) === 0, 'Batch651 changed question or mapping data.');

$assert(($artifact['repository_health_after']['fail_entities'] ?? -1) === 0 && ($artifact['repository_health_after']['fail_checks'] ?? -1) === 0, 'Repository Health final gate has FAILs.');
$assert(($artifact['regressions']['nursing_dosage'] ?? null) === ['total' => 24, 'calculation_required' => 16, 'conceptual' => 8], 'Nursing dosage regression invariant failed.');
$assert(($artifact['regressions']['cpa_cy2029_leakage'] ?? -1) === 0 && ($artifact['regressions']['flashcard_defects'] ?? -1) === 0, 'CPA currentness or flashcard regression failed.');
$assert(($artifact['regressions']['calculation_metadata_contradictions'] ?? -1) === 0 && ($artifact['regressions']['duplicate_effective'] ?? -1) === 0 && ($artifact['regressions']['double_weighting'] ?? -1) === 0 && ($artifact['regressions']['new_different_id_equivalence'] ?? -1) === 0, 'Eligibility/calculation regression invariant failed.');
$assert(($artifact['baseline']['native_by_board']['civil-service'] ?? 0) === 1550 && ($artifact['baseline']['native_by_board']['nursing'] ?? 0) === 878, 'Unrelated board counts changed.');
$assert(($artifact['specialization_count'] ?? 0) === 15, 'The official specialization inventory was not retained.');
$assert(($artifact['portfolio_next_major_candidate'] ?? '') === 'civil-service', 'Batch648 portfolio priority is not retained for a small LET cohort.');
$assert(($artifact['determinism']['identical'] ?? false) && ($artifact['determinism']['runtime_and_timestamps_excluded'] ?? false), 'Determinism record is incomplete.');

$question3 = null;
foreach ($questions as $question) if ((string) ($question['id'] ?? '') === '3') $question3 = $question;
$assert(($question3['taxonomy']['concept_id'] ?? '') === 'pronouns-pronoun-antecedent-agreement' && str_contains(strtolower((string) ($question3['question'] ?? '')), 'reference books'), 'The documented semantic placement finding changed unexpectedly.');
$assert(count(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch651')) === 0, 'Batch651 unexpectedly authored questions.');

printf("BATCH651_TEST=PASS CANDIDATES=%d PLANS=%d QUESTION_FREEZE=PASS ELIGIBILITY_FREEZE=PASS\n", count($records), count($plans));
