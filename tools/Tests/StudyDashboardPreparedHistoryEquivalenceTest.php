<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;

$attempts = [];
$now = time();
for ($index = 0; $index < 500; $index++) {
    $attempts[] = [
        'id' => 'prepared-history-' . $index,
        'session_id' => 'prepared-session-' . $index,
        'completed' => true,
        'mode' => 'practice',
        'board' => $index % 7 === 0 ? 'civil-service' : 'let',
        'subject' => $index % 7 === 0 ? 'general-knowledge' : 'English',
        'topic' => 'prepared-topic-' . ($index % 4),
        'score' => $index % 11,
        'total' => 10,
        'answered' => 10,
        'unanswered' => 0,
        'percentage' => $index * 7 % 101,
        'completed_at' => gmdate('Y-m-d\TH:i:s\Z', $now - ($index * 60)),
        'review' => [],
    ];
}

$assertEquivalent = static function (array $input, array $preferences, bool $includeReadiness, string $case): void {
    $expected = StudyDashboardService::build($input, $preferences, $includeReadiness);
    $ordered = LearningHistoryService::ordered($input);
    $actual = StudyDashboardService::buildFromOrdered($ordered, $preferences, $includeReadiness);
    if ($actual !== $expected) {
        throw new RuntimeException('Prepared dashboard output changed for ' . $case . '.');
    }
};

foreach ([0, 1, 25, 100, 500] as $count) {
    $assertEquivalent(array_slice($attempts, 0, $count), [], false, 'history count ' . $count);
}

$letAttempts = array_values(array_filter($attempts, static fn(array $attempt): bool => $attempt['board'] === 'let'));
$assertEquivalent(array_slice($letAttempts, 0, 25), ['preferred_board' => 'let'], true, 'full-scope LET readiness');
$assertEquivalent($attempts, ['preferred_board' => 'let'], true, 'mixed-board scoped readiness');

$invalidMetricHistory = [['id' => 'invalid-metric', 'score' => 1, 'total' => 0]];
$legacyRaw = StudyDashboardService::build($invalidMetricHistory, [], false);
if (($legacyRaw['progress']['totalAttempts'] ?? null) !== 1) {
    throw new RuntimeException('Raw-input dashboard no longer preserves its historical counted-attempt contract.');
}

echo "[PASS] Raw and prepared dashboard outputs match for 0, 1, 25, 100, and 500 valid attempts.\n";
echo "[PASS] Full-scope readiness reuses aggregates; mixed-board readiness preserves scoped recomputation.\n";
echo "[PASS] Raw-input attempt-total compatibility is preserved.\n";
