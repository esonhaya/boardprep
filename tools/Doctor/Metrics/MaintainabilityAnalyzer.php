<?php

declare(strict_types=1);

namespace Tools\Doctor\Metrics;

use Tools\Doctor\Snapshot\ProjectSnapshot;

final class MaintainabilityAnalyzer
{
    public function analyze(ProjectSnapshot $snapshot): void
    {
        $metrics = [];

        foreach ($snapshot->files as $file) {

            $path = $file["path"];

            $methodCount = 0;
            $complexity = 0;

            foreach ($snapshot->metric("cyclomatic") as $metric) {

                if ($metric["file"] !== $path) {
                    continue;
                }

                $methodCount++;
                $complexity += $metric["score"];

            }

            $averageComplexity =
                $methodCount > 0
                    ? $complexity / $methodCount
                    : 1;

            $score = 100;

            $score -= min(
                30,
                (int) floor($file["lines"] / 20)
            );

            $score -= min(
                40,
                (int) round($averageComplexity * 2)
            );

            $score -= min(
                20,
                $methodCount
            );

            $score = max(
                0,
                min(
                    100,
                    $score
                )
            );

            $metrics[] = [

                "file" => $path,
                "score" => $score,

            ];

        }

        usort(
            $metrics,
            fn(array $a, array $b) =>
                $a["score"] <=> $b["score"]
        );

        $snapshot->setMetric(
            "maintainability",
            $metrics
        );
    }
}
