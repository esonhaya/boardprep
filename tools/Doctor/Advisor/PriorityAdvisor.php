<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

use Tools\Doctor\Diagnostics\DiagnosticRegistry;
use Tools\Doctor\DTO\DoctorResult;

final class PriorityAdvisor
{
    public static function impact(
        string $title
    ): int {
        return match ($title) {
            'Largest Method' => 100,
            'Largest Service' => 95,
            'Largest Controller' => 90,
            'Layer Violations' => 90,
            'Circular Dependencies' => 90,
            'Dependency Coupling' => 80,
            'Dead Classes' => 60,
            'Unused Imports' => 40,
            'Empty Directories' => 20,
            'Domain Migration' => 15,
            default => 10,
        };
    }

    public static function findingImpact(
        string $id
    ): int {
        return DiagnosticRegistry::impact($id);
    }

    public static function label(
        int $impact
    ): string {
        return match (true) {
            $impact >= 90 => 'Critical',
            $impact >= 70 => 'High',
            $impact >= 40 => 'Medium',
            default => 'Low',
        };
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function rankedFindings(
        DoctorResult $report
    ): array {
        $findings = $report->findings()->all();

        usort(
            $findings,
            static function ($a, $b): int {
                return $b->impact() <=> $a->impact();
            }
        );

        return array_map(
            static fn($finding): array => [
                'id' => $finding->id,
                'title' => $finding->title,
                'impact' => $finding->impact(),
                'priority' => $finding->priorityLabel(),
                'effort' => $finding->effort(),
                'severity' => $finding->severity,
                'file' => $finding->file,
                'symbol' => $finding->symbol,
            ],
            $findings
        );
    }
}
