<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Board\BoardViewService;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Shared\QuestionCoverageService;

$questions = App::storage()->all('questions');
$ids = array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $questions);
if (count($questions) !== 706 || count(array_unique($ids)) !== 706) {
    throw new RuntimeException('canonical question identities were not preserved');
}
foreach (range(1, 258) as $id) {
    if (!in_array((string) $id, $ids, true)) {
        throw new RuntimeException("existing question identity {$id} was lost");
    }
}

$let = QuestionEligibilityService::eligible($questions, 'let');
$cse = QuestionEligibilityService::eligible($questions, 'civil-service');
if (count($let) !== 514 || count($cse) !== 375) {
    throw new RuntimeException('explicit exam eligibility counts are incorrect');
}
if (count(array_unique(array_map(static fn(array $q): string => (string) $q['id'], $cse))) !== 375) {
    throw new RuntimeException('CSE eligibility contains duplicate canonical records');
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
    new QuizSpecification('CSE', 'Verbal Ability', null, [], [], 'mixed', 10, 'practice', false, true, null, null)
);
if (count($verbal) !== 10 || count(array_unique(array_map(static fn(array $q): string => (string) $q['id'], $verbal))) !== 10) {
    throw new RuntimeException('CSE verbal production selection did not return a unique usable set');
}
foreach ($verbal as $question) {
    if (QuestionEligibilityService::forExam($question, 'civil-service') === null) {
        throw new RuntimeException('CSE selection returned an ineligible question');
    }
}

$letView = BoardViewService::find('let');
$cseView = BoardViewService::find('civil-service');
if (($letView['available_questions'] ?? 0) !== 514 || ($cseView['available_questions'] ?? 0) !== 375) {
    throw new RuntimeException('board availability is not derived from eligibility');
}
$coverage = QuestionCoverageService::analyzeRepository()['blueprints'] ?? [];
$cseCoverage = array_values(array_filter(
    $coverage,
    static fn(array $report): bool => ($report['board'] ?? '') === 'civil-service'
));
if ($cseCoverage === [] || ($cseView['content_readiness']['status'] ?? '') !== 'STUDY_READY') {
    throw new RuntimeException('CSE blueprint/readiness integration is incomplete');
}
$expectedBoards = ['let', 'civil-service', 'criminologist', 'nursing', 'psychometrician'];
foreach ($expectedBoards as $boardId) {
    $view = BoardViewService::find($boardId);
    if ($view === null || !isset($view['content_readiness']['status'])) {
        throw new RuntimeException("missing registered board: {$boardId}");
    }
}
$foundationCounts = ['criminologist' => 103, 'nursing' => 20, 'psychometrician' => 20];
foreach ($foundationCounts as $boardId => $count) {
    $view = BoardViewService::find($boardId);
    if (($view['available_questions'] ?? 0) !== $count
        || ($view['content_readiness']['status'] ?? '') !== 'FOUNDATION') {
        throw new RuntimeException("{$boardId} foundation readiness is not repository-derived");
    }
}

echo '[PASS] Multi-exam eligibility, identity preservation, CSE selection, and board counts verified.' . PHP_EOL;
