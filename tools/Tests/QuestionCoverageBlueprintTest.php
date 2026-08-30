<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Shared\QuestionCoverageService;

$question = static fn(string $id, string $subject, string $topic, string $difficulty): array => [
    'id' => $id,
    'taxonomy' => [
        'board_id' => 'let',
        'subject_id' => $subject,
        'domain_id' => 'grammar',
        'topic_id' => $topic,
        'concept_id' => 'agreement',
    ],
    'difficulty' => $difficulty,
    'status' => 'approved',
    'question' => "Question {$id}?",
    'choices' => ['Yes', 'No'],
    'answer' => 'Yes',
    'explanation' => 'A complete explanation.',
];
$boards = [['id' => 'let', 'name' => 'LET']];
$subjects = [
    ['id' => 'english', 'name' => 'English'],
    ['id' => 'professional-education', 'name' => 'Professional Education'],
    ['id' => 'general-education', 'name' => 'General Education'],
];
$relations = [
    ['id' => 'let-general', 'board_id' => 'let', 'subject_id' => 'general-education', 'settings' => ['blueprint_weight' => 20]],
    ['id' => 'let-professional', 'board_id' => 'let', 'subject_id' => 'professional-education', 'settings' => ['blueprint_weight' => 40]],
    ['id' => 'let-english', 'board_id' => 'let', 'subject_id' => 'english', 'settings' => ['blueprint_weight' => 40]],
];
$domains = [['id' => 'grammar', 'name' => 'Grammar', 'subject_id' => 'english']];
$topics = [
    ['id' => 'agreement', 'name' => 'Agreement', 'domain_id' => 'grammar'],
    ['id' => 'empty-topic', 'name' => 'Empty Topic', 'domain_id' => 'grammar'],
];
$concepts = [['id' => 'agreement', 'name' => 'Agreement', 'topic_id' => 'agreement']];
$questions = [
    $question('easy', 'english', 'agreement', ' Easy '),
    $question('medium', 'english', 'agreement', 'medium'),
    $question('hard', 'english', 'agreement', 'hard'),
];

$report = QuestionCoverageService::analyze(
    $questions, $boards, $subjects, $relations, $domains, $topics, $concepts
);
if ($report['inventory']['eligible'] !== 3
    || $report['inventory']['by_difficulty'] !== ['easy' => 1, 'hard' => 1, 'medium' => 1]
    || ($report['inventory']['by_topic']['agreement'] ?? 0) !== 3
    || isset($report['inventory']['by_topic']['empty-topic'])) {
    throw new RuntimeException('complete, sparse, or empty-category inventory is inconsistent');
}
$blueprint = $report['blueprints'][0] ?? null;
if (!is_array($blueprint) || $blueprint['allocation_total'] !== 100 || !$blueprint['valid_weight_total']) {
    throw new RuntimeException('blueprint allocation totals are not feasible and internally consistent');
}
$categories = array_column($blueprint['categories'], null, 'subject');
if (($categories['english']['available'] ?? null) !== 3
    || ($categories['general-education']['available'] ?? null) !== 0
    || ($categories['professional-education']['shortage_per_100'] ?? null) !== 40) {
    throw new RuntimeException('sparse and empty blueprint categories were silently distorted');
}

$legacy = $questions[0];
$legacy['id'] = 'legacy';
$legacy['subject'] = ' English ';
$legacy['domain'] = 'Grammar';
$legacy['topic'] = 'Agreement';
$legacy['concept'] = 'Agreement';
unset($legacy['taxonomy']);
$legacyReport = QuestionCoverageService::analyze(
    [$legacy], $boards, $subjects, $relations, $domains, $topics, $concepts
);
if ($legacyReport['inventory']['eligible'] !== 1
    || $legacyReport['issues']['legacy_metadata'] !== ['legacy']) {
    throw new RuntimeException('legacy metadata is not counted with production selection parity');
}

$selected = QuestionPoolFilter::filter(
    $questions,
    new SelectionRequest('English', null, [], 3, 'Agreement')
);
if (count($selected) !== $report['inventory']['eligible']) {
    throw new RuntimeException('coverage diagnostics disagree with production selection');
}

$doctor = (new \Tools\Doctor\Project\BoardPrep\Checks\QuestionCoverageCheck())->run();
if ($doctor->title !== 'Question Coverage'
    || !in_array($doctor->status, ['PASS', 'WARNING'], true)
    || !str_contains(implode("\n", $doctor->details), 'production eligible')) {
    throw new RuntimeException('Doctor coverage boundary did not expose repository diagnostics');
}

echo "[PASS] Question coverage, taxonomy, blueprint feasibility, and selection parity verified.\n";
