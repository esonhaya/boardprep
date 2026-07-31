<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Services\RepositoryHealth\DTO\HealthReport;
use App\Services\RepositoryHealth\DTO\RepositoryStatistics;
use App\Services\RepositoryHealth\DTO\ValidationResult;

class ReportBuilder
{
    public static function build(
        array $results,
        RepositoryStatistics $statistics,
        float $healthScore
    ): HealthReport {
        $report = new HealthReport();

        foreach ($results as $result) {

            if (!$result instanceof ValidationResult) {
                continue;
            }

            foreach ($result->issues as $issue) {
                $report->issues[] = $issue;
            }
        }

        $report->statistics = $statistics;
        $report->healthScore = $healthScore;

        return $report;
    }
}
