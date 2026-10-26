<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Quiz\Start\QuizStartInputNormalizer;
use App\Services\Quiz\Start\QuizStartSessionPayloadFactory;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$specification = new QuizSpecification(
    board: 'LET',
    subject: 'english',
    domain: null,
    topics: [],
    concepts: [],
    difficulty: 'mixed',
    questionCount: 2,
    mode: 'practice',
    adaptive: true,
    shuffle: true,
    boardBlueprintVersion: null,
    subjectBlueprintVersion: null
);

$snapshot = static function (string $id, string $topic, string $subject = 'english'): array {
    return [
        'id' => $id,
        'topic' => $topic,
        'difficulty' => 'medium',
        'taxonomy' => ['subject_id' => $subject, 'topic_id' => $topic],
    ];
};
$attempt = static function (string $id, string $date, string $questionId, string $topic, bool $correct) use ($snapshot): array {
    return [
        'id' => $id,
        'completed' => true,
        'completed_at' => $date,
        'board' => 'LET',
        'subject' => 'English',
        'mode' => 'practice',
        'score' => $correct ? 1 : 0,
        'total' => 1,
        'percentage' => $correct ? 100 : 0,
        'review' => [[
            'answered' => true,
            'correct' => $correct,
            'question' => $snapshot($questionId, $topic),
        ]],
    ];
};

$history = [
    $attempt('older-attempt', '2026-10-01T08:00:00Z', 'q-repeated', 'weak-topic', false),
    $attempt('newer-attempt', '2026-10-02T08:00:00Z', 'q-repeated', 'weak-topic', false),
];
$context = AdaptiveQuizService::contextFromAttempts($history, $specification);
$assert(($context['weak_topics'][0]['topic'] ?? '') === 'weak-topic', 'Repeated question-level errors across attempts did not create a bounded weak-topic signal.');
$assert(($context['weak_topics'][0]['attempts'] ?? 0) === 2, 'Weak-topic evidence did not preserve its attempt count.');
$assert(($context['question_exposure']['q-repeated']['count'] ?? 0) === 2, 'Recent exposure did not count distinct completed attempts.');
$assert(($context['question_exposure']['q-repeated']['last_attempt_index'] ?? -1) === 0, 'Recent exposure did not identify the most recent attempt.');

$singleAttemptContext = AdaptiveQuizService::contextFromAttempts([$history[1]], $specification);
$assert(($singleAttemptContext['weak_topics'] ?? []) === [], 'One attempt was incorrectly treated as a demonstrated weak topic.');
$otherBoardContext = AdaptiveQuizService::contextFromAttempts($history, new QuizSpecification(
    board: 'CPA', subject: 'english', domain: null, topics: [], concepts: [], difficulty: 'mixed',
    questionCount: 2, mode: 'practice', adaptive: true, shuffle: true,
    boardBlueprintVersion: null, subjectBlueprintVersion: null
));
$assert(($otherBoardContext['weak_topics'] ?? []) === [], 'Adaptive evidence crossed board contexts.');

