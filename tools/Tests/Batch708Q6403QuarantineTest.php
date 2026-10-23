<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityService;

App::boot();
$questions = App::container()->get(QuestionRepository::class)->all();
$byId = [];
foreach ($questions as $question) {
    if (is_scalar($question['id'] ?? null)) $byId[(string) $question['id']] = $question;
}
$held = $byId['6403'] ?? null;
if (!is_array($held)
    || (string) ($held['answer'] ?? '') !== 'AB-0072'
    || !in_array('AB-0072.', array_map('strval', (array) ($held['choices'] ?? [])), true)
    || !str_contains((string) ($held['explanation'] ?? ''), 'punctuation')) {
    throw new RuntimeException('Q6403 no longer matches the recorded exact-transcription contradiction.');
}

$index = QuestionEligibilityService::index();
$override = array_values(array_filter(App::storage()->all('question-reuse-overrides'), static fn(mixed $record): bool =>
    is_array($record) && (string) ($record['question_id'] ?? '') === '6403'
    && strtolower((string) ($record['board_id'] ?? '')) === 'civil-service'
    && ($record['override_type'] ?? '') === 'EXCLUDE'
));
if (count($override) !== 1 || $index->forExam($held, 'civil-service') !== null) {
    throw new RuntimeException('The explicit Civil Service selection hold is missing or ineffective.');
}

$candidate = null;
foreach ($questions as $question) {
    if (!is_array($question) || (string) ($question['id'] ?? '') === '6403') continue;
    $placement = $index->forExam($question, 'civil-service');
    if ($placement !== null && strtolower((string) ($placement['subject_id'] ?? '')) === 'clerical-ability'
        && in_array(strtolower((string) ($question['status'] ?? 'active')), ['active', 'approved'], true)) {
        $candidate = $question;
        break;
    }
}
if (!is_array($candidate)) {
    throw new RuntimeException('No other eligible Civil Service clerical question was available to verify ordinary practice.');
}
$difficulty = strtolower((string) ($candidate['difficulty'] ?? 'easy'));
$difficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'easy';
$normalRequest = new SelectionRequest('clerical-ability', null, [$difficulty => 1], 1, exam: 'civil-service');
$normalPool = QuestionPoolFilter::filter([$held, $candidate], $normalRequest, $index);
if (in_array('6403', array_map(static fn(array $question): string => (string) $question['id'], $normalPool), true)
    || !in_array((string) $candidate['id'], array_map(static fn(array $question): string => (string) $question['id'], $normalPool), true)) {
    throw new RuntimeException('Normal Civil Service subject practice did not quarantine Q6403 while retaining eligible questions.');
}

$randomRequest = new SelectionRequest('*', null, [$difficulty => 1], 1, exam: 'civil-service');
$random = QuestionSelectionService::fulfillRequest([$held, $candidate], $randomRequest, $index)->questions;
if (in_array('6403', array_map(static fn(array $question): string => (string) $question['id'], $random), true)) {
    throw new RuntimeException('A fresh Civil Service selection bypassed the Q6403 hold.');
}

$blueprintRequest = new SelectionRequest('clerical-ability', null, [$difficulty => 2], 2, exam: 'civil-service');
$blueprint = BlueprintRequestExecutor::execute([$held, $candidate], [$blueprintRequest], $index);
if (in_array('6403', array_map(static fn(array $question): string => (string) $question['id'], $blueprint), true)) {
    throw new RuntimeException('Blueprint shortage/recovery selection bypassed the Q6403 hold.');
}

$historicalAnswer = QuestionScoreEvaluator::evaluate($held, 'AB-0072');
$alternateAnswer = QuestionScoreEvaluator::evaluate($held, 'AB-0072.');
if (($historicalAnswer['correct'] ?? false) !== true || ($alternateAnswer['correct'] ?? false) !== false) {
    throw new RuntimeException('The stored answer key behavior changed while verifying Q6403 quarantine.');
}

echo "[PASS] Q6403 is excluded from fresh normal, random, and blueprint-recovery CSE selection; other clerical practice remains available and its canonical score rule is unchanged.\n";
