<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\DTO;

class HealthReport
{
    public array $issues = [];

    public RepositoryStatistics $statistics;

    public float $healthScore = 100;

    public string $overallStatus = 'PASS';

    public array $summary = [];

    public array $checks = [];

    public array $boardSummaries = [];

    public array $exclusions = [];

    public array $affectedIds = [];

    public array $actionQueue = [];

    public string $generatedAt = '';

    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'overall_status' => $this->overallStatus,
            'summary' => $this->summary,
            'checks' => $this->checks,
            'issues' => array_map(
                static fn(HealthIssue $issue): array => $issue->toArray(),
                $this->issues
            ),
            'board_summaries' => $this->boardSummaries,
            'affected_ids' => $this->affectedIds,
            'exclusions' => $this->exclusions,
            'action_queue' => $this->actionQueue,
            'health_score' => $this->healthScore,
            'statistics' => $this->statistics->toArray(),
        ];
    }
}
