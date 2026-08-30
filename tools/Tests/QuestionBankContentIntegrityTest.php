<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$input = [
    'board_id' => 'LET',
    'subject_id' => 'English',
    'domain_id' => 'Grammar',
    'topic_id' => 'Parts',
    'concept_id' => 'Nouns',
    'difficulty' => 'medium',
    'type' => 'multiple_choice',
    'question' => 'Which word names a person?',
    'option_1' => 'Quickly',
    'option_2' => 'Teacher',
    'option_3' => 'Beautiful',
    'option_4' => 'Run',
    'correct_option' => 'option-2',
    'explanation' => 'Teacher names a person.',
];

$built = \App\Services\Question\QuestionBuilderService::build(0, $input);
$request = new \SelectionRequest(
    subject: 'English',
    domain: 'Grammar',
    difficultyDistribution: ['medium' => 1],
    questionCount: 1,
    topic: 'parts-of-speech'
);
$selected = \QuestionSelectionService::fulfillRequest([$built], $request)->questions;

if (count($selected) !== 1 || $selected[0]['choices'] !== ['Quickly', 'Teacher', 'Beautiful', 'Run']
    || $selected[0]['answer'] !== 'Teacher') {
    throw new RuntimeException('canonical authored options were not adapted for quiz runtime');
}

$malformedIssues = \App\Services\Quality\Validators\ChoiceValidator::validate([
    'choices' => ['A', ['invalid' => true], 'A'],
    'answer' => 'A',
]);
$codes = array_column($malformedIssues, 'type');
if (!in_array('invalid-choice', $codes, true) || !in_array('duplicate-choices', $codes, true)) {
    throw new RuntimeException('malformed choice data was not reported safely');
}

$quality = \App\Services\Question\QuestionQualityService::analyze();
$qualityCodes = array_map(
    static fn(object $issue): string => (string) $issue->code,
    $quality['issues']
);
if (in_array('invalid-status', $qualityCodes, true) || in_array('missing-subject_id', $qualityCodes, true)) {
    throw new RuntimeException('legacy top-level metadata was reported as invalid');
}

echo "[PASS] authored and legacy question records remain quiz-runtime compatible.\n";
