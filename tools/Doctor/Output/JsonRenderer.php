<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\DTO\DoctorResult;

final class JsonRenderer
{
    public function render(
        DoctorResult $report
    ): string {

        $checks = [];

        foreach ($report->checks as $check) {

            $checks[] = [

                'title' =>
                    $check->title,

                'status' =>
                    $check->status,

                'summary' =>
                    $check->summary,

                'details' =>
                    $check->details,

                'recommendations' =>
                    $check->recommendations,

                'score' =>
                    $check->score,

                'findings' =>
                    $check->findings->toArray(),

            ];

        }

        return json_encode(

            [

                'health' =>
                    $report->health(),

                'pass' =>
                    $report->passCount(),

                'warning' =>
                    $report->warningCount(),

                'fail' =>
                    $report->failCount(),

                'info' =>
                    $report->infoCount(),

                'checks' =>
                    $checks,

                'generatedAt' =>
                    date(DATE_ATOM),

            ],

            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES

        );

    }
}
