<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$board = 'LET';
$snapshot = static function (string $id, string $topic, string $subject = 'english', string $difficulty = 'medium', string $questionBoard = 'let'): array {
    return [
        'id' => $id,
        'question' => 'Synthetic question ' . $id,
        'choices' => ['Choice A', 'Choice B'],
        'answer' => 'Choice A',
        'explanation' => 'Synthetic evaluation only.',
        'difficulty' => $difficulty,
        'subject' => $subject,
        'topic' => $topic,
        'board' => $questionBoard,
        'taxonomy' => ['board_id' => $questionBoard, 'subject_id' => $subject, 'topic_id' => $topic],
    ];
};
$attempt = static function (string $id, int $day, array $items, string $attemptBoard = 'LET', string $attemptSubject = '*') use ($snapshot): array {
    $review = [];
    $correct = 0;
    foreach ($items as $item) {
        $question = $snapshot(
            (string) $item[0],
            (string) $item[1],
            (string) ($item[2] ?? 'english'),
            (string) ($item[3] ?? 'medium'),
            (string) (isset($item[5]) && is_string($item[5]) ? $item[5] : strtolower($attemptBoard))
        );
        $isCorrect = (bool) ($item[4] ?? false);
        $correct += $isCorrect ? 1 : 0;
        $review[] = ['answered' => true, 'correct' => $isCorrect, 'question' => $question];
    }
    $total = count($review);
    return [
        'id' => $id,
        'session_id' => 'session-' . $id,
        'completed' => true,
        'completed_at' => sprintf('2026-10-%02dT12:00:00Z', max(1, min(27, $day))),
        'board' => $attemptBoard,
        'subject' => $attemptSubject,
        'mode' => 'practice',
        'score' => $correct,
        'total' => $total,
        'answered' => $total,
        'percentage' => $total > 0 ? (int) round($correct * 100 / $total) : 0,
        'review' => $review,
    ];
};
$specification = static function (string $exam = 'LET', string $subject = '*'): QuizSpecification {
    return new QuizSpecification(
        board: $exam, subject: $subject, domain: null, topics: [], concepts: [], difficulty: 'mixed',
        questionCount: 5, mode: 'practice', adaptive: true, shuffle: true,
        boardBlueprintVersion: null, subjectBlueprintVersion: null
    );
};
$pool = [];
foreach (['weak', 'strong', 'broad', 'other'] as $topic) {
    for ($n = 1; $n <= 8; $n++) {
        $pool[] = $snapshot($topic . '-' . $n, $topic, 'english', $n % 3 === 0 ? 'hard' : 'medium');
    }
}
$pool[] = $snapshot('math-1', 'math-weak', 'mathematics');
$pool[] = $snapshot('math-2', 'math-weak', 'mathematics');

$requestFor = static function (array $context, int $count = 1, string $subject = '*'): SelectionRequest {
    return new SelectionRequest(
        subject: $subject, domain: null, difficultyDistribution: ['medium' => $count],
        questionCount: $count, exam: 'LET', adaptiveContext: $context
    );
};
$evaluate = static function (string $name, array $history, array $candidatePool, int $count = 1, string $subject = '*', string $attemptBoard = 'LET') use ($specification, $requestFor): array {
    $context = AdaptiveQuizService::contextFromAttempts($history, $specification($attemptBoard, $subject));
    $selectedBaseline = array_slice(array_values(array_filter(
        $candidatePool,
        static fn(array $question): bool => strtolower((string) ($question['difficulty'] ?? '')) === 'medium'
    )), 0, $count);
    $request = $requestFor($context, $count, $subject);
    $selected = AdaptiveQuestionSelectionService::apply($selectedBaseline, $candidatePool, $request, $context);
    $selectedIds = array_values(array_map(static fn(array $question): string => (string) $question['id'], $selected));
    return [
        'name' => $name,
        'history_attempts' => count($history),
        'weak_topics' => array_map(static fn(array $row): array => [
            'topic' => $row['topic'], 'subject' => $row['subject'], 'overall_accuracy' => $row['average'],
            'recent_accuracy' => $row['recent_average'], 'priority' => $row['priority'],
            'priority_basis' => $row['priority_basis'],
        ], $context['weak_topics']),
        'selected_ids' => $selectedIds,
        'selected_topics' => array_values(array_map(static function (array $question): string {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            return (string) ($taxonomy['topic_id'] ?? '');
        }, $selected)),
        'question_exposure_entries' => count($context['question_exposure']),
        'selection_count' => count($selected),
        'unique_selection_count' => count(array_unique($selectedIds)),
    ];
};
$profiles = [];
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$cold = $evaluate('new_learner_cold_start', [], $pool, 1);
$assert($cold['weak_topics'] === [] && $cold['selected_ids'] === ['weak-1'], 'Cold start inferred a weakness or changed the deterministic baseline without evidence.');
$profiles[] = $cold;