$question = static function (string $id, string $topic, string $difficulty, string $subject = 'english'): array {
    return [
        'id' => $id,
        'question' => 'Synthetic distinct stem ' . $id,
        'choices' => ['Choice A ' . $id, 'Choice B ' . $id],
        'answer' => 'Choice A ' . $id,
        'explanation' => 'Synthetic test explanation.',
        'difficulty' => $difficulty,
        'taxonomy' => ['subject_id' => $subject, 'topic_id' => $topic],
        'topic' => $topic,
    ];
};
$pool = [
    $question('weak-medium', 'weak-topic', 'medium'),
    $question('ordinary-medium', 'ordinary-topic', 'medium'),
    $question('weak-hard', 'weak-topic', 'hard'),
    $question('ordinary-hard', 'ordinary-topic', 'hard'),
    $question('wrong-subject-weak', 'weak-topic', 'medium', 'mathematics'),
];
$unscopedTestContext = $context;
foreach ($unscopedTestContext['weak_topics'] as &$weakTopic) {
    $weakTopic['board'] = '';
}
unset($weakTopic);
$request = new SelectionRequest(
    subject: '*',
    domain: null,
    difficultyDistribution: ['medium' => 1, 'hard' => 1],
    questionCount: 2,
    exam: null,
    adaptiveContext: $unscopedTestContext
);
$selected = BlueprintRequestExecutor::execute($pool, [$request]);
$selectedIds = array_column($selected, 'id');
$selectedDifficulties = array_count_values(array_column($selected, 'difficulty'));
$assert(count($selected) === 2 && count(array_unique($selectedIds)) === 2, 'Adaptive selection changed the requested count or introduced a duplicate.');
$assert(($selectedDifficulties['medium'] ?? 0) === 1 && ($selectedDifficulties['hard'] ?? 0) === 1, 'Adaptive selection changed the baseline difficulty allocation.');
$assert(in_array('weak-medium', $selectedIds, true) && in_array('weak-hard', $selectedIds, true), 'Adaptive priority was not propagated through blueprint request execution.');
$assert(!in_array('wrong-subject-weak', $selectedIds, true), 'Adaptive topic ranking crossed the candidate subject context.');
$selectedAgain = array_column(BlueprintRequestExecutor::execute($pool, [$request]), 'id');
$stableIds = $selectedIds;
sort($stableIds, SORT_NATURAL);
sort($selectedAgain, SORT_NATURAL);
$assert($selectedAgain === $stableIds, 'Adaptive candidate composition changed across equivalent runs with stable difficulty quotas.');

$exposureContext = [
    'weak_topics' => [],
    'question_exposure' => [
        'seen-often' => ['count' => 4, 'last_attempt_index' => 0],
        'seen-once' => ['count' => 1, 'last_attempt_index' => 2],
    ],
];
$mediumPool = [
    $question('seen-often', 'ordinary-topic', 'medium'),
    $question('seen-once', 'ordinary-topic', 'medium'),
    $question('not-seen', 'ordinary-topic', 'medium'),
];
$mediumRequest = new SelectionRequest('*', null, ['medium' => 1], 1, adaptiveContext: $exposureContext);
$rotated = AdaptiveQuestionSelectionService::apply([$mediumPool[0]], $mediumPool, $mediumRequest, $exposureContext);
$assert(($rotated[0]['id'] ?? '') === 'not-seen', 'Recent question exposure was not reduced when an unseen same-difficulty candidate existed.');

$sparse = AdaptiveQuestionSelectionService::apply(
    [$pool[1], $pool[3]],
    [$pool[1]],
    $request,
    $unscopedTestContext
);
$assert(count($sparse) === 2 && ($sparse[0]['id'] ?? '') === 'ordinary-medium', 'A sparse candidate bucket destructively shortened or rewrote the baseline selection.');

$explanation = AdaptiveQuizService::selectionExplanation($context, $selected);
$assert(is_string($explanation) && str_contains($explanation, 'lower accuracy across at least two completed'), 'The learner-facing explanation omitted the evidence basis.');
$payload = QuizStartSessionPayloadFactory::create($specification, $selected, [], [], [], $explanation);
$assert(($payload['adaptive_selection_explanation'] ?? '') === $explanation, 'Adaptive selection explanation did not reach the persisted quiz session.');
$examInput = QuizStartInputNormalizer::normalize(['exam' => 'LET', 'mode' => 'exam', 'adaptive' => '1']);
$assert(($examInput['adaptive'] ?? true) === false, 'Adaptive practice was allowed to alter exam-simulation selection.');

echo "[PASS] adaptive practice uses multi-attempt question evidence and learner-scoped recent exposure.\n";
echo "[PASS] blueprint selection propagation preserves eligibility pool, subject context, difficulty counts, and requested count.\n";
echo "[PASS] sparse fallback and the in-session evidence explanation are safe and accurate.\n";
