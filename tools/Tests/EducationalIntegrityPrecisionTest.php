<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

$result = (new \Tools\Doctor\Project\BoardPrep\Checks\EducationalIntegrityCheck())->run();
$details = (array) $result->details;
$has = static fn(string $fragment): bool => count(array_filter(
    $details,
    static fn(string $detail): bool => str_contains($detail, $fragment)
)) > 0;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert($has('HARD_DEFECT count='), 'Hard defects are not distinguished.');
$assert($has('REVIEW_REQUIRED count='), 'Review-required evidence is not distinguished.');
$assert($has('JUSTIFIED_EXCEPTION count='), 'Documented exclusions are not distinguished.');
$assert($has('INSUFFICIENT_EVIDENCE count='), 'Coverage signals are not distinguished from defects.');
$assert($has('source_id=boardprep-blueprint-targets'), 'The explicit internal editorial source exception is not identified.');
$assert($has('question_id=263') && $has('board=let'), 'A justified flashcard exclusion does not expose its exact question and board.');
$assert($has('questions=8962 source_supported=8962 missing=0'), 'Question-to-source-study coverage is not reported with counts.');
$assert($has('repository_record_id=b633-arch-site-capacity') && $has('board=architecture'), 'Coverage evidence does not identify the affected taxonomy leaf and board.');
$assert($has('question_set_id=6973,7517') && $has('board=civil-engineering') && $has('classification=TRUE_DUPLICATE'), 'A live exact duplicate is not classified with its board and exact IDs.');
$assert($has('QUESTION_DUPLICATE_CLASSIFICATION groups=85 by_class=TRUE_DUPLICATE:85'), 'The Doctor does not publish deterministic duplicate classification totals.');
$assert($has('question_set_id=6975,7519') && $has('differing_fields=difficulty'), 'Difficulty conflicts among identical tasks are not identified by exact ID.');
$assert($has('remote URL reachability is not tested'), 'URL shape validation is overstated as remote availability verification.');
$assert($result->status !== 'FAIL', 'Existing justified exceptions or insufficient evidence became hard defects.');

