<?php

declare(strict_types=1);

namespace Tools\Doctor\Analyzers;

final class DependencyRiskAnalyzer
{
    /**
     * @param array<string,array<string,mixed>> $stats
     * @return array{
     *     risk:int,
     *     highest_file:string,
     *     highest_dependencies:int,
     *     total_dependencies:int,
     *     highly_coupled:int
     * }
     */
    public function analyze(array $stats): array
    {
        $application = $this->applicationStats($stats);

        if ($application === []) {
            return [
                'risk' => 0,
                'highest_file' => 'N/A',
                'highest_dependencies' => 0,
                'total_dependencies' => 0,
                'highly_coupled' => 0,
            ];
        }

        uasort(
            $application,
            fn(array $a, array $b): int =>
                ($b['total'] ?? 0) <=> ($a['total'] ?? 0)
        );

        $highestFile = array_key_first($application);
        $highestRow = $application[$highestFile];

        $totalDependencies = 0;
        $highlyCoupled = 0;

        foreach ($application as $row) {
            $total = (int) ($row['total'] ?? 0);

            $totalDependencies += $total;

            if ($total >= 10) {
                $highlyCoupled++;
            }
        }

        /*
         * Risk is deliberately normalized to 0-100.
         *
         * This is not a "bad code" score. It represents architectural
         * change risk caused by dependency concentration.
         */
        $couplingRisk = min(
            45,
            (int) round(
                ((int) ($highestRow['total'] ?? 0)) * 2.25
            )
        );

        $concentrationRisk = min(
            30,
            $highlyCoupled * 5
        );

        $volumeRisk = min(
            25,
            (int) round(
                $totalDependencies / 20
            )
        );

        $risk = min(
            100,
            $couplingRisk
            + $concentrationRisk
            + $volumeRisk
        );

        return [
            'risk' => $risk,
            'highest_file' => $highestFile,
            'highest_dependencies' => (int) ($highestRow['total'] ?? 0),
            'total_dependencies' => $totalDependencies,
            'highly_coupled' => $highlyCoupled,
        ];
    }

    /**
     * Remove Doctor internals from application architecture metrics.
     *
     * @param array<string,array<string,mixed>> $stats
     * @return array<string,array<string,mixed>>
     */
    private function applicationStats(array $stats): array
    {
        $result = [];

        foreach ($stats as $file => $row) {
            $normalized = str_replace('\\', '/', $file);

            if (
                str_starts_with($normalized, './tools/Doctor/')
                || str_starts_with($normalized, 'tools/Doctor/')
                || str_starts_with($normalized, './tests/')
                || str_starts_with($normalized, 'tests/')
            ) {
                continue;
            }

            $result[$file] = $row;
        }

        return $result;
    }
}
