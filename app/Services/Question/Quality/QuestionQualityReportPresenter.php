<?php

declare(strict_types=1);

namespace App\Services\Question\Quality;

use App\Services\RepositoryHealth\DTO\HealthReport;

final class QuestionQualityReportPresenter
{
    public static function present(HealthReport $report): array
    {
        $groups = QuestionQualityIssueGrouper::group($report->issues);

        return [
            'report' => $report,
            'healthScore' => $report->healthScore,
            'issues' => $report->issues,
            'issueGroups' => $groups,
            'issueGroupLabels' => QuestionQualityIssueCatalog::labels(),
            'severitySummary' => QuestionQualitySeveritySummary::build($report->issues),
        ] + self::legacyBuckets($groups);
    }

    private static function legacyBuckets(array $groups): array
    {
        $result = [];
        foreach (QuestionQualityIssueCatalog::labels() as $key => $_label) {
            $result[$key] = $groups[$key] ?? [];
        }
        return $result;
    }
}
