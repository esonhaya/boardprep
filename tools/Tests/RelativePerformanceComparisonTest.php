<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Learning\StudyDashboardService;

$attempts = [
    ['board' => 'LET', 'let_level' => 'ELEMENTARY', 'subject' => 'English', 'topic' => 'Grammar', 'percentage' => 20],
    ['board' => 'LET', 'let_level' => 'ELEMENTARY', 'subject' => 'English', 'topic' => 'Grammar', 'percentage' => 20],
    ['board' => 'LET', 'let_level' => 'ELEMENTARY', 'subject' => 'English', 'topic' => 'Reading', 'percentage' => 100],
    ['board' => 'LET', 'let_level' => 'ELEMENTARY', 'subject' => 'Mathematics', 'topic' => 'Algebra', 'percentage' => 100],
    ['board' => 'CSE', 'civil_service_level' => 'PROFESSIONAL', 'subject' => 'General Information', 'topic' => 'Public Service', 'percentage' => 0],
];

$dashboard = StudyDashboardService::build($attempts);
$focusTopics = $dashboard['focusTopics'] ?? [];
$strongestSubjects = $dashboard['strongestSubjects'] ?? [];

$assertions = [
    'below-average topics are selected by learner performance within context' =>
        count($focusTopics) === 1
        && ($focusTopics[0]['topic'] ?? '') === 'Grammar'
        && ($focusTopics[0]['board'] ?? '') === 'LET'
        && ($focusTopics[0]['let_level'] ?? '') === 'ELEMENTARY',
    'above-average subjects exclude below-average subjects' =>
        count($strongestSubjects) === 1
        && ($strongestSubjects[0]['subject'] ?? '') === 'Mathematics'
        && ($strongestSubjects[0]['board'] ?? '') === 'LET',
    'single-topic board context does not invent a comparative weakness' =>
        !in_array('Public Service', array_column($focusTopics, 'topic'), true),
    'single-topic subject context does not invent a comparative strength' =>
        !in_array('General Information', array_column($strongestSubjects, 'subject'), true),
    'full subject and topic history remains available' =>
        count($dashboard['topics'] ?? []) === 4
        && count($dashboard['subjects'] ?? []) === 3,
    'practice recommendations remain actionable in the sparse context' =>
        !empty($dashboard['recommendations'])
        && str_starts_with((string) ($dashboard['recommendations'][0]['action'] ?? ''), '/quiz?'),
    'study plan labels relative focus separately from general recommendations' =>
        ($dashboard['studyPlan'][0]['topic'] ?? '') === 'Grammar'
        && ($dashboard['studyPlan'][0]['type'] ?? '') === 'Focus',
];

foreach ($assertions as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo '[PASS] Relative performance comparisons preserve board and exam-variant context. Assertions: ' . count($assertions) . "\n";
