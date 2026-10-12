<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;

$questions = App::storage()->all('questions');
$eligibilityIndex = QuestionEligibilityService::index();
$ids = array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $questions);
if ($questions === [] || count(array_unique($ids)) !== count($questions)) {
    throw new RuntimeException('canonical question identities were not preserved');
}
foreach (range(1, 258) as $id) {
    if (!in_array((string) $id, $ids, true)) {
        throw new RuntimeException("existing question identity {$id} was lost");
    }
}

$let = QuestionEligibilityService::eligible($questions, 'let');
$cse = QuestionEligibilityService::eligible($questions, 'civil-service');
if (count(array_unique(array_map(static fn(array $q): string => (string) $q['id'], $let))) !== count($let)
    || count(array_unique(array_map(static fn(array $q): string => (string) $q['id'], $cse))) !== count($cse)) {
    throw new RuntimeException('CSE eligibility contains duplicate canonical records');
}
$ownedByBoard = static function (array $boardQuestions, string $board): array {
    return array_values(array_filter(
        $boardQuestions,
        static fn(array $question): bool => ($question['board'] ?? ($question['taxonomy']['board_id'] ?? '')) === $board
    ));
};
foreach (['let' => $let, 'civil-service' => $cse] as $board => $eligible) {
    foreach ($ownedByBoard($questions, $board) as $question) {
        if (QuestionEligibilityService::forExam($question, $board, $eligibilityIndex) === null) {
            throw new RuntimeException("owned {$board} question is not eligible");
        }
    }
}
$analytical = array_values(array_filter(
    $cse,
    static fn(array $question): bool => ($question['taxonomy']['subject_id'] ?? '') === 'analytical-logical'
));
if (count($analytical) < 70) {
    throw new RuntimeException('CSE analytical/logical foundation is incomplete');
}

$verbal = QuestionSelectionService::select(
    $questions,
    new QuizSpecification('CSE', 'Verbal Ability', null, [], [], 'mixed', 10, 'practice', false, true, null, null),
    $eligibilityIndex
);
if (count($verbal) !== 10 || count(array_unique(array_map(static fn(array $q): string => (string) $q['id'], $verbal))) !== 10) {
    throw new RuntimeException('CSE verbal production selection did not return a unique usable set');
}
foreach ($verbal as $question) {
    if (QuestionEligibilityService::forExam($question, 'civil-service', $eligibilityIndex) === null) {
        throw new RuntimeException('CSE selection returned an ineligible question');
    }
}
$verbalWithoutExplicitIndex = QuestionSelectionService::select(
    $questions,
    new QuizSpecification('CSE', 'Verbal Ability', null, [], [], 'mixed', 10, 'practice', false, true, null, null)
);
if (count($verbalWithoutExplicitIndex) !== 10
    || array_filter($verbalWithoutExplicitIndex, static fn(array $question): bool =>
        QuestionEligibilityService::forExam($question, 'civil-service', $eligibilityIndex) === null
    ) !== []) {
    throw new RuntimeException('default production selector did not preserve indexed eligibility semantics');
}

// These canonical records come from the real Batch 625/669 collision areas:
// CPA and Criminologist keep their own propositions while Nursing additions
// use the separate canonical Nursing records.  No title or numeric match
// should create an implicit cross-board placement.
$collisionBoardCases = [
    '4163' => 'cpa',
    '4200' => 'cpa',
    '4320' => 'criminologist',
    '4400' => 'criminologist',
    '4482' => 'criminologist',
    '4873' => 'nursing',
    '5192' => 'nursing',
];
$questionsById = [];
foreach ($questions as $question) {
    $questionsById[(string) ($question['id'] ?? '')] = $question;
}
foreach ($collisionBoardCases as $id => $owner) {
    $question = $questionsById[$id] ?? null;
    $actualOwner = is_array($question)
        ? (string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? '')
        : '';
    if (!is_array($question) || $actualOwner !== $owner
        || QuestionEligibilityService::forExam($question, $owner, $eligibilityIndex) === null) {
        throw new RuntimeException("collision regression question {$id} lost its {$owner} identity");
    }
    foreach (['nursing', 'cpa', 'criminologist'] as $otherBoard) {
        if ($otherBoard !== $owner
            && QuestionEligibilityService::forExam($question, $otherBoard, $eligibilityIndex) !== null) {
            throw new RuntimeException("collision regression question {$id} leaked from {$owner} into {$otherBoard}");
        }
    }
}

// Compare indexed results with the original storage-backed resolver for a
// deterministic sample from each collision-sensitive board.
foreach (['cpa', 'criminologist', 'nursing'] as $board) {
    $sample = array_values(array_filter(
        $questions,
        static fn(array $question): bool => ($question['taxonomy']['board_id'] ?? $question['board'] ?? '') === $board
    ));
    foreach (array_slice($sample, 0, 2) as $question) {
        foreach ([$board, 'nursing', 'cpa', 'criminologist'] as $candidateBoard) {
            $indexed = QuestionEligibilityService::forExam($question, $candidateBoard, $eligibilityIndex);
            $storageBacked = QuestionEligibilityService::forExam($question, $candidateBoard);
            if ($indexed !== $storageBacked) {
                throw new RuntimeException("indexed eligibility differs for {$board} question {$question['id']} on {$candidateBoard}");
            }
        }
    }
}

$expectedBoards = ['let', 'civil-service', 'criminologist', 'nursing', 'psychometrician', 'civil-engineering', 'cpa', 'mechanical-engineering', 'registered-electrical-engineering', 'architecture'];
$registeredBoards = array_fill_keys(array_map(
    static fn(array $board): string => (string) ($board['id'] ?? ''),
    App::storage()->all('boards')
), true);
foreach ($expectedBoards as $boardId) {
    if (!isset($registeredBoards[$boardId])) {
        throw new RuntimeException("missing registered board: {$boardId}");
    }
}

echo '[PASS] Multi-exam eligibility, identity preservation, CSE selection, collision-board isolation, and indexed parity verified.' . PHP_EOL;
