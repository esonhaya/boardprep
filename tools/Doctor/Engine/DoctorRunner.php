<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Metrics\MetricRegistry;
use Tools\Doctor\Metrics\MetricsPipeline;
use Tools\Doctor\Output\JsonReportWriter;
use Tools\Doctor\Snapshot\ProjectSnapshotBuilder;

final class DoctorRunner
{
    public function run(): DoctorResult
    {
        MetricRegistry::reset();

        $snapshot =
            (new ProjectSnapshotBuilder())
                ->build();

        (new MetricsPipeline())
            ->analyze($snapshot);

        DoctorContext::setSnapshot(
            $snapshot
        );

        $result = new DoctorResult();

        foreach (
            Doctor::checks()
            as $check
        ) {

            $checkResult =
                $check->run();

            MetricRegistry::set(
                strtolower(
                    str_replace(
                        ' ',
                        '-',
                        $checkResult->title
                    )
                ),
                $checkResult
            );

            $result->add(
                $checkResult
            );

        }

        (new JsonReportWriter())
            ->write($result);

        return $result;
    }
}