$editorialAudit = \Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::analyze([
    [
        'id' => 'fixture-generic-rationale',
        'status' => 'active',
        'difficulty' => 'easy',
        'explanation' => 'The key grammatical relationship is shown by “sample”.',
        'taxonomy' => ['board_id' => 'let', 'concept_id' => 'critical-reading-evidence-quality'],
    ],
    [
        'id' => 'fixture-invalid-difficulty',
        'status' => 'active',
        'difficulty' => 'expert',
        'cognitive_intent' => 'analyze',
        'explanation' => 'The stated values give 4 + 5 = 9.',
        'taxonomy' => ['board_id' => 'civil-service', 'concept_id' => 'fixture-arithmetic'],
    ],
    [
        'id' => 'fixture-stem-metadata',
        'status' => 'active',
        'difficulty' => 'easy',
        'cognitive_intent' => 'recall',
        'question' => 'A notice is issued. (Review item first.) The competency focus is Word Meaning.',
        'explanation' => 'A concise item-specific explanation.',
        'taxonomy' => ['board_id' => 'civil-service', 'concept_id' => 'fixture-vocabulary'],
    ],
    [
        'id' => 'fixture-unlabeled-depth',
        'status' => 'active',
        'difficulty' => 'medium',
        'explanation' => 'Compare the evidence and its limits.',
        'taxonomy' => ['board_id' => 'nursing', 'concept_id' => 'fixture-assessment'],
    ],
    [
        'id' => 'fixture-valid-rationale',
        'status' => 'active',
        'difficulty' => 'medium',
        'cognitive_intent' => 'apply',
        'explanation' => 'The subject is singular, so the verb takes the singular form.',
        'taxonomy' => ['board_id' => 'let', 'concept_id' => 'fixture-grammar'],
    ],
    [
        'id' => 'fixture-duplicate-stem-a',
        'status' => 'active',
        'difficulty' => 'easy',
        'question' => 'A released notice names the correct office. Which wording is clearest?',
        'explanation' => 'Use a direct noun phrase.',
        'taxonomy' => ['board_id' => 'let', 'concept_id' => 'fixture-writing'],
    ],
    [
        'id' => 'fixture-duplicate-stem-b',
        'status' => 'active',
        'difficulty' => 'easy',
        'question' => ' A released notice names the correct office. Which wording is clearest? ',
        'explanation' => 'Use a direct noun phrase.',
        'taxonomy' => ['board_id' => 'let', 'concept_id' => 'fixture-writing'],
    ],
    [
        'id' => 'fixture-near-stem-a',
        'status' => 'active',
        'difficulty' => 'easy',
        'question' => 'A notice names the correct office. Which wording is clearest?',
        'explanation' => 'Use a direct noun phrase.',
        'taxonomy' => ['board_id' => 'let', 'concept_id' => 'fixture-writing'],
    ],
    [
        'id' => 'fixture-near-stem-b',
        'status' => 'active',
        'difficulty' => 'easy',
        'question' => 'A notice names the correct office! Which wording is clearest?',
        'explanation' => 'Use a direct noun phrase.',
        'taxonomy' => ['board_id' => 'let', 'concept_id' => 'fixture-writing'],
    ],
]);
$editorialFindings = $editorialAudit['findings'] ?? [];
$hasEditorialFinding = static fn(string $code, string $id): bool => count(array_filter(
    $editorialFindings,
    static fn(array $finding): bool => ($finding['code'] ?? '') === $code
        && str_contains((string) ($finding['entity_id'] ?? ''), $id)
)) > 0;
$assert($hasEditorialFinding('GENERIC_EXPLANATION_TEMPLATE', 'fixture-generic-rationale'), 'Generic rationales are not raised as review-required exact-ID candidates.');
$assert($hasEditorialFinding('INVALID_DIFFICULTY_LABEL', 'fixture-invalid-difficulty'), 'Invalid difficulty labels are not identified as hard defects.');
$assert($hasEditorialFinding('EDITORIAL_METADATA_LEAK_IN_STEM', 'fixture-stem-metadata'), 'Authoring labels leaked into learner-facing stems are not identified.');
$assert($hasEditorialFinding('MISSING_COGNITIVE_INTENT', 'fixture-unlabeled-depth'), 'Missing cognitive-intent metadata is not classified as insufficient evidence.');
$assert($hasEditorialFinding('EXACT_DUPLICATE_STEM', 'fixture-duplicate-stem-a,fixture-duplicate-stem-b'), 'Exact normalized duplicate stems are not grouped with their exact IDs for review.');
$assert($hasEditorialFinding('PUNCTUATION_ONLY_STEM_VARIANT', 'fixture-near-stem-a,fixture-near-stem-b'), 'Punctuation-only near-duplicate candidates are not grouped for review.');
$assert(!$hasEditorialFinding('GENERIC_EXPLANATION_TEMPLATE', 'fixture-valid-rationale'), 'A specific grammar explanation became a false positive.');
$assert(count($editorialAudit['profiles'] ?? []) === 3, 'Board depth profiles are not deterministic or board-scoped.');
$assert(!str_contains(implode("\n", $details), 'question_id=1;'), 'A repaired live rationale is still flagged.');
$assert(count(array_filter($details, static fn(string $detail): bool => str_starts_with($detail, 'BOARD_QUESTION_DEPTH board=')) ) === 10, 'The live Doctor report does not profile all ten boards.');

echo "[PASS] Educational integrity classifies exact defects, review items, exceptions, and insufficient-evidence records.\n";
echo "[PASS] Source-study coverage is board-aware and remote URL availability is not overclaimed.\n";
echo "[PASS] Question-depth audit exposes exact missing-intent IDs, invalid difficulty, and generic rationale candidates without relabeling curriculum scope.\n";