$one = $evaluate('single_completed_attempt', [$attempt('single', 1, [['weak-1', 'weak', 'english', 'medium', false]])], $pool, 1);
$assert($one['weak_topics'] === [], 'One completed attempt was treated as reliable weakness evidence.');
$profiles[] = $one;

$repeatedHistory = [
    $attempt('repeat-old', 1, [['weak-1', 'weak', 'english', 'medium', false]]),
    $attempt('repeat-new', 2, [['weak-1', 'weak', 'english', 'medium', false]]),
];
$repeated = $evaluate('repeated_mistakes', $repeatedHistory, $pool, 1);
$assert(($repeated['weak_topics'][0]['topic'] ?? '') === 'weak'
    && ($repeated['selected_topics'][0] ?? '') === 'weak', 'Repeated-mistake topic was not prioritized over unrelated eligible candidates.');
$profiles[] = $repeated;

$weakSubject = $evaluate('weak_subject', [
    $attempt('subject-old', 1, [['weak-1', 'weak', 'english', 'medium', false]]),
    $attempt('subject-new', 2, [['weak-1', 'weak', 'english', 'medium', false]]),
], $pool, 1, 'english');
$assert(($weakSubject['selected_topics'][0] ?? '') === 'weak', 'Weak subject practice did not select its measured topic.');
$profiles[] = $weakSubject;

$multiTopicHistory = [
    $attempt('multi-a-old', 1, [['weak-1', 'weak', 'english', 'medium', false], ['strong-1', 'strong', 'english', 'medium', false]]),
    $attempt('multi-a-new', 2, [['weak-1', 'weak', 'english', 'medium', false], ['strong-1', 'strong', 'english', 'medium', false]]),
];
$multiple = $evaluate('multiple_weak_topics_diversity', $multiTopicHistory, $pool, 4);
$assert(in_array('weak', $multiple['selected_topics'], true) && in_array('strong', $multiple['selected_topics'], true), 'Selection concentrated on one equally prioritized topic.');
$profiles[] = $multiple;

$high = $evaluate('high_performing_learner', [
    $attempt('high-old', 1, [['strong-1', 'strong', 'english', 'medium', true]]),
    $attempt('high-new', 2, [['strong-2', 'strong', 'english', 'medium', true]]),
], $pool, 1);
$assert($high['weak_topics'] === [], 'High-performing evidence was classified as a weak topic.');
$profiles[] = $high;

$unevenHistory = [
    $attempt('uneven-english-a', 1, [['weak-1', 'weak', 'english', 'medium', false]]),
    $attempt('uneven-english-b', 2, [['weak-1', 'weak', 'english', 'medium', false]]),
    $attempt('uneven-math-a', 3, [['math-1', 'math-weak', 'mathematics', 'medium', false]]),
    $attempt('uneven-math-b', 4, [['math-1', 'math-weak', 'mathematics', 'medium', false]]),
];
$uneven = $evaluate('uneven_subject_coverage', $unevenHistory, $pool, 1, 'english');
$assert(($uneven['weak_topics'][0]['subject'] ?? '') === 'english', 'Weakness evidence crossed the requested subject scope.');
$profiles[] = $uneven;

$exposureHistory = [$attempt('exposed', 1, [['broad-1', 'broad', 'english', 'medium', true]])];
$exposureContext = AdaptiveQuizService::contextFromAttempts($exposureHistory, $specification());
$exposurePool = [$snapshot('broad-1', 'broad'), $snapshot('broad-2', 'broad'), $snapshot('broad-3', 'broad')];
$exposure = $evaluate('repeated_question_exposure', $exposureHistory, $exposurePool, 1);
$assert(($exposure['selected_ids'][0] ?? '') !== 'broad-1', 'A recently seen question was selected while unseen same-difficulty alternatives existed.');
$profiles[] = $exposure;

