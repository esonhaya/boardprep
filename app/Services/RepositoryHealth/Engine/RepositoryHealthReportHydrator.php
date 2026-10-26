<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Services\RepositoryHealth\DTO\HealthIssue;
use App\Services\RepositoryHealth\DTO\HealthReport;
use App\Services\RepositoryHealth\DTO\RepositoryStatistics;

/**
 * Reconstructs the display DTOs from a provenance-validated Doctor snapshot.
 * Callers must validate the snapshot before hydration.
 */
final class RepositoryHealthReportHydrator
{
    /** @param array<string, mixed> $payload */
    public static function hydrate(array $payload): ?HealthReport
    {
        if (!is_array($payload['statistics'] ?? null) || !is_array($payload['issues'] ?? null)) {
            return null;
        }

        $report = new HealthReport();
        $report->generatedAt = is_string($payload['generated_at'] ?? null) ? $payload['generated_at'] : '';
        $report->overallStatus = is_string($payload['overall_status'] ?? null) ? $payload['overall_status'] : 'REVIEW';
        $report->healthScore = is_numeric($payload['health_score'] ?? null) ? (float) $payload['health_score'] : 0.0;
        $report->summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $report->checks = is_array($payload['checks'] ?? null) ? $payload['checks'] : [];
        $report->boardSummaries = is_array($payload['board_summaries'] ?? null) ? $payload['board_summaries'] : [];
        $report->affectedIds = is_array($payload['affected_ids'] ?? null) ? $payload['affected_ids'] : [];
        $report->exclusions = is_array($payload['exclusions'] ?? null) ? $payload['exclusions'] : [];
        $report->actionQueue = is_array($payload['action_queue'] ?? null) ? $payload['action_queue'] : [];
        $report->statistics = self::statistics($payload['statistics']);

        foreach ($payload['issues'] as $row) {
            if (!is_array($row) || !is_string($row['code'] ?? null)) {
                return null;
            }
            $issue = new HealthIssue();
            $issue->validator = self::text($row['validator'] ?? '');
            $issue->severity = self::text($row['severity'] ?? 'info');
            $issue->priority = self::text($row['priority'] ?? 'low');
            $issue->category = self::text($row['category'] ?? 'UNKNOWN');
            $issue->code = $row['code'];
            $issue->message = self::text($row['message'] ?? '');
            $issue->recommendation = self::text($row['suggested_action'] ?? '');
            $issue->repairable = ($row['repairable'] ?? false) === true;
            $issue->entityType = is_string($row['entity_type'] ?? null) ? $row['entity_type'] : null;
            $entityId = $row['entity_id'] ?? null;
            $issue->entityId = is_string($entityId) || is_int($entityId) ? $entityId : null;
            $issue->metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
            $issue->status = self::text($row['status'] ?? 'FAIL');
            $issue->boardId = is_string($row['board_id'] ?? null) ? $row['board_id'] : null;
            $issue->evidence = is_array($row['evidence'] ?? null) ? $row['evidence'] : [];
            $issue->suggestedAction = self::text($row['suggested_action'] ?? '');
            $issue->sourceCheck = self::text($row['source_check'] ?? '');
            $issue->justifiedExclusion = ($row['justified_exclusion'] ?? false) === true;
            $report->issues[] = $issue;
        }

        return $report;
    }

    /** @param array<string, mixed> $payload */
    private static function statistics(array $payload): RepositoryStatistics
    {
        $statistics = new RepositoryStatistics();
        $statistics->totalQuestions = self::integer($payload['total_questions'] ?? 0);
        $statistics->totalIssues = self::integer($payload['total_issues'] ?? 0);
        $statistics->errors = self::integer($payload['errors'] ?? 0);
        $statistics->warnings = self::integer($payload['warnings'] ?? 0);
        $statistics->infos = self::integer($payload['infos'] ?? 0);
        $statistics->issuesByCategory = self::array($payload['issues_by_category'] ?? []);
        $statistics->issuesByValidator = self::array($payload['issues_by_validator'] ?? []);
        $statistics->questionsPerDifficulty = self::array($payload['questions_per_difficulty'] ?? []);
        $statistics->questionsPerStatus = self::array($payload['questions_per_status'] ?? []);
        $statistics->questionsPerBoard = self::array($payload['questions_per_board'] ?? []);
        $statistics->questionsPerSubject = self::array($payload['questions_per_subject'] ?? []);
        $statistics->questionsPerDomain = self::array($payload['questions_per_domain'] ?? []);
        $statistics->questionsPerTopic = self::array($payload['questions_per_topic'] ?? []);
        $statistics->questionsPerConcept = self::array($payload['questions_per_concept'] ?? []);
        $statistics->coverage = self::array($payload['coverage'] ?? []);
        $statistics->boards = self::array($payload['boards'] ?? []);
        $statistics->totals = self::array($payload['totals'] ?? []);
        return $statistics;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
