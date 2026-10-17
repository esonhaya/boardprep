<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Quiz\Start\QuizStartPreparationService;

$questions = \App\Core\App::container()->get(\App\Repositories\QuestionRepository::class)->all();
$index = QuestionEligibilityService::index();
$checkScoped = static function (string $subject, string $mode = 'practice', bool $adaptive = false) use ($questions, $index): array {
    $prepared = QuizStartPreparationService::prepare([
        'exam' => 'LET',
        'subject' => $subject,
        'count' => 10,
        'mode' => $mode,
        'difficulty' => 'mixed',
        'adaptive' => $adaptive ? '1' : '',
    ], $questions, $index);
    if ($prepared->questions === []) throw new RuntimeException('No questions were returned for explicit LET subject ' . $subject . '.');
    foreach ($prepared->questions as $question) {
        $effective = QuestionEligibilityService::forExam($question, 'LET', $index);
        if (strtolower((string) ($effective['subject_id'] ?? '')) !== strtolower($subject)) {
            throw new RuntimeException('A question crossed an explicit LET subject boundary: ' . $subject . ' / ' . (string) ($question['id'] ?? ''));
        }
    }
    return $prepared->questions;
};

$english = $checkScoped('english');
$math = $checkScoped('mathematics');
$values = $checkScoped('values-education');
$examEnglish = $checkScoped('english', 'exam');
$adaptiveMath = $checkScoped('mathematics', 'practice', true);
if (count($english) > 10 || count($math) > 10 || count($values) > 10 || count($examEnglish) > 10 || count($adaptiveMath) > 10) {
    throw new RuntimeException('A subject-scoped selection exceeded its request size.');
}

foreach ([
    ['english', 'ELEMENTARY', ''],
    ['english', 'SECONDARY', 'secondary-english'],
    ['values-education', 'SECONDARY', 'secondary-values-education'],
] as [$subject, $level, $specialization]) {
    $variant = QuizStartPreparationService::prepare([
        'exam' => 'LET', 'subject' => $subject, 'count' => 5, 'mode' => 'practice',
        'let_level' => $level, 'let_specialization' => $specialization,
    ], $questions, $index);
    if ($variant->questions === []) throw new RuntimeException("LET {$level}/{$specialization} returned no eligible {$subject} questions.");
    foreach ($variant->questions as $question) {
        $placement = \App\Services\Quiz\LET\LETVariantRegistry::placementForConfiguration(
            $question,
            $variant->specification->letVariant,
            null,
            QuestionEligibilityService::forExam($question, 'LET', $index)
        );
        if ($placement === null) throw new RuntimeException("LET {$level}/{$specialization} delivered an incompatible question.");
    }
}

$factoryQuestion = static function (string $id, string $subject): array {
    return [
        'id' => $id,
        'status' => 'active',
        'question' => 'Which example demonstrates the stated study concept for ' . $subject . '?',
        'choices' => ['A plausible first response', 'A supported second response', 'An unrelated response', 'An unsafe response'],
        'answer' => 'A supported second response',
        'explanation' => 'The second response applies the stated concept in the supplied context.',
        'difficulty' => 'medium',
        'taxonomy' => ['board_id' => 'let', 'subject_id' => $subject, 'domain_id' => 'domain-' . $subject, 'topic_id' => 'topic-' . $subject],
    ];
};
$fixtureQuestions = [$factoryQuestion('fixture-english', 'english'), $factoryQuestion('fixture-values', 'values-education')];
$fixtureIndex = new QuestionEligibilityIndex([
    ['question_ids' => ['fixture-english'], 'exam_ids' => ['LET'], 'subject_ids' => ['let' => 'english']],
    ['question_ids' => ['fixture-values'], 'exam_ids' => ['LET'], 'subject_ids' => ['let' => 'values-education']],
]);
$filtered = QuestionPoolFilter::filter(
    $fixtureQuestions,
    new SelectionRequest('english', null, [], 10, exam: 'LET'),
    $fixtureIndex
);
if (count($filtered) !== 1 || ($filtered[0]['id'] ?? '') !== 'fixture-english') {
    throw new RuntimeException('An insufficient explicit subject pool widened into another subject.');
}
$crossBoardEligible = $factoryQuestion('fixture-cross-board-valid', 'mathematics');
$crossBoardEligible['taxonomy']['board_id'] = 'nursing';
$crossBoardIndex = new QuestionEligibilityIndex([
    ['question_ids' => ['fixture-cross-board-valid'], 'exam_ids' => ['LET'], 'subject_ids' => ['let' => 'english']],
]);
$crossBoardEnglish = QuestionPoolFilter::filter(
    [$crossBoardEligible],
    new SelectionRequest('english', null, [], 1, exam: 'LET'),
    $crossBoardIndex
);
$crossBoardSourceSubject = QuestionPoolFilter::filter(
    [$crossBoardEligible],
    new SelectionRequest('mathematics', null, [], 1, exam: 'LET'),
    $crossBoardIndex
);
if (count($crossBoardEnglish) !== 1 || $crossBoardSourceSubject !== []) {
    throw new RuntimeException('A reusable question did not obey its explicit effective LET destination subject.');
}
$mismatch = $factoryQuestion('fixture-missing-destination', 'english');
$mismatch['taxonomy']['board_id'] = 'nursing';
$mismatchIndex = new QuestionEligibilityIndex([
    ['question_ids' => ['fixture-missing-destination'], 'exam_ids' => ['LET'], 'subject_ids' => ['let' => '']],
]);
if (QuestionPoolFilter::filter([$mismatch], new SelectionRequest('*', null, [], 1, exam: 'LET'), $mismatchIndex) !== []) {
    throw new RuntimeException('A cross-board question with no effective destination placement was delivered.');
}

echo "[PASS] Live English, Mathematics, Values Education, exam, and adaptive journeys preserve effective LET subject eligibility.\n";
echo "[PASS] LET Elementary, Secondary English, Secondary Values, and explicit cross-board destination placement are independently checked.\n";
echo "[PASS] Small-pool and unplaced cross-board fixtures fail closed without broadening to another subject.\n";
