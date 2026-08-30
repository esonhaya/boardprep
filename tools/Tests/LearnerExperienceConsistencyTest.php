<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\LearningProgressService;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyActionService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\TopicPerformanceService;

$attempts = [
    ['completed' => false, 'percentage' => 99],
    ['completed' => true, 'subject' => 'English', 'topic' => 'Grammar', 'score' => 0, 'total' => 2, 'date' => '2026-08-28'],
    ['subject' => 'English', 'topic' => 'Grammar', 'percentage' => 80, 'completed_at' => '2026-08-29'],
    ['subject' => 'English', 'topic' => 'Reading', 'percentage' => 40, 'completed_at' => '2026-08-30'],
    ['percentage' => 'bad'],
    'malformed',
];

$progress = LearningProgressService::build($attempts);
$dashboard = StudyDashboardService::build($attempts);
if ($progress['completedAttempts'] !== 3 || $progress['averageScore'] !== 40
    || $dashboard['progress'] !== $progress) {
    throw new RuntimeException('learner aggregates do not use one completed-attempt population');
}

$topics = TopicPerformanceService::summarize($attempts);
$grammar = array_values(array_filter($topics, static fn(array $row): bool => $row['topic'] === 'Grammar'))[0] ?? [];
if (($grammar['attempts'] ?? 0) !== 2 || ($grammar['average'] ?? -1) !== 40 || ($grammar['best'] ?? -1) !== 80) {
    throw new RuntimeException('repeated-topic aggregate is inconsistent');
}

foreach (array_merge($dashboard['recommendations'], $dashboard['studyPlan']) as $item) {
    parse_str((string) parse_url((string) ($item['action'] ?? ''), PHP_URL_QUERY), $query);
    foreach (['subject', 'topic', 'mode', 'difficulty', 'count'] as $field) {
        if (!array_key_exists($field, $query)) {
            throw new RuntimeException("learner quiz action is missing canonical {$field}");
        }
    }
}

$empty = StudyDashboardService::build([]);
parse_str((string) parse_url($empty['studyPlan'][0]['action'], PHP_URL_QUERY), $emptyQuery);
if (($emptyQuery['topic'] ?? null) !== 'General' || !StudyActionService::isValid(
    StudyActionService::create(['topic' => 'General'])
)) {
    throw new RuntimeException('new-learner action is not canonical and launchable');
}

echo "[PASS] learner metrics cover malformed, incomplete, repeated-topic, and empty states.\n";
echo "[PASS] every recommendation and study-plan action carries canonical quiz context.\n";

$tied = LearningHistoryService::ordered([
    ['id' => 'older', 'percentage' => 10, 'completed_at' => '2026-08-30T10:00:00+00:00'],
    ['id' => 'newer', 'percentage' => 90, 'completed_at' => '2026-08-30T10:00:00+00:00'],
]);
if (($tied[0]['id'] ?? '') !== 'newer') {
    throw new RuntimeException('equal-time attempts are not deterministically newest-first');
}
