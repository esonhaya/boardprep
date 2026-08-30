<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Quiz\Start\QuizStartInputNormalizer;
use App\Services\Quiz\Start\QuizStartPreparationService;
use App\Services\Quiz\Start\QuizStartSessionPayloadFactory;

$defaultExam = QuizStartInputNormalizer::normalize(['mode' => 'exam']);
if ($defaultExam['count'] !== 150 || $defaultExam['mode'] !== 'exam') {
    throw new RuntimeException('Exam mode must use the documented 150-question default.');
}
if (QuizStartInputNormalizer::normalize(['mode' => 'practice', 'count' => 150])['count'] !== 20) {
    throw new RuntimeException('Ordinary quiz limits must remain isolated from exam simulation.');
}

$allocation = RuntimeAllocationService::allocate(7, ['general' => 20, 'professional' => 40, 'major' => 40]);
if ($allocation !== ['general' => 1, 'professional' => 3, 'major' => 3]
    || array_sum($allocation) !== 7) {
    throw new RuntimeException('Largest-remainder subject allocation is not exact and deterministic.');
}

$question = static fn(string $id, string $difficulty = 'easy'): array => [
    'id' => $id,
    'subject' => 'English',
    'domain' => 'Grammar',
    'topic' => 'Agreement',
    'difficulty' => $difficulty,
    'status' => 'active',
    'question' => "Question {$id}?",
    'choices' => ['First', 'Second'],
    'answer' => 'First',
    'explanation' => 'A complete explanation.',
];
$pool = [
    $question('valid-easy'),
    $question('valid-hard', 'hard'),
    array_merge($question('malformed'), ['answer' => 'Missing']),
    $question('valid-easy'),
];
$preparation = QuizStartPreparationService::prepare([
    'exam' => 'LET',
    'subject' => 'English',
    'mode' => 'exam',
    'count' => 7,
    'difficulty' => 'mixed',
], $pool);
if (count($preparation->questions) !== 2
    || count(array_unique(array_column($preparation->questions, 'id'))) !== 2
    || $preparation->issues === []) {
    throw new RuntimeException('Sparse simulation did not exclude malformed duplicates or report shortages.');
}

$payload = QuizStartSessionPayloadFactory::create(
    $preparation->specification,
    $preparation->questions,
    $preparation->coverage,
    $preparation->issues
);
if ($payload['session_type'] !== 'exam_simulation'
    || $payload['requested_question_count'] !== 7
    || $payload['question_count'] !== 2) {
    throw new RuntimeException('Simulation session did not preserve requested/generated boundaries.');
}

$summary = QuizScoringService::calculate($preparation->questions, ['valid-easy' => 'A']);
if ($summary['total'] !== 2 || $summary['unanswered'] !== 1) {
    throw new RuntimeException('Partial exam score denominator must include unanswered generated questions.');
}

echo "[PASS] Exam simulation blueprint, sparse-pool, isolation, and scoring behavior verified.\n";
