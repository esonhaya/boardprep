<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Context\DoctorSelfContext;
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Metrics\DoctorMetricsPipeline;
use Tools\Doctor\Metrics\MetricRegistry;
use Tools\Doctor\Metrics\MetricsPipeline;
use Tools\Doctor\Output\JsonReportWriter;
use Tools\Doctor\Output\V2ConsoleWriter;
use Tools\Doctor\Registry\CheckRegistry;
use Tools\Doctor\Snapshot\DoctorSnapshotBuilder;
use Tools\Doctor\Snapshot\ProjectSnapshotBuilder;

final class DoctorRunner
{
    public function run(): DoctorResult
    {
        MetricRegistry::reset();

        $projectSnapshot =
            (new ProjectSnapshotBuilder())
                ->build();

        (new MetricsPipeline())
            ->analyze(
                $projectSnapshot
            );

        DoctorContext::setSnapshot(
            $projectSnapshot
        );

        $doctorSnapshot =
            (new DoctorSnapshotBuilder())
                ->build();

        (new DoctorMetricsPipeline())
            ->analyze(
                $doctorSnapshot
            );

        DoctorSelfContext::setSnapshot(
            $doctorSnapshot
        );

        $result = new DoctorResult();

        $checks =
            (new CheckRegistry())
                ->fromDirectories([
                    "./tools/Doctor/Project/Shared/Checks",
                    "./tools/Doctor/Project/BoardPrep/Checks",
                    "./tools/Doctor/Self/Checks",
                ]);

        foreach ($checks as $check) {
            $checkResult =
                $check->run();

            MetricRegistry::set(
                strtolower(
                    str_replace(
                        " ",
                        "-",
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

        (new V2ConsoleWriter())
            ->write($result);

        return $result;
    }
}
