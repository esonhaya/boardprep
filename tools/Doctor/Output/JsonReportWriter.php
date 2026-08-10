<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\DTO\DoctorResult;

final class JsonReportWriter
{
    public function write(
        DoctorResult $result
    ): void {
        $v2 =
            (new V2ReportBuilder())
                ->build($result);

        $report = [
            'health' => $result->health(),

            'summary' => [
                'checks' => count($result->checks),
                'pass' => $result->passCount(),
                'warning' => $result->warningCount(),
                'fail' => $result->failCount(),
                'info' => $result->infoCount(),
                'findings' => $result->findingCount(),
            ],

            'diagnostics' => $v2['diagnostics'],
            'diagnosis' => $v2['diagnosis'],
            'fix_plan' => $v2['fix_plan'],
            'fix_plans' => $v2['fix_plans'],
            'priority_actions' => $v2['priority_actions'],

            'checks' => array_map(
                static function ($check): array {
                    return [
                        'title' => $check->title,
                        'status' => $check->status,
                        'summary' => $check->summary,
                        'details' => $check->details,
                        'recommendations' => $check->recommendations,
                        'score' => $check->score,
                        'findings' => $check->findings->toArray(),
                    ];
                },
                $result->checks
            ),

            'findings' =>
                $result->findings()->toArray(),

            'trend' => $result->trend,
        ];

        file_put_contents(
            'storage/doctor-report.json',
            json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }
}
