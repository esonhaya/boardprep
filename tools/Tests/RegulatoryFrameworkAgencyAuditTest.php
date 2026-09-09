<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

\App\Core\Autoloader::register();

use App\Services\Shared\ContentPriorityService;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . dirname(__DIR__, 2) . '/storage');

$report = ContentPriorityService::analyzeRepository();
$topics = [];
foreach ($report['content_depth']['topics'] ?? [] as $topic) {
    if (($topic['subject_id'] ?? '') === 'cpa-regulatory-framework') {
        $topics[(string) ($topic['topic_id'] ?? '')] = $topic;
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$agency = $topics['cpa-regulatory-framework-agency'] ?? [];
$validity = $topics['cpa-regulatory-framework-validity'] ?? [];
$assert(($agency['question_ids'] ?? []) === ['1341', '1350'], 'Agency questions were not reconciled to the active topic');
$assert(($agency['content_fit_state'] ?? '') === 'VERIFIED', 'Agency content fit was not verified');
$assert(($agency['source_support_state'] ?? '') === 'ADEQUATE', 'Agency source support is not adequate');
$assert(($validity['question_ids'] ?? []) === ['1342', '1351'], 'Holder-in-due-course questions were not grouped under validity');
$assert(($validity['content_fit_state'] ?? '') === 'VERIFIED', 'Validity content fit was not verified');
$assert(!in_array('cpa|cpa-regulatory-framework|cpa-regulatory-framework-agency', array_column($report['priorities'] ?? [], 'target'), true), 'Agency remained a false singleton priority');

$semantic = [];
foreach ($report['semantic_audit']['questions'] ?? [] as $row) {
    $semantic[(string) ($row['question_id'] ?? '')] = $row;
}
$assert(($semantic['1341']['recommended_action'] ?? '') === 'NO_ACTION', 'Agency ratification row retained a repair action');
$assert(($semantic['1350']['recommended_action'] ?? '') === 'NO_ACTION', 'Agency authority row retained a repair action');
$assert(($semantic['1342']['topic_id'] ?? '') === 'cpa-regulatory-framework-validity', 'Question 1342 was not moved to validity');

$response = (new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php'))->request('GET', '/developer/content-intelligence');
$html = (string) ($response['output'] ?? '');
$assert(($response['status'] ?? 0) === 200 && ($response['exitCode'] ?? 1) === 0, 'Content Intelligence report route failed');
$assert(str_contains($html, 'Regulatory Framework Content Fit'), 'Developer report omitted Regulatory Framework fit section');
$assert(str_contains($html, 'cpa-regulatory-framework-agency') && str_contains($html, 'ADEQUATE'), 'Developer report omitted agency topic/source state');

echo "[PASS] Regulatory Framework agency fit, source foundation, priority reconciliation, and developer visibility verified.\n";
