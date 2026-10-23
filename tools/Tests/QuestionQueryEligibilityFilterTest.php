<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionQueryService;
use App\Services\Shared\TaxonomyStorageService;

App::boot();
$questions = App::container()->get(QuestionRepository::class)->all();
$index = QuestionEligibilityService::index();
$target = null;

foreach ($questions as $question) {
    foreach (TaxonomyStorageService::boards() as $board) {
        $boardId = trim((string) ($board['id'] ?? ''));
        if ($boardId === '') {
            continue;
        }
        $placement = $index->forExam($question, $boardId);
        $subjectId = trim((string) ($placement['subject_id'] ?? ''));
        if ($placement !== null && $subjectId !== '') {
            $target = ['question' => $question, 'board' => $boardId, 'subject' => $subjectId];
            break 2;
        }
    }
}

if (!is_array($target)) {
    throw new RuntimeException('No effective question placement was available for the eligibility filter regression.');
}

$questionId = (string) ($target['question']['id'] ?? '');
$byExactId = QuestionQueryService::getQuestions(['search' => $questionId]);
if (count($byExactId) !== 1 || (string) ($byExactId[0]['id'] ?? '') !== $questionId) {
    throw new RuntimeException('Question repository search did not resolve the exact canonical ID.');
}

$filtered = QuestionQueryService::getQuestions([
    'board' => $target['board'],
    'subject' => $target['subject'],
]);
if (!in_array($questionId, array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $filtered), true)) {
    throw new RuntimeException('Effective board/subject filter omitted a question with a valid production placement.');
}
foreach ($filtered as $question) {
    $placement = $index->forExam($question, $target['board']);
    if ($placement === null || strtolower(trim((string) ($placement['subject_id'] ?? ''))) !== strtolower($target['subject'])) {
        throw new RuntimeException('Board/subject filter returned a question outside its production eligibility placement.');
    }
}

$page = QuestionQueryService::getQuestionPage(['board' => $target['board'], 'subject' => $target['subject']], 1, 5);
if (($page['canonical_total'] ?? 0) !== count($questions) || ($page['total'] ?? 0) !== count($filtered)) {
    throw new RuntimeException('Question inventory did not distinguish canonical totals from effective placement matches.');
}

echo "[PASS] Question repository ID search, board/subject eligibility, and canonical versus filtered totals verified.\n";