$staleHistory = [];
for ($day = 1; $day <= 8; $day++) {
    $staleHistory[] = $attempt('stale-' . $day, $day, [['stale-q', 'stale', 'english', 'medium', false]]);
}
$staleHistory[] = $attempt('stale-correct-1', 20, [['stale-q2', 'stale', 'english', 'medium', true]]);
$staleHistory[] = $attempt('stale-correct-2', 21, [['stale-q3', 'stale', 'english', 'medium', true]]);
for ($day = 22; $day <= 26; $day++) {
    $staleHistory[] = $attempt('persistent-' . $day, $day, [['persistent-q', 'persistent', 'english', 'medium', false]]);
}
$stale = $evaluate('stale_history_recency_weight', $staleHistory, [
    $snapshot('stale-candidate', 'stale'), $snapshot('persistent-candidate', 'persistent'),
], 1);
$priorityByTopic = [];
foreach ($stale['weak_topics'] as $row) $priorityByTopic[$row['topic']] = $row['priority'];
$assert(($priorityByTopic['persistent'] ?? 0) > ($priorityByTopic['stale'] ?? PHP_INT_MAX), 'Recent recovery did not reduce a stale weakness signal relative to a current repeated weakness.');
$profiles[] = $stale;

$smallPoolBase = [$snapshot('small-base-1', 'other'), $snapshot('small-base-2', 'other')];
$smallContext = ['weak_topics' => [['topic' => 'weak', 'board' => 'LET', 'subject' => 'english', 'average' => 0, 'priority' => 100]], 'question_exposure' => []];
$smallRequest = $requestFor($smallContext, 2);
$small = AdaptiveQuestionSelectionService::apply($smallPoolBase, [$snapshot('small-only', 'weak')], $smallRequest, $smallContext);
$assert(array_column($small, 'id') === ['small-base-1', 'small-base-2'], 'Small-pool fallback destructively changed the selected count or baseline.');
$profiles[] = ['name' => 'very_small_eligible_pool', 'selected_ids' => array_column($small, 'id'), 'selection_count' => count($small), 'unique_selection_count' => count(array_unique(array_column($small, 'id')))];

$ineligibleHistory = [$attempt('removed-history', 1, [['removed-q', 'weak', 'english', 'medium', false]])];
$ineligibleContext = AdaptiveQuizService::contextFromAttempts($ineligibleHistory, $specification());
$remainingPool = [$snapshot('eligible-q1', 'weak'), $snapshot('eligible-q2', 'broad')];
$ineligibleRequest = $requestFor($ineligibleContext, 2);
$remaining = AdaptiveQuestionSelectionService::apply(array_slice($remainingPool, 0, 2), $remainingPool, $ineligibleRequest, $ineligibleContext);
$assert(!in_array('removed-q', array_column($remaining, 'id'), true)
    && count(array_unique(array_column($remaining, 'id'))) === count($remaining), 'Removed or ineligible historical questions re-entered the eligible selection.');
$profiles[] = ['name' => 'historical_questions_now_ineligible', 'selected_ids' => array_column($remaining, 'id'), 'selection_count' => count($remaining), 'unique_selection_count' => count(array_unique(array_column($remaining, 'id')))];

$crossBoardHistory = [
    $attempt('cross-a', 1, [['shared-canonical', 'weak', 'english', 'medium', false]], 'CSE'),
    $attempt('cross-b', 2, [['shared-canonical', 'weak', 'english', 'medium', false]], 'CSE'),
];
$crossContext = AdaptiveQuizService::contextFromAttempts($crossBoardHistory, $specification('LET'));
$crossPool = [$snapshot('shared-canonical', 'weak'), $snapshot('unseen-shared-board', 'broad')];
$crossRequest = $requestFor($crossContext, 1);
$cross = AdaptiveQuestionSelectionService::apply([$crossPool[0]], $crossPool, $crossRequest, $crossContext);
$assert(($crossContext['weak_topics'] ?? []) === [], 'Weakness evidence crossed an examination-board boundary.');
$assert(($cross[0]['id'] ?? '') === 'unseen-shared-board', 'Shared canonical exposure was not considered across board histories.');
$profiles[] = ['name' => 'cross_board_question_exposure', 'weak_topics' => [], 'selected_ids' => array_column($cross, 'id'), 'selection_count' => count($cross), 'unique_selection_count' => count(array_unique(array_column($cross, 'id')))];

// A repeated canonical ID in a malformed input pool must not create duplicate session items.
$duplicateContext = ['weak_topics' => [], 'question_exposure' => ['dup' => ['count' => 2, 'last_attempt_index' => 0]]];
$duplicateQuestion = $snapshot('dup', 'broad');
$duplicatePool = [$duplicateQuestion, $duplicateQuestion, $snapshot('unique', 'broad')];
$duplicate = AdaptiveQuestionSelectionService::apply(
    [$duplicateQuestion, $duplicatePool[2]], $duplicatePool, $requestFor($duplicateContext, 2), $duplicateContext
);
$assert(count(array_unique(array_column($duplicate, 'id'))) === count($duplicate), 'Duplicate canonical IDs survived adaptive ranking.');

