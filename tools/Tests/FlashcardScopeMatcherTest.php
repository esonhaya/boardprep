<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Flashcard\FlashcardScopeMatcher;
use App\Services\Question\QuestionEligibilityIndex;

$crossBoard = [
    'id' => 'fixture-shared-english',
    'status' => 'active',
    'question' => 'Which response uses the shared teaching concept?',
    'choices' => ['A supported response', 'An unrelated response', 'A harmful response', 'A copied response'],
    'answer' => 'A supported response',
    'explanation' => 'The supported response applies the concept.',
    'difficulty' => 'medium',
    'taxonomy' => ['board_id' => 'nursing', 'subject_id' => 'clinical-practice', 'topic_id' => 'shared-topic'],
];
$crossIndex = new QuestionEligibilityIndex([
    ['question_ids' => ['fixture-shared-english'], 'exam_ids' => ['LET'], 'subject_ids' => ['let' => 'english']],
]);
if (!FlashcardScopeMatcher::matches($crossBoard, ['board' => 'let', 'subject' => 'english'], $crossIndex)
    || FlashcardScopeMatcher::matches($crossBoard, ['board' => 'let', 'subject' => 'clinical-practice'], $crossIndex)) {
    throw new RuntimeException('Flashcard scope followed the source subject instead of the explicit eligible destination subject.');
}

$cse = $crossBoard;
$cse['id'] = 'fixture-cse-clerical';
$cse['taxonomy'] = ['board_id' => 'civil-service', 'subject_id' => 'clerical-ability', 'topic_id' => 'filing'];
$cse['civil_service_levels'] = ['SUBPROFESSIONAL'];
$cseIndex = new QuestionEligibilityIndex([
    ['question_ids' => ['fixture-cse-clerical'], 'exam_ids' => ['civil-service'], 'subject_ids' => ['civil-service' => 'clerical-ability']],
]);
if (FlashcardScopeMatcher::matches($cse, ['board' => 'civil-service', 'civil_service_level' => 'PROFESSIONAL'], $cseIndex)
    || !FlashcardScopeMatcher::matches($cse, ['board' => 'civil-service', 'civil_service_level' => 'SUBPROFESSIONAL'], $cseIndex)) {
    throw new RuntimeException('Flashcards ignored Civil Service candidate-level eligibility.');
}

$let = $crossBoard;
$let['id'] = 'fixture-let-secondary-english';
$let['taxonomy'] = ['board_id' => 'let', 'subject_id' => 'english', 'topic_id' => 'english-topic'];
$letIndex = new QuestionEligibilityIndex([
    [
        'question_ids' => ['fixture-let-secondary-english'],
        'exam_ids' => ['LET'],
        'subject_ids' => ['let' => 'english'],
        'variant_placements' => ['let' => [[
            'level' => 'SECONDARY',
            'component' => 'SPECIALIZATION',
            'specialization_id' => 'secondary-english',
        ]]],
    ],
]);
if (!FlashcardScopeMatcher::matches($let, [
    'board' => 'let', 'subject' => 'english',
    'let_variant' => ['level' => 'SECONDARY', 'specialization_id' => 'secondary-english'],
], $letIndex)
    || FlashcardScopeMatcher::matches($let, [
        'board' => 'let', 'subject' => 'english',
        'let_variant' => ['level' => 'ELEMENTARY', 'specialization_id' => null],
    ], $letIndex)) {
    throw new RuntimeException('Flashcards ignored the preferred LET level or specialization.');
}

echo "[PASS] Flashcard filters preserve explicit cross-board destination subject and LET/Civil Service candidate eligibility.\n";
