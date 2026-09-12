<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$questions = json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$new = array_values(array_filter($questions, static fn(array $q): bool => (int)($q['id'] ?? 0) >= 2920 && (int)($q['id'] ?? 0) <= 3099));
$assert(count($new) === 180, 'Batch 616 must contain exactly 180 new questions');
$ids = [];
foreach ($new as $question) {
    $id = (int)$question['id'];
    $assert(!isset($ids[$id]), 'Duplicate Batch 616 ID: ' . $id);
    $ids[$id] = true;
    $assert(\App\Services\Shared\QuestionValidationService::validate($question)['valid'], 'Invalid new question: ' . $id);
    $taxonomy = $question['taxonomy'] ?? [];
    $assert(($taxonomy['board_id'] ?? '') === 'civil-engineering', 'Invalid Civil Engineering ownership: ' . $id);
    $assert(in_array($taxonomy['concept_id'] ?? '', ['structural-foundations', 'applied-foundations', 'hydro-geotech-foundations'], true), 'Unexpected concept: ' . $id);
    $assert(($question['context_dependent'] ?? false) === true && ($question['context_necessary'] ?? false) === true, 'Professional context metadata missing: ' . $id);
    $assert(($question['answer'] ?? '') !== '' && in_array($question['answer'], $question['choices'] ?? [], true), 'Answer key missing: ' . $id);
}

$metadata = json_decode((string) file_get_contents($root . '/storage/question-reuse-metadata.json'), true, 512, JSON_THROW_ON_ERROR);
$metadataByQuestion = [];
foreach ($metadata as $record) $metadataByQuestion[(string)($record['question_id'] ?? '')] = $record['reuse'] ?? [];
$links = json_decode((string) file_get_contents($root . '/storage/study-question-links.json'), true, 512, JSON_THROW_ON_ERROR);
$linked = [];
foreach ($links as $link) {
    $id = (string)($link['question_id'] ?? '');
    if ($id >= '2920' && $id <= '3099') $linked[$id] = true;
}
$eligibility = json_decode((string) file_get_contents($root . '/storage/question-eligibility.json'), true, 512, JSON_THROW_ON_ERROR);
$eligible = [];
foreach ($eligibility as $row) foreach ($row['question_ids'] ?? [] as $id) if ((int)$id >= 2920 && (int)$id <= 3099) $eligible[(string)$id] = true;
foreach ($ids as $id => $_) {
    $reuse = $metadataByQuestion[(string)$id] ?? [];
    $assert(($reuse['reuse_policy'] ?? '') === 'REVIEW', 'New question is not conservative REVIEW: ' . $id);
    $assert(in_array($reuse['required_depth'] ?? null, [3, 4], true), 'New question depth is unknown/invalid: ' . $id);
    $assert(($reuse['context_mode'] ?? '') === 'PROFESSIONAL', 'New question context is invalid: ' . $id);
    $assert(isset($linked[(string)$id]), 'New question lacks study link: ' . $id);
    $assert(isset($eligible[(string)$id]), 'New question lacks intended-board eligibility: ' . $id);
}

$coverage = json_decode((string) file_get_contents($root . '/storage/board-concept-coverage.json'), true, 512, JSON_THROW_ON_ERROR);
$calibrated = [];
foreach ($coverage as $row) if (($row['board_id'] ?? '') === 'civil-engineering' && in_array($row['concept_id'] ?? '', ['structural-foundations', 'applied-foundations', 'hydro-geotech-foundations'], true)) $calibrated[$row['concept_id']] = $row;
$assert(count($calibrated) === 3, 'Civil Engineering coverage calibration is incomplete');
foreach ($calibrated as $concept => $row) {
    $assert(($row['max_depth'] ?? null) === 4, 'Coverage depth is not 4: ' . $concept);
    $assert(($row['reuse_policy'] ?? '') === 'REVIEW', 'Coverage policy was loosened: ' . $concept);
}
$derived = json_decode((string) file_get_contents($root . '/storage/concept-derived-eligibility.json'), true, 512, JSON_THROW_ON_ERROR);
$relationships = array_values(array_filter($derived, static fn(array $r): bool => ($r['record_type'] ?? '') === 'RELATIONSHIP'));
$assert(count($relationships) === 40, 'Batch 616 must not activate new same-concept inheritance');
$assert(count(array_filter($relationships, static fn(array $r): bool => str_contains((string)($r['provenance']['reference'] ?? ''), 'batch616'))) === 0, 'Batch 616 unexpectedly added derived eligibility');
echo "[PASS] Batch 616 new content, depth, source-link, eligibility, coverage, and no-new-inheritance invariants verified.\n";
