<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Services\Shared\ContentPriorityService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$question = static function (string $id, string $subject, string $topic, array $usage = [], bool $noCard = false): array {
    return [
        'id' => $id,
        'taxonomy' => [
            'board_id' => 'alpha',
            'subject_id' => $subject,
            'topic_id' => $topic,
            'concept_id' => $topic . '-concept',
        ],
        'difficulty' => 'medium',
        'options' => ['A', 'B'],
        'answer' => 'A',
        'question' => 'Fixture question ' . $id,
        'timesUsed' => $usage['used'] ?? 0,
        'timesCorrect' => $usage['correct'] ?? 0,
        'timesIncorrect' => $usage['incorrect'] ?? 0,
        'no_card' => $noCard,
    ];
};

$questions = [
    $question('q-high', 'subject-a', 'topic-deep', ['used' => 10, 'correct' => 2, 'incorrect' => 8]),
    $question('q-low', 'subject-a', 'topic-deep', ['used' => 0]),
    $question('q-mid-1', 'subject-a', 'topic-deep', ['used' => 1, 'correct' => 1]),
    $question('q-mid-2', 'subject-a', 'topic-deep', ['used' => 1, 'correct' => 1]),
    $question('q-mid-3', 'subject-a', 'topic-deep', ['used' => 1, 'correct' => 1]),
    $question('q-shallow', 'subject-b', 'topic-shallow', ['used' => 1, 'correct' => 1], true),
];

$collections = [
    'questions' => $questions,
    'question-statistics' => [],
    'attempts' => [],
    'weakness' => [],
    'boards' => [['id' => 'alpha', 'name' => 'Alpha Board']],
    'subjects' => [
        ['id' => 'subject-a', 'name' => 'Subject A', 'board_id' => 'alpha'],
        ['id' => 'subject-b', 'name' => 'Subject B', 'board_id' => 'alpha'],
    ],
    'taxonomy/domains' => [],
    'taxonomy/topics' => [
        ['id' => 'topic-deep', 'name' => 'Deep Topic'],
        ['id' => 'topic-shallow', 'name' => 'Shallow Topic'],
    ],
    'taxonomy/concepts' => [],
    'question-eligibility' => [['id' => 'eligibility', 'exam_ids' => ['alpha'], 'question_ids' => array_column($questions, 'id')]],
    'study-materials' => [
        ['id' => 'material-a', 'title' => 'Fixture resource', 'source_ids' => ['source-a']],
        ['id' => 'material-orphan', 'title' => 'Unlinked fixture resource', 'source_ids' => ['source-a']],
    ],
    'study-question-links' => [['id' => 'link-q-high', 'question_id' => 'q-high', 'material_id' => 'material-a']],
    'sources' => [['id' => 'source-a', 'title' => 'Fixture source']],
    'blueprints' => [],
];

$integrity = ['portfolio' => ['status' => 'PASS'], 'warnings' => []];
$derive = static fn(array $item): ?array => ($item['no_card'] ?? false) ? null : ['card_key' => 'card:' . $item['id']];
$report = ContentPriorityService::analyze($collections, $integrity, $derive);
$repeat = ContentPriorityService::analyze($collections, $integrity, $derive);

$assert($report['portfolio']['question_count'] === 6, 'fixture question count was not aggregated');
$assert($report['portfolio']['eligible_question_count'] === 6, 'fixture eligibility was not aggregated');
$assert(in_array('q-low', $report['exposure']['never_seen'], true), 'zero-use question was not classified as never seen');
$assert(in_array('q-high', $report['exposure']['high_exposure'], true), 'high-exposure question was not classified');
$assert(count($report['weakness']['high_confidence_weak_areas']) > 0, 'adequate-sample weak area was not detected');
$assert(count($report['weakness']['insufficient_sample_areas']) > 0, 'small-sample areas were not labeled insufficient');
$assert(in_array('q-high', $report['weakness']['areas']['alpha|subject-a|topic-deep']['question_ids'] ?? [], true), 'weakness area did not retain exact question IDs');
$assert(in_array('alpha|subject-b|topic-shallow', array_column($report['content_depth']['shallow_topics'], 'target'), true), 'shallow topic was not detected');
$assert(!in_array('alpha|subject-a|topic-deep', array_column($report['content_depth']['shallow_topics'], 'target'), true), 'adequate topic was incorrectly flagged shallow');
$assert($report['portfolio']['flashcard_exclusion_count'] === 1, 'flashcard exclusion count was not aggregated');
$assert(in_array('material-orphan', $report['study_depth']['orphan_materials'], true), 'orphan study material was not surfaced');
$assert($report['priorities'] !== [], 'priority candidates were not produced');
$assert($report['priorities'] === $repeat['priorities'], 'priority ordering was not deterministic');
$assert(in_array('SCARCITY', $report['priorities'][0]['reason_codes'], true), 'priority reason code was not explainable');
$assert(count($report['boards']) === 1 && $report['boards']['alpha']['question_count'] === 6, 'board summary was not aggregated');
$assert($report['runtime']['n_plus_one_found'] === false, 'fixture reported an unexpected N+1 scan');

$depthFixture = $collections;
for ($index = 7; $index <= 16; $index++) {
    $depthFixture['questions'][] = $question('q-depth-' . $index, 'subject-a', 'topic-deep');
}
$depthReport = ContentPriorityService::analyze($depthFixture, $integrity, $derive);
$assert(($depthReport['study_depth']['many_questions_single_resource'][0]['code'] ?? '') === 'MANY_QUESTIONS_SINGLE_RESOURCE', 'study-resource scarcity was not detected');
$assert(in_array('alpha|subject-a|topic-deep', array_column($depthReport['content_depth']['overconcentrated_areas'], 'target'), true), 'overconcentrated topic was not detected');

$controller = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/DeveloperToolsController.php');
$view = file_get_contents(dirname(__DIR__, 2) . '/app/Views/developer/dashboard.php');
$assert($controller !== false && str_contains($controller, 'ContentPriorityService'), 'Developer Tools controller does not consume the shared service');
$assert($view !== false && str_contains($view, 'Content Investment Priorities'), 'Developer Tools view does not render priorities');

echo "[PASS] Content depth, usage signals, deterministic priorities, and Developer Tools contract verified.\n";
