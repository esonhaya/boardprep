<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Quality\QuestionDistractorAuditService;
use App\Services\Quality\Validators\Choice\ChoiceDuplicateDetector;

$questions = [[
    'id' => 'values-duplicate-fixture',
    'answer' => 'The correct choice.',
    'choices' => ['The correct choice.', ' the   correct choice. ', 'A different response.', 'A third option.'],
], [
    'id' => 'decimal-difference-fixture',
    'answer' => '1.5 kN',
    'choices' => ['1.5 kN', '15 kN', '0.15 kN', '150 kN'],
], [
    'id' => 'long-choice-fixture',
    'answer' => 'A short keyed answer.',
    'choices' => ['A short keyed answer.', str_repeat('A much longer but possibly justified option ', 4), 'Another short option.', 'Yet another short option.'],
], [
    'id' => 'near-choice-fixture',
    'answer' => 'A learner should compare the evidence before choosing.',
    'choices' => [
        'A learner should compare the evidence before choosing.',
        'A learner should compare the evidence before answering.',
        'The learner should ignore the evidence in the example.',
        'The teacher should remove every choice from the task.',
    ],
], [
    'id' => 'obvious-polarity-fixture',
    'answer' => 'Check the facts, hear from those affected, and use the authorized process.',
    'choices' => [
        'Check the facts, hear from those affected, and use the authorized process.',
        'Ignore the report until another person confirms it.',
        'Avoid asking what happened so no one feels uncomfortable.',
        'Blame the learner who raised the concern for causing conflict.',
    ],
], [
    'id' => 'values-repeat-a', 'answer' => 'A reasoned first response.',
    'taxonomy' => ['board_id' => 'let', 'subject_id' => 'values-education'],
    'choices' => ['A reasoned first response.', 'Do not examine the facts.', 'Use a fair process.', 'Invite respectful discussion.'],
], [
    'id' => 'values-repeat-b', 'answer' => 'A reasoned second response.',
    'taxonomy' => ['board_id' => 'let', 'subject_id' => 'values-education'],
    'choices' => ['A reasoned second response.', 'Do not examine the facts.', 'Ask what is known.', 'Invite respectful discussion.'],
], [
    'id' => 'values-repeat-c', 'answer' => 'A reasoned third response.',
    'taxonomy' => ['board_id' => 'let', 'subject_id' => 'values-education'],
    'choices' => ['A reasoned third response.', 'Do not examine the facts.', 'Check who is affected.', 'Invite respectful discussion.'],
]];
$audit = QuestionDistractorAuditService::analyze($questions);
$has = static fn(string $id, string $code): bool => count(array_filter($audit['findings'], static fn(array $row): bool => ($row['question_id'] ?? '') === $id && ($row['code'] ?? '') === $code)) > 0;
if (!$has('values-duplicate-fixture', 'EXACT_OR_NORMALIZED_DUPLICATE')) throw new RuntimeException('Exact/normalized duplicate choice was not a deterministic error.');
if ($has('decimal-difference-fixture', 'EXACT_OR_NORMALIZED_DUPLICATE')) throw new RuntimeException('Meaningful decimal differences were collapsed into a false duplicate.');
if (!$has('long-choice-fixture', 'OPTION_LENGTH_OUTLIER')) throw new RuntimeException('The option-length review cue was not recorded.');
if (!$has('near-choice-fixture', 'NEAR_DUPLICATE_OPTIONS')) throw new RuntimeException('The similar wording review cue was not recorded.');
if (!$has('obvious-polarity-fixture', 'OBVIOUS_POLARITY_CUE')) throw new RuntimeException('The obvious polarity review cue was not recorded.');
if (!count(array_filter($audit['findings'], static fn(array $row): bool => ($row['code'] ?? '') === 'REPEATED_DISTRACTOR_TEXT' && ($row['details']['group'] ?? '') === 'let|values-education'))) throw new RuntimeException('Repeated distractor text was not added to the nonbinding review queue.');
$format = ChoiceDuplicateDetector::validate(['Correct answer!', 'correct answer!']);
if (($format[0]['severity'] ?? '') !== 'error') throw new RuntimeException('An exact duplicate after case/spacing normalization did not fail deterministic integrity.');
if (ChoiceDuplicateDetector::validate(['AB-0072', 'AB-0072.']) !== []) throw new RuntimeException('Distinct exact-transcription options were collapsed by punctuation normalization.');
if (($audit['summary']['ERROR'] ?? 0) !== 1 || ($audit['summary']['REVIEW'] ?? 0) < 2) throw new RuntimeException('Deterministic errors and heuristic review cues were not separated.');
echo "[PASS] Distractor audit separates deterministic duplicates from nonbinding wording and length review cues.\n";
