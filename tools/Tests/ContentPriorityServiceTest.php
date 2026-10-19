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

$question = static function (string $id, string $subject, string $topic, array $usage = [], bool $noCard = false, string $domain = 'domain-a'): array {
    return [
        'id' => $id,
        'taxonomy' => [
            'board_id' => 'alpha',
            'subject_id' => $subject,
            'domain_id' => $domain,
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
    'blueprints' => [[
        'id' => 'blueprint-alpha',
        'board_id' => 'alpha',
        'sections' => [
            ['id' => 'section-a', 'domain_id' => 'domain-a', 'target_percent' => 80],
            ['id' => 'section-b', 'domain_id' => 'domain-b', 'target_percent' => 20],
        ],
    ]],
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
$blueprintPriority = array_values(array_filter(
    $report['priorities'],
    static fn(array $priority): bool => ($priority['target'] ?? '') === 'alpha' && ($priority['level'] ?? '') === 'board'
))[0] ?? null;
$assert(is_array($blueprintPriority), 'domain-based blueprint priority was not produced');
$assert(($blueprintPriority['priority'] ?? null) === 40.0, 'domain-based blueprint gap was calculated from the wrong taxonomy level');
$assert(in_array('BLUEPRINT_SCARCITY', $blueprintPriority['reason_codes'] ?? [], true), 'blueprint priority did not identify its reason');

$sectionIdCollections = $collections;
$sectionIdCollections['blueprints'] = [[
    'id' => 'blueprint-section-ids',
    'board_id' => 'alpha',
    'sections' => [
        ['id' => 'domain-a', 'target_percent' => 80],
        ['id' => 'domain-b', 'target_percent' => 20],
    ],
]];
$sectionIdReport = ContentPriorityService::analyze($sectionIdCollections, $integrity, $derive);
$sectionIdPriority = array_values(array_filter(
    $sectionIdReport['priorities'],
    static fn(array $priority): bool => ($priority['target'] ?? '') === 'alpha' && ($priority['level'] ?? '') === 'board'
))[0] ?? null;
$assert(is_array($sectionIdPriority), 'domain section ID blueprint priority was not produced');
$assert(($sectionIdPriority['priority'] ?? null) === 40.0, 'domain section IDs were not matched to their board domain counts');
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

// Developer reporting routes are intentionally unavailable in production.
$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
$simulator = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$reportResponse = $simulator->request('GET', '/developer/content-intelligence');
$assert(($reportResponse['status'] ?? 0) === 200, sprintf(
    'Content Intelligence report route did not render: HTTP %d, process exit %d, %.3fs; stderr: %s',
    (int) ($reportResponse['status'] ?? 0),
    (int) ($reportResponse['exitCode'] ?? 1),
    (float) ($reportResponse['duration'] ?? 0),
    substr(trim((string) ($reportResponse['stderr'] ?? '')), -1200)
));
$assert(($reportResponse['exitCode'] ?? 1) === 0, 'Content Intelligence report route exited with an error');
$assert(str_contains((string) ($reportResponse['output'] ?? ''), 'Content Intelligence'), 'Content Intelligence report title was not rendered');
$assert(str_contains((string) ($reportResponse['output'] ?? ''), 'Portfolio QA'), 'Content Intelligence report omitted the shared QA queue');
$assert(str_contains((string) ($reportResponse['output'] ?? ''), 'Content Priorities'), 'Content Intelligence priority section was not rendered');
$assert(str_contains((string) ($reportResponse['output'] ?? ''), 'psychometrician'), 'Content Intelligence report omitted a registered board');
$assert(str_contains((string) ($reportResponse['output'] ?? ''), 'PASS_WITH_JUSTIFIED_EXCLUSIONS'), 'Content Intelligence report omitted the integrity status');
$reportHtml = (string) ($reportResponse['output'] ?? '');
$boardReadinessStart = strpos($reportHtml, '<section aria-labelledby="board-depth-heading">');
$boardReadinessEnd = $boardReadinessStart === false ? false : strpos($reportHtml, '</tbody>', $boardReadinessStart);
$boardReadinessHtml = $boardReadinessStart === false || $boardReadinessEnd === false
    ? ''
    : substr($reportHtml, $boardReadinessStart, $boardReadinessEnd - $boardReadinessStart);
$assert($boardReadinessHtml !== '', 'Content Intelligence report omitted the board readiness matrix');
$assert(!str_contains($boardReadinessHtml, '>UNKNOWN<'), 'Content Intelligence report failed to map board integrity results');
$assert(str_contains($reportHtml, '<td>UNKNOWN</td>'), 'Content Intelligence report hid unresolved semantic audit states');

echo "[PASS] Content depth, usage signals, deterministic priorities, and Developer Tools contract verified.\n";
