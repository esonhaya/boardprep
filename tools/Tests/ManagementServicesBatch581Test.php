<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . dirname(__DIR__, 2) . '/storage');

use App\Services\Shared\ContentPriorityService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__, 2);
$questions = json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$eligibility = json_decode((string) file_get_contents($root . '/storage/question-eligibility.json'), true, 512, JSON_THROW_ON_ERROR);
$links = json_decode((string) file_get_contents($root . '/storage/study-question-links.json'), true, 512, JSON_THROW_ON_ERROR);
$expectedTopics = [
    1869 => 'cpa-management-services-budgeting',
    1870 => 'cpa-management-services-variance',
    1871 => 'cpa-management-services-sales-mix',
    1872 => 'cpa-management-services-capital-budgeting',
    1873 => 'cpa-management-services-sunk-cost',
    1874 => 'cpa-management-services-control',
    1875 => 'cpa-management-services-cost-behavior',
    1876 => 'cpa-management-services-relevant-costing',
    1877 => 'cpa-management-services-standard-costing',
    1878 => 'cpa-management-services-make-buy',
    1879 => 'cpa-management-services-cash-budget',
    1880 => 'cpa-management-services-npv',
];

$byId = [];
foreach ($questions as $question) {
    $byId[(int) ($question['id'] ?? 0)] = $question;
}
$eligibleIds = [];
foreach ($eligibility as $record) {
    if (in_array('cpa', $record['exam_ids'] ?? [], true)) {
        $eligibleIds = array_map('intval', $record['question_ids'] ?? []);
    }
}
$linksByQuestion = [];
foreach ($links as $link) {
    $linksByQuestion[(int) ($link['question_id'] ?? 0)][] = $link;
}

foreach ($expectedTopics as $id => $topic) {
    $question = $byId[$id] ?? null;
    $assert(is_array($question), "new question {$id} is missing");
    $assert(($question['taxonomy']['board_id'] ?? '') === 'cpa', "question {$id} is not CPA-owned");
    $assert(($question['taxonomy']['subject_id'] ?? '') === 'cpa-management-services', "question {$id} has the wrong subject");
    $assert(($question['taxonomy']['topic_id'] ?? '') === $topic, "question {$id} has the wrong topic");
    $assert(count($question['choices'] ?? []) === 4 && count(array_unique($question['choices'])) === 4, "question {$id} has duplicate choices");
    $assert(count(array_keys($question['choices'], $question['answer'], true)) === 1, "question {$id} does not have one keyed choice");
    $assert(($question['status'] ?? '') === 'active', "question {$id} is not active");
    $assert(in_array($id, $eligibleIds, true), "question {$id} is not CPA-eligible");
    $assert(count($linksByQuestion[$id] ?? []) === 1, "question {$id} does not have exactly one study link");
    $assert(in_array('openstax-principles-managerial-accounting', $question['source_ids'] ?? [], true), "question {$id} lacks technical source provenance");
}

$report = ContentPriorityService::analyzeRepository();
$assert(($report['portfolio']['question_count'] ?? 0) === 2083, 'portfolio count did not include the LET English grammar depth wave');
$assert(($report['integrity']['portfolio']['total_invalid_links'] ?? 1) === 0, 'new wave introduced invalid links');
$assert(($report['integrity']['portfolio']['total_invalid_taxonomy_refs'] ?? 1) === 0, 'new wave introduced invalid taxonomy references');
$assert(($report['integrity']['portfolio']['total_invalid_answer_keys'] ?? 1) === 0, 'new wave introduced invalid answer keys');
$assert(($report['integrity']['portfolio']['total_card_key_collisions'] ?? 1) === 0, 'new wave introduced card-key collisions');

$managementTopics = array_values(array_filter(
    $report['content_depth']['topics'] ?? [],
    static fn(array $row): bool => ($row['subject_id'] ?? '') === 'cpa-management-services'
));
foreach ($managementTopics as $topic) {
    if (str_ends_with((string) ($topic['topic_id'] ?? ''), '-core')) {
        continue;
    }
    if (($topic['question_count'] ?? 0) > 0) {
        $assert(($topic['question_count'] ?? 0) >= 2, "Management Services topic {$topic['topic_id']} remains shallow");
        $assert(($topic['source_support_state'] ?? '') === 'ADEQUATE', "Management Services topic {$topic['topic_id']} lost technical support");
    }
}

$priorityTargets = array_column($report['priorities'] ?? [], 'target');
foreach (array_values($expectedTopics) as $topic) {
    $assert(!in_array('cpa|cpa-management-services|' . $topic, $priorityTargets, true), "closed topic {$topic} remains a priority");
}

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
$simulator = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator($root . '/public/index.php');
$response = $simulator->request('GET', '/developer/content-intelligence');
$html = (string) ($response['output'] ?? '');
$assert(($response['status'] ?? 0) === 200 && ($response['exitCode'] ?? 1) === 0, 'developer content-intelligence report did not render');
$assert(str_contains($html, 'cpa-management-services-budgeting'), 'developer report omitted the updated Management Services depth/priority target');
$assert(str_contains($html, '>1869<'), 'developer report omitted the first new question ID');
$assert(str_contains($html, 'ADEQUATE'), 'developer report omitted source-support state');

echo "[PASS] Batch 581 Management Services wave, eligibility, links, cards, integrity, and priority closure verified.\n";
