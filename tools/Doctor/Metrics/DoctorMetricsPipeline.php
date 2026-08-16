<?php

declare(strict_types=1);

namespace Tools\Doctor\Metrics;

use Tools\Doctor\Snapshot\ProjectSnapshot;

final class DoctorMetricsPipeline
{
    public function analyze(
        ProjectSnapshot $snapshot
    ): void {
        (new MethodMetricsAnalyzer())
            ->analyze($snapshot);
    }
}
