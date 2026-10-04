<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static function (string $path): array {
    $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Expected JSON object/list: ' . $path);
    return $value;
};
$fail = static function (string $message): never {
    fwrite(STDERR, 'batch655_architecture_production=FAIL ' . $message . PHP_EOL);
    exit(1);
};
$assert = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) $fail($message);
};

try {
    $artifact = $read($root . '/storage/batch655-architecture-production-triage.json');
    $assert(($artifact['batch'] ?? null) === 655, 'wrong batch artifact');
    $assert(($artifact['lineage']['expected_head'] ?? '') === 'eccec1486a06d0d66182bb1ad3b33d795d5ddfd8', 'wrong lineage');

    $production = (array) ($artifact['production'] ?? []);
    $expectedPlans = array_map(static fn(int $n): string => sprintf('ARCH-654-%03d', $n), range(1, 12));
    $assert(($production['plans_loaded'] ?? []) === $expectedPlans, 'exact Batch654 plan set was not consumed');
    $assert(($production['wave1']['planned'] ?? 0) === 8 && ($production['wave1']['accepted'] ?? 0) === 8, 'Wave1 authorization/result mismatch');
    $assert(($production['wave2']['planned'] ?? 0) === 4 && ($production['wave2']['accepted'] ?? 0) === 4, 'Wave2 gate/result mismatch');
    $assert(($production['wave3_preauthorized'] ?? -1) === 0, 'Wave3 is not zero');
    $ids = array_values(array_map('intval', (array) ($production['accepted_ids'] ?? [])));
    $assert(count($ids) === 12 && count(array_unique($ids)) === 12, 'accepted Architecture IDs are not unique');
    $assert($ids === range(min($ids), max($ids)), 'accepted Architecture IDs are not contiguous');
    foreach (['source_traceability', 'study_traceability', 'flashcard_derivability', 'structured_content', 'currentness', 'depth', 'area_placement', 'format_suitability', 'calculation_recomputed'] as $field) {
        $assert(($production[$field] ?? '') === 'PASS', $field . ' did not pass');
    }
    $assert(($production['duplicates'] ?? -1) === 0 && ($production['total_new'] ?? -1) === 12, 'production duplicate/count guard failed');

    $final = (array) ($artifact['architecture_final'] ?? []);
    $assert(($final['native'] ?? -1) === 484 && ($final['effective'] ?? -1) === 484 && ($final['runtime'] ?? -1) === 484, 'Architecture final counts are wrong');
    $assert(($final['active_leaves'] ?? -1) === 14, 'Architecture active leaf count changed');
    $assert(($final['authorable_zero_under5_under10_under20'] ?? []) === ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0], 'authorable thinness is not closed');
    $assert(($final['headroom']['high_value_text'] ?? -1) === 0, 'high-value text headroom remains unexpectedly');
    $assert(($final['closure_state'] ?? '') === 'ARCHITECTURE_TEXT_EVIDENCE_EXHAUSTED', 'Architecture closure state is wrong');
    $assert(count((array) ($artifact['visual_backlog']['rows'] ?? [])) === 4, 'visual backlog does not preserve all preferred/required leaves');
    $assert(($artifact['visual_backlog']['capability_decision'] ?? '') === 'VISUAL_CAPABILITY_USEFUL_LATER', 'visual capability decision missing');

    $triage = (array) ($artifact['portfolio_triage'] ?? []);
    $rows = (array) ($triage['rows'] ?? []);
    $assert(($triage['boards_triaged'] ?? 0) === 10 && count($rows) === 10, 'portfolio triage is not ten boards');
    $boardIds = array_values(array_map(static fn(array $row): string => (string) ($row['board_id'] ?? ''), $rows));
    foreach (['let', 'civil-service', 'nursing', 'criminologist', 'civil-engineering', 'mechanical-engineering', 'registered-electrical-engineering', 'architecture', 'psychometrician', 'cpa'] as $boardId) $assert(in_array($boardId, $boardIds, true), 'portfolio board missing: ' . $boardId);
    $nursing = $rows[array_search('nursing', $boardIds, true)];
    $assert(($nursing['ready'] ?? null) === 'UNKNOWN' && ($nursing['raw_zero_under5_under10_under20'] ?? null) === 'UNKNOWN', 'unknown legacy Nursing fields were invented');
    $assert(count(array_filter($rows, static fn(array $row): bool => ($row['operational_state'] ?? '') === 'CURRENTNESS_REVIEW_NEEDED')) === 1, 'currentness triage is not bounded');
    $assert(count(array_filter($rows, static fn(array $row): bool => ($row['operational_state'] ?? '') === 'VISUAL_LIMITED')) === 1, 'visual triage is not bounded');
    $assert(count(array_filter($rows, static fn(array $row): bool => ($row['operational_state'] ?? '') === 'SOURCE_READINESS_NEEDED')) === 4, 'source-readiness triage count changed');
    $assert(count(array_filter($rows, static fn(array $row): bool => ($row['operational_state'] ?? '') === 'UNKNOWN_REQUIRES_MANUAL_REVIEW')) === 1, 'unknown/manual triage boundary changed');
    $assert(($triage['next_primary_milestone'] ?? '') === 'LET_SOURCE_READINESS_DEFECT_CLOSURE', 'multiple/incorrect next target');

    $regressions = (array) ($artifact['global_regressions'] ?? []);
    $assert(($regressions['other_board_counts_unchanged'] ?? false) === true, 'other board counts changed');
    $assert(($regressions['nursing_dosage'] ?? '') === '24/16/8', 'Nursing dosage invariant changed');
    foreach (['cpa_cy2029_leakage', 'flashcard_defects', 'calculation_metadata_contradictions', 'duplicate_effective', 'double_weighting', 'new_different_id_equivalence'] as $field) $assert(($regressions[$field] ?? -1) === 0, $field . ' invariant failed');
    $assert(($artifact['health']['fail_entities'] ?? -1) === 0, 'health has FAIL entities');
    $assert(($artifact['determinism']['identical'] ?? false) === true && ($artifact['determinism']['second_audit_hash'] ?? '') === ($artifact['audit_hash'] ?? ''), 'two-run determinism marker/hash failed');

    $questions = $read($root . '/storage/questions.json');
    $batchQuestions = array_values(array_filter($questions, static fn(mixed $question): bool => is_array($question) && ($question['batch_marker'] ?? '') === 'batch655-architecture'));
    $assert(count($batchQuestions) === 12, 'stored Batch655 question count is not twelve');
    foreach ($batchQuestions as $question) $assert(($question['board'] ?? '') === 'architecture' && ($question['batch655']['visual_suitability'] ?? '') !== 'VISUAL_REQUIRED', 'stored question violates Architecture/visual safety');

    fwrite(STDOUT, 'batch655_architecture_production=PASS' . PHP_EOL);
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
