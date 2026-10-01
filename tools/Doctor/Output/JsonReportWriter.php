<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\DTO\DoctorResult;

/**
 * BoardPrep compatibility writer for the extracted HayaDoctor contracts.
 *
 * HayaDoctor deliberately keeps PROJECT and DOCTOR scopes in one result. The
 * historical BoardPrep report is the project-facing report, so this thin
 * adapter preserves its summary contract while reusing Haya's DTOs and
 * report-building services. It is not a second Doctor engine.
 */
final class JsonReportWriter
{
    public function write(DoctorResult $result, string $path): void
    {
        $projectChecks = array_values(array_filter(
            $result->checks,
            static fn(object $check): bool => ($check->scope ?? 'PROJECT') === 'PROJECT'
        ));
        $v2 = (new V2ReportBuilder())->build($result);
        $report = [
            'generated_at' => date(DATE_ATOM),
            'health' => $result->health(),
            'summary' => [
                'checks' => count($projectChecks),
                'pass' => $result->passCount('PROJECT'),
                'warning' => $result->warningCount('PROJECT'),
                'fail' => $result->failCount('PROJECT'),
                'info' => $result->infoCount('PROJECT'),
                'findings' => $result->findingCount(),
            ],
            'diagnostics' => $v2['diagnostics'],
            'diagnosis' => $v2['diagnosis'],
            'fix_plan' => $v2['fix_plan'],
            'fix_plans' => $v2['fix_plans'],
            'priority_actions' => $v2['priority_actions'],
            'checks' => array_map(
                static fn(object $check): array => [
                    'title' => $check->title,
                    'status' => $check->status,
                    'summary' => $check->summary,
                    'details' => $check->details,
                    'recommendations' => $check->recommendations,
                    'score' => $check->score,
                    'findings' => $check->findings->toArray(),
                ],
                $projectChecks
            ),
            'findings' => $result->findings('PROJECT')->toArray(),
            'trend' => $result->trend,
        ];
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
