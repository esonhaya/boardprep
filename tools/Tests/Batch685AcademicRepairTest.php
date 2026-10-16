<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$questions = json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$byId = [];
foreach ($questions as $question) {
    if (is_array($question) && isset($question['id'])) $byId[(string) $question['id']] = $question;
}
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assert(str_contains((string) ($byId['616']['explanation'] ?? ''), '18% is 0.18')
    && str_contains((string) ($byId['616']['explanation'] ?? ''), '10% of 250 is 25 and 8% is 20'), 'Question 616 lost its reviewed percentage reasoning and independent check.');
$assert(($byId['937']['cognitive_intent'] ?? null) === 'understand', 'Question 937 cognitive intent does not match its explanation task.');
$assert(($byId['616']['answer'] ?? null) === '45' && ($byId['937']['answer'] ?? null) === 'it reduces transfer of organisms between the client, staff, and environment', 'A repaired record changed an answer key.');

$decisions = json_decode((string) file_get_contents($root . '/storage/academic-review-decisions.json'), true, 512, JSON_THROW_ON_ERROR);
$decisionsByQuestion = [];
foreach ($decisions as $decision) $decisionsByQuestion[(string) ($decision['question_id'] ?? '')] = $decision;
foreach (['616', '937'] as $questionId) {
    $decision = $decisionsByQuestion[$questionId] ?? [];
    $assert(($decision['review_state'] ?? '') === 'REVIEW_REQUIRED', 'Question ' . $questionId . ' was incorrectly marked academically signed off.');
    $assert(($decision['correction_state'] ?? '') === 'APPLIED_PENDING_HUMAN_SIGNOFF', 'Question ' . $questionId . ' correction is not visibly awaiting human sign-off.');
    $assert(($decision['human_signoff'] ?? '') === 'NOT_RECORDED' && !empty($decision['supporting_source_ids']) && !empty($decision['validation_result']), 'Question ' . $questionId . ' review record is incomplete or overstates sign-off.');
}

echo "Batch 685 academic repair and sign-off tests passed\n";
