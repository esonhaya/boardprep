<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Services\Shared\ContentPriorityService;
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . dirname(__DIR__, 2) . '/storage');

$report = ContentPriorityService::analyzeRepository();
$topics = [];
foreach ($report['content_depth']['topics'] ?? [] as $topic) {
    $topics[(string) ($topic['topic_id'] ?? '')] = $topic;
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$activity = $topics['cpa-management-services-activity-based'] ?? [];
$performance = $topics['cpa-management-services-performance'] ?? [];
$assert(($activity['question_ids'] ?? []) === ['1308', '1312'], 'ABC questions were not reconciled to the active activity-based topic');
$assert(($activity['content_fit_state'] ?? '') === 'VERIFIED', 'ABC content fit was not verified');
$assert(($activity['source_support_state'] ?? '') === 'ADEQUATE', 'ABC technical source support is not adequate');
$assert(($performance['question_ids'] ?? []) === ['1316', '1317'], 'Performance questions were not reconciled');

$question = null;
foreach (json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR) as $candidate) {
    if ((string) ($candidate['id'] ?? '') === '1317') {
        $question = $candidate;
        break;
    }
}
$assert(($question['taxonomy']['topic_id'] ?? '') === 'cpa-management-services-performance', 'question 1317 remains under activity-based taxonomy');
$assert(!in_array('cpa|cpa-management-services|cpa-management-services-activity-based', array_column($report['priorities'] ?? [], 'target'), true), 'activity-based remained an authoring priority after reconciliation');
$semanticQuestion = array_values(array_filter(
    $report['semantic_audit']['management_services'] ?? [],
    static fn(array $row): bool => ($row['question_id'] ?? '') === '1317'
))[0] ?? [];
$assert(($semanticQuestion['topic_id'] ?? '') === 'cpa-management-services-performance', 'question 1317 semantic audit omitted its corrected topic');
$assert(($semanticQuestion['content_fit_state'] ?? '') === 'VERIFIED', 'question 1317 semantic fit was not surfaced as verified');
$assert(($semanticQuestion['source_support_state'] ?? '') === 'ADEQUATE', 'question 1317 source support was not surfaced as adequate');
$assert(($semanticQuestion['recommended_action'] ?? '') === 'NO_ACTION', 'question 1317 retained an unnecessary repair action');

$fitWarnings = array_filter($report['warnings']['semantic_content_fit'] ?? [], static fn(array $warning): bool => ($warning['code'] ?? '') === 'REVIEW_TAXONOMY_CONTENT_FIT');
$assert($fitWarnings === [], 'known Management Services content-fit mismatches remain');
$assert(($report['integrity']['portfolio']['total_invalid_links'] ?? 1) === 0, 'Management Services link repair introduced invalid links');
$assert(($report['integrity']['portfolio']['total_duplicate_links'] ?? 1) === 0, 'Management Services link repair introduced duplicate links');

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
$simulator = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$reportResponse = $simulator->request('GET', '/developer/content-intelligence');
$assert(($reportResponse['status'] ?? 0) === 200, 'Content Intelligence report route did not render after semantic audit integration');
$reportHtml = (string) ($reportResponse['output'] ?? '');
$assert(str_contains($reportHtml, 'Management Services Content Fit'), 'developer report omitted Management Services fit section');
$assert(str_contains($reportHtml, 'cpa-management-services-performance'), 'developer report omitted question 1317 current topic');
$assert(str_contains($reportHtml, 'REVIEW_TAXONOMY_CONTENT_FIT') || str_contains($reportHtml, 'NO_ACTION'), 'developer report omitted semantic recommended action state');

echo "[PASS] Management Services topic fit, source support, links, and priority reconciliation verified.\n";