// Controlled ranking benchmark: prebuilt synthetic pool/history, one warm-up, seven measured ranking passes.
$benchmarkPool = [];
for ($index = 0; $index < 1200; $index++) {
    $benchmarkPool[] = $snapshot(
        'bench-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'topic-' . ($index % 20),
        'english',
        $index % 3 === 0 ? 'hard' : 'medium'
    );
}
$benchmarkHistory = [];
for ($attemptIndex = 0; $attemptIndex < 30; $attemptIndex++) {
    $items = [];
    for ($q = 0; $q < 20; $q++) {
        $candidateId = 'bench-' . str_pad((string) (($attemptIndex * 20 + $q) % 1200), 4, '0', STR_PAD_LEFT);
        $items[] = [$candidateId, 'topic-' . (($attemptIndex * 20 + $q) % 20), 'english', $q % 3 === 0 ? 'hard' : 'medium', (($attemptIndex + $q) % 4) !== 0];
    }
    $benchmarkHistory[] = $attempt('bench-history-' . $attemptIndex, ($attemptIndex % 27) + 1, $items);
}
$benchmarkContext = AdaptiveQuizService::contextFromAttempts($benchmarkHistory, $specification());
$benchmarkSelected = array_slice(array_values(array_filter($benchmarkPool, static fn(array $q): bool => $q['difficulty'] === 'medium')), 0, 20);
$benchmarkRequest = $requestFor($benchmarkContext, count($benchmarkSelected));
AdaptiveQuestionSelectionService::apply($benchmarkSelected, $benchmarkPool, $benchmarkRequest, $benchmarkContext);
$timings = [];
for ($run = 0; $run < 7; $run++) {
    $started = microtime(true);
    $ranked = AdaptiveQuestionSelectionService::apply($benchmarkSelected, $benchmarkPool, $benchmarkRequest, $benchmarkContext);
    $timings[] = round((microtime(true) - $started) * 1000, 3);
}
$sortedTimes = $timings;
sort($sortedTimes, SORT_NUMERIC);
$assert(count($ranked) === count($benchmarkSelected)
    && count(array_unique(array_column($ranked, 'id'))) === count($ranked), 'Benchmark ranking lost requested count or introduced duplicates.');
$assert(array_column($ranked, 'id') === array_column(AdaptiveQuestionSelectionService::apply($benchmarkSelected, $benchmarkPool, $benchmarkRequest, $benchmarkContext), 'id'), 'Equivalent adaptive ranking was not deterministic.');

$report = [
    'report' => 'BATCH_721_ADAPTIVE_SELECTION_EVALUATION',
    'result' => 'PASS',
    'synthetic_profiles' => count($profiles),
    'profiles' => $profiles,
    'ranking_policy' => $benchmarkContext['ranking_policy'] ?? [],
    'benchmark' => [
        'candidate_count' => count($benchmarkPool),
        'history_attempt_count' => count($benchmarkHistory),
        'history_answer_events' => array_sum(array_map(static fn(array $row): int => count($row['review']), $benchmarkHistory)),
        'selected_count' => count($ranked),
        'warmups' => 1,
        'measured_runs' => count($timings),
        'timings_ms' => $timings,
        'median_ms' => $sortedTimes[intdiv(count($sortedTimes), 2)],
        'minimum_ms' => min($timings),
        'maximum_ms' => max($timings),
        'php_peak_memory_bytes' => memory_get_peak_usage(true),
        'storage_reads' => 0,
        'fixture_mutations' => 0,
    ],
    'assertions' => [
        'cold_start_no_weakness_inference' => true,
        'single_attempt_sample_gate' => true,
        'repeated_mistake_topic_priority' => true,
        'subject_scope' => true,
        'multi_topic_diversity' => true,
        'high_performance_not_weak' => true,
        'recency_weight_reduces_stale_signal' => true,
        'small_pool_fallback' => true,
        'ineligible_history_not_reintroduced' => true,
        'cross_board_weakness_isolation_and_exposure' => true,
        'duplicate_prevention' => true,
        'deterministic_ranking' => true,
    ],
    'limits' => [
        'synthetic_fixtures_do_not_establish_learning_outcomes',
        'candidate pool is synthetic and bypasses the production eligibility filter; eligibility remains enforced by the upstream selector and separately tested production journey',
        'no official blueprint or mastery claim is derived',
    ],
];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
