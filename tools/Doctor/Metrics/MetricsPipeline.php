<?php

declare(strict_types=1);

namespace Tools\Doctor\Metrics;

use Tools\Doctor\Analyzers\GraphStatisticsAnalyzer;
use Tools\Doctor\Snapshot\ProjectSnapshot;

final class MetricsPipeline
{
    public function analyze(
        ProjectSnapshot $snapshot
    ): void {

        (new CyclomaticAnalyzer())
            ->analyze($snapshot);

        (new MaintainabilityAnalyzer())
            ->analyze($snapshot);

        (new GraphStatisticsAnalyzer())
            ->analyze($snapshot);

    }
}
