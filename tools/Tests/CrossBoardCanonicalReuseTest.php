<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/Support/StaticStorageFixture.php';
$root = dirname(__DIR__, 2);
putenv('APP_STORAGE_PATH=' . StaticStorageFixture::create($root, 'cross-board-canonical-reuse'));
putenv('DB_DRIVER=json');
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Board\BoardViewService;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionReuseEligibilityResolver;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$questions = App::storage()->all('questions');
$byId = [];
foreach ($questions as $question) {
    $byId[(string) ($question['id'] ?? '')] = $question;
}

$shared = $byId['1'] ?? null;
$architectureOnly = $byId['2868'] ?? null;
$assert(is_array($shared) && is_array($architectureOnly), 'cross-board fixture questions are missing');

$index = new QuestionEligibilityIndex(
    App::storage()->all('question-eligibility'),
    App::storage()->all('blueprints'),
    QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    App::storage()->all('question-reuse-overrides')
);
$letMapping = QuestionEligibilityService::forExam($shared, 'let', $index);
$cseMapping = QuestionEligibilityService::forExam($shared, 'civil-service', $index);
$architectureMapping = QuestionEligibilityService::forExam($architectureOnly, 'architecture', $index);
$assert(($letMapping['subject_id'] ?? '') === 'english', 'shared question lost LET subject mapping');
$assert(($cseMapping['subject_id'] ?? '') === 'verbal-ability', 'shared question lost CSE blueprint mapping');
$assert(($architectureMapping['subject_id'] ?? '') === 'architecture-history-and-theory', 'architecture mapping was not resolved');
$assert(QuestionEligibilityService::forExam($architectureOnly, 'let', $index) === null, 'architecture-only question leaked into LET eligibility');
$assert(QuestionEligibilityService::forExam($architectureOnly, 'civil-service', $index) === null, 'architecture-only question leaked into CSE eligibility');

$letBoard = BoardViewService::find('let');
$cseBoard = BoardViewService::find('civil-service');
$letReadiness = $letBoard['content_readiness'] ?? [];
$cseReadiness = $cseBoard['content_readiness'] ?? [];
$letEligibleCount = count(array_filter($questions, static fn(array $question): bool => QuestionEligibilityService::forExam($question, 'let', $index) !== null));
$cseEligibleCount = count(array_filter($questions, static fn(array $question): bool => QuestionEligibilityService::forExam($question, 'civil-service', $index) !== null));
$assert(($letReadiness['eligible_questions'] ?? 0) === $letEligibleCount, 'LET board view did not consume shared eligibility mappings');
$assert(($cseReadiness['eligible_questions'] ?? 0) === $cseEligibleCount, 'CSE board view did not consume shared eligibility mappings');

$letSelection = QuestionSelectionService::select(
    [$shared, $architectureOnly],
    new QuizSpecification('let', 'English', null, [], [], 'easy', 1, 'practice', false, false, null, null)
);
$cseSelection = QuestionSelectionService::select(
    [$shared, $architectureOnly],
    new QuizSpecification('civil-service', 'Verbal Ability', null, [], [], 'easy', 1, 'practice', false, false, null, null)
);
$architectureSelection = QuestionSelectionService::select(
    [$shared, $architectureOnly],
    new QuizSpecification('architecture', 'History and Theory', null, [], [], 'hard', 1, 'practice', false, false, null, null)
);
$assert(array_map(static fn(array $question): string => (string) $question['id'], $letSelection) === ['1'], 'LET production selection did not reuse the shared canonical question');
$assert(array_map(static fn(array $question): string => (string) $question['id'], $cseSelection) === ['1'], 'CSE production selection did not reuse the shared canonical question');
$assert(array_map(static fn(array $question): string => (string) $question['id'], $architectureSelection) === ['2868'], 'architecture production selection did not retain board-specific content');
$assert(count(array_unique(array_map(static fn(array $question): string => (string) $question['id'], $cseSelection))) === count($cseSelection), 'multi-board selection duplicated a canonical question');

echo '[PASS] Cross-board canonical eligibility, blueprint mapping, production selection, and board isolation verified.' . PHP_EOL;
