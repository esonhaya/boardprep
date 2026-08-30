<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\TopicPerformanceService;

$attempts = [
    [
        'completed' => true,
        'subject' => 'Science',
        'topic' => 'Zoology',
        'score' => 1,
        'total' => 2,
        'percentage' => 50,
    ],
    [
        'completed' => true,
        'subject' => 'English',
        'topic' => 'Grammar',
        'score' => 1,
        'total' => 2,
        'percentage' => 50,
    ],
    [
        'completed' => true,
        'subject' => 'English',
        'topic' => 'Reading',
        'score' => 2,
        'total' => 2,
        'percentage' => 100,
    ],
];

$weakest = TopicPerformanceService::weakest($attempts, 3);
if (($weakest[0]['topic'] ?? '') !== 'Grammar'
    || ($weakest[1]['topic'] ?? '') !== 'Zoology'
    || ($weakest[2]['topic'] ?? '') !== 'Reading') {
    throw new RuntimeException('equal weakness priorities are not deterministic');
}

$dashboard = StudyDashboardService::build(array_reverse($attempts));
if (($dashboard['weakestTopics'][0]['topic'] ?? '') !== 'Grammar'
    || !str_contains((string) ($dashboard['recommendations'][0]['action'] ?? ''), 'topic=Grammar')
    || !str_contains((string) ($dashboard['studyPlan'][0]['action'] ?? ''), 'subject=English')) {
    throw new RuntimeException('deterministic weakness did not reach recommendation actions');
}

echo "[PASS] adaptive study priorities and actions use deterministic tie ordering.\n";
