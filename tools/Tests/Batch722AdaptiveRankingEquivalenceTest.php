<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$pool = [];
for ($index = 0; $index < 1200; $index++) {
    $topic = 'topic-' . ($index % 40);
    $pool[] = [
        'id' => 'synthetic-' . $index,
        'difficulty' => ['easy', 'medium', 'hard'][$index % 3],
        'subject' => 'English',
        'topic' => $topic,
        'taxonomy' => ['subject_id' => 'english', 'topic_id' => $topic],
    ];
}
$weakTopics = [];
for ($index = 0; $index < 40; $index++) {
    $weakTopics[] = [
        'board' => 'LET',
        'subject' => 'English',
        'topic' => 'topic-' . $index,
        'priority' => 40 - $index,
    ];
}
$exposure = [];
for ($index = 0; $index < 900; $index += 2) {
    $exposure['synthetic-' . $index] = [
        'count' => 1 + ($index % 4),
        'last_attempt_index' => $index % 5,
    ];
}
$context = ['weak_topics' => $weakTopics, 'question_exposure' => $exposure];
$request = new SelectionRequest(
    '*', null, ['easy' => 10, 'medium' => 10, 'hard' => 10], 30,
    exam: 'LET', adaptiveContext: $context
);
$selected = array_slice($pool, 0, 30);
$ranked = AdaptiveQuestionSelectionService::apply($selected, $pool, $request, $context);
$expectedIds = [
    'synthetic-960', 'synthetic-970', 'synthetic-41', 'synthetic-81', 'synthetic-91',
    'synthetic-59', 'synthetic-1002', 'synthetic-1012', 'synthetic-962', 'synthetic-3',
    'synthetic-13', 'synthetic-980', 'synthetic-924', 'synthetic-934', 'synthetic-83',
    'synthetic-45', 'synthetic-55', 'synthetic-101', 'synthetic-966', 'synthetic-976',
    'synthetic-1004', 'synthetic-87', 'synthetic-97', 'synthetic-902', 'synthetic-1008',
    'synthetic-1000', 'synthetic-5', 'synthetic-9', 'synthetic-1018', 'synthetic-23',
];
if (array_column($ranked, 'id') !== $expectedIds) {
    throw new RuntimeException('Indexed adaptive ranking changed established candidate order.');
}
if (count($ranked) !== 30 || count(array_unique($expectedIds)) !== 30) {
    throw new RuntimeException('Adaptive ranking changed the requested count or introduced duplicate candidates.');
}

echo "[PASS] Indexed adaptive ranking preserves the established ordered selection across a 1,200-candidate pool.\n";
