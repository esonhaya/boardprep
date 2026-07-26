<?php

class ReportBuilder
{
    public static function build(
        array $results,
        RepositoryStatistics $statistics,
        float $healthScore
    ): HealthReport
    {
        $report = new HealthReport();

        foreach ($results as $result) {

            foreach ($result->issues as $issue) {

                $report->issues[] = $issue;

            }

        }

        $report->statistics = $statistics;

        $report->healthScore = $healthScore;

        return $report;
    }
}
