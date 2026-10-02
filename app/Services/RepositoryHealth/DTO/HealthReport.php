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

    /** Optional CLI diagnostics; intentionally excluded from the stable report payload. */
    public ?array $profiling = null;

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

    /**
     * Filter the permanent action queue without moving filtering logic into a
     * controller or view.
     *
     * @param array{severity?: string, board?: string, category?: string, entity_type?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function filteredActionQueue(array $filters = []): array
    {
        return self::filterActionQueue($this->actionQueue, $filters);
    }

    /**
     * Apply the same bounded action-queue filters to a persisted snapshot.
     * The developer surface reads the canonical snapshot instead of running
     * a full repository scan during an ordinary page request.
     *
     * @param array<int, array<string, mixed>> $queue
     * @param array{severity?: string, board?: string, category?: string, entity_type?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function filterActionQueue(array $queue, array $filters = []): array
    {
        $severity = strtoupper(trim((string) ($filters['severity'] ?? '')));
        $board = strtolower(trim((string) ($filters['board'] ?? '')));
        $category = strtoupper(trim((string) ($filters['category'] ?? '')));
        $entityType = strtolower(trim((string) ($filters['entity_type'] ?? '')));

        return array_values(array_filter($queue, static function (array $issue) use ($severity, $board, $category, $entityType): bool {
            if ($severity !== '' && strtoupper((string) ($issue['status'] ?? '')) !== $severity) return false;
            if ($board !== '' && strtolower((string) ($issue['board_id'] ?? '')) !== $board) return false;
            if ($category !== '' && strtoupper((string) ($issue['category'] ?? '')) !== $category) return false;
            if ($entityType !== '' && strtolower((string) ($issue['entity_type'] ?? '')) !== $entityType) return false;
            return true;
        }));
    }
}
