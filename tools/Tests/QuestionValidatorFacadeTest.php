<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Quality\Validators\QuestionValidator;

if (!class_exists(QuestionValidator::class)) {
    throw new RuntimeException('The production question validation facade did not autoload.');
}

$validQuestion = [
    'id' => 'synthetic-validator-1',
    'question' => 'Which response matches the stated evidence?',
    'choices' => ['The supported choice.', 'An unsupported alternative.', 'A contradicted alternative.', 'An unrelated alternative.'],
    'answer' => 'The supported choice.',
    'explanation' => 'The explanation identifies the evidence and why the selected choice follows.',
    'difficulty' => 'medium',
    'status' => 'active',
    'taxonomy' => [
        'board_id' => 'let',
        'subject_id' => 'english',
        'domain_id' => 'reading-comprehension',
        'topic_id' => 'critical-reading',
        'concept_id' => 'critical-reading-evidence-quality',
    ],
];
if (QuestionValidator::validate($validQuestion) !== []) {
    throw new RuntimeException('A structurally valid nested-taxonomy question was rejected.');
}

$invalid = $validQuestion;
$invalid['taxonomy']['topic_id'] = ['forged' => 'array'];
$invalid['choices'] = ['Same', ' same ', '', 'Other'];
$invalid['answer'] = 'Missing answer';
$issues = QuestionValidator::validate($invalid);
$types = array_column($issues, 'type');
foreach (['invalid-topic', 'duplicate-choices', 'empty-choice', 'invalid-answer'] as $required) {
    if (!in_array($required, $types, true)) {
        throw new RuntimeException('Question validation missed ' . $required . '.');
    }
}

$malformedMetadata = $validQuestion;
$malformedMetadata['taxonomy'] = 'not-a-taxonomy-object';
$malformedMetadata['difficulty'] = ['invalid'];
$malformedMetadata['status'] = ['invalid'];
set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});
try {
    $malformedTypes = array_column(QuestionValidator::validate($malformedMetadata), 'type');
} finally {
    restore_error_handler();
}
foreach (['missing-board_id', 'missing-subject_id', 'missing-topic', 'invalid-difficulty', 'invalid-status'] as $required) {
    if (!in_array($required, $malformedTypes, true)) {
        throw new RuntimeException('Malformed question metadata was not safely reported: ' . $required . '.');
    }
}

echo "[PASS] Question validation facade autoload, nested taxonomy, malformed taxonomy, option uniqueness, and answer matching verified.\n";
