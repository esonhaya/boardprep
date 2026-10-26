<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\RepositoryHealth\DTO\HealthIssue;
use App\Services\RepositoryHealth\DTO\HealthReport;
use App\Services\RepositoryHealth\DTO\RepositoryStatistics;
use App\Services\RepositoryHealth\Engine\RepositoryHealthReportHydrator;

$original = new HealthReport();
$original->generatedAt = '2026-10-26T00:00:00+00:00';
$original->overallStatus = 'REVIEW';
$original->healthScore = 98.5;
$original->summary = ['issue_count' => 1, 'overall_status' => 'REVIEW'];
$original->checks = [['id' => 'synthetic-check', 'status' => 'PASS']];
$original->boardSummaries = ['let' => ['canonical' => 1]];
$original->affectedIds = ['synthetic-check' => ['q-1']];
$original->exclusions = ['q-2' => 'explicit'];
$original->actionQueue = [['code' => 'QUESTION_REUSE_REVIEW_HOLD']];
$original->statistics = new RepositoryStatistics();
$original->statistics->totalQuestions = 1;
$original->statistics->totalIssues = 1;
$original->statistics->questionsPerBoard = ['let' => 1];
$original->statistics->totals = ['canonical' => 1, 'effective_declared' => 1, 'runtime' => 1];

$issue = new HealthIssue();
$issue->validator = 'EligibilityValidator';
$issue->severity = 'warning';
$issue->priority = 'normal';
$issue->category = 'ELIGIBILITY';
$issue->code = 'QUESTION_REUSE_REVIEW_HOLD';
$issue->message = 'Synthetic isolated hold.';
$issue->recommendation = 'Inspect evidence.';
$issue->repairable = false;
$issue->entityType = 'question';
$issue->entityId = 'q-1';
$issue->metadata = ['fixture_only' => true];
$issue->status = 'REVIEW';
$issue->boardId = 'let';
$issue->evidence = ['board_id' => 'let'];
$issue->suggestedAction = 'Review source and answer independently.';
$issue->sourceCheck = 'QuestionEligibility';
$original->issues[] = $issue;

$hydrated = RepositoryHealthReportHydrator::hydrate($original->toArray());
if (!$hydrated instanceof HealthReport || $hydrated->toArray() !== $original->toArray()) {
    throw new RuntimeException('Hydrated current health snapshot did not preserve its report and issue contract.');
}
if (RepositoryHealthReportHydrator::hydrate(['statistics' => [], 'issues' => [['code' => []]]]) !== null) {
    throw new RuntimeException('Malformed report issue data was accepted for cached display.');
}

echo "[PASS] Cached repository-health report hydration preserves issue evidence and statistics and rejects malformed payloads.\n";
