<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Learning\SmartStudyHubService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$empty = SmartStudyHubService::build([
    'status' => 'INSUFFICIENT_PRACTICE_DATA',
    'recommendations' => [[
        'title' => 'Choose a board',
        'reason' => 'Board-specific practice starts after you select an examination.',
        'label' => 'Explore examinations',
        'action' => '/exams',
    ]],
], ['state' => 'NO_HISTORY', 'has_mistake_or_skipped_review' => false]);
$assert(($empty['learner_state'] ?? '') === 'NO_HISTORY', 'Empty learner was assigned returning history.');
$assert(count($empty['actions'] ?? []) === 3, 'New learner should receive three bounded starting actions.');
$assert(($empty['actions'][0]['href'] ?? '') === '/exams'
    && ($empty['actions'][0]['availability_state'] ?? '') === 'SETUP_REQUIRED',
    'First-use setup action is missing or mislabeled.');

$returning = SmartStudyHubService::build([
    'status' => 'PRACTICE_DATA_AVAILABLE',
    'board' => 'civil-service',
    'variant_label' => 'Professional',
    'recommendations' => [
        [
            'title' => 'Review recorded mistakes',
            'reason' => 'Historical errors remain available.',
            'label' => 'Open Mistake Notebook',
            'action' => '/mistake-notebook',
        ],
        [
            'title' => 'Practice General Information',
            'reason' => 'This configured subject has eligible questions.',
            'label' => 'Start practice',
            'action' => '/quiz?action=start&subject=general-knowledge&topic=General&mode=practice&count=5&difficulty=mixed&exam=civil-service&civil_service_level=PROFESSIONAL',
        ],
        [
            'title' => 'Browse lessons',
            'reason' => 'Published material is available.',
            'label' => 'Open Study Library',
            'action' => '/study-library',
        ],
    ],
], ['state' => 'RETURNING_LEARNER', 'has_mistake_or_skipped_review' => true]);
$actions = $returning['actions'] ?? [];
$assert(count($actions) === 3, 'Returning learner actions exceeded or fell below the prioritization cap.');
$assert(($actions[0]['href'] ?? '') === '/mistake-notebook', 'Saved mistake history did not receive priority.');
$assert(($actions[1]['availability_state'] ?? '') === 'READINESS_ELIGIBLE_ROUTE'
    && str_contains((string) ($actions[1]['href'] ?? ''), 'exam=civil-service')
    && str_contains((string) ($actions[1]['href'] ?? ''), 'civil_service_level=PROFESSIONAL')
    && ($actions[1]['board'] ?? '') === 'civil-service'
    && ($actions[1]['variant'] ?? '') === 'Professional',
    'Board/variant-specific readiness action lost its selected context.');

$variantRequired = SmartStudyHubService::build([
    'status' => 'VARIANT_REQUIRED',
    'recommendations' => [[
        'title' => 'Choose your examination variant',
        'reason' => 'Select the applicable level and specialization before checking practice pools.',
        'label' => 'Choose a variant',
        'action' => '/quiz',
    ]],
], ['state' => 'NO_HISTORY', 'has_mistake_or_skipped_review' => false]);
$assert(($variantRequired['actions'][0]['availability_state'] ?? '') === 'SETUP_REQUIRED'
    && ($variantRequired['actions'][0]['type'] ?? '') === 'exam_setup',
    'A required variant was presented as available practice.');

$unavailable = SmartStudyHubService::build([
    'status' => 'PRACTICE_DATA_AVAILABLE',
    'recommendations' => [[
        'title' => 'Browse published study materials',
        'reason' => 'No eligible question pool is available for this context.',
        'label' => 'Open Study Library',
        'action' => '/study-library',
    ]],
], ['state' => 'RETURNING_LEARNER', 'has_mistake_or_skipped_review' => false]);
$assert(($unavailable['actions'][0]['href'] ?? '') === '/study-library'
    && !in_array('READINESS_ELIGIBLE_ROUTE', array_column($unavailable['actions'], 'availability_state'), true),
    'Unavailable pool fallback fabricated a practice action.');

$hostile = SmartStudyHubService::build([
    'status' => 'PRACTICE_DATA_AVAILABLE',
    'recommendations' => [
        ['title' => 'External', 'reason' => 'bad', 'label' => 'bad', 'action' => 'https://example.invalid/'],
        ['title' => 'Malformed', 'reason' => 'bad', 'label' => 'bad', 'action' => '/quiz?action=delete'],
        ['title' => 'Protocol relative', 'reason' => 'bad', 'label' => 'bad', 'action' => '//example.invalid/'],
        ['title' => 'Practice', 'reason' => 'A pool is eligible.', 'label' => 'Start practice', 'action' => '/quiz?action=start&subject=English&topic=General&mode=practice&count=5'],
        ['title' => 'Duplicate', 'reason' => 'duplicate', 'label' => 'again', 'action' => '/quiz?action=start&subject=English&topic=General&mode=practice&count=5'],
    ],
], ['state' => 'RETURNING_LEARNER', 'has_mistake_or_skipped_review' => false]);
$assert(count($hostile['actions']) === 3
    && count(array_unique(array_column($hostile['actions'], 'href'))) === count($hostile['actions'])
    && !in_array('https://example.invalid/', array_column($hostile['actions'], 'href'), true)
    && !in_array('//example.invalid/', array_column($hostile['actions'], 'href'), true),
    'Unsafe or duplicate recommendation destinations were rendered.');

echo "[PASS] Smart Study Hub prioritizes saved evidence, preserves board variants, validates routes, and caps actions at three.\n";
