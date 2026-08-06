<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class MaintainabilityCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $metrics =
            DoctorContext::snapshot()
                ->metric("maintainability");

        usort(

            $metrics,

            fn ($a, $b) =>
                $a["score"] <=> $b["score"]

        );

        $worst =
            $metrics[0] ?? null;

        $details = [];

        foreach (

            array_slice(
                $metrics,
                0,
                10
            )

            as $metric

        ) {

            $details[] = sprintf(

                "%3d  %s",

                $metric["score"],

                $metric["file"]

            );

        }

        return new CheckResult(

            title: "Maintainability",

            status:

                $worst !== null
                && $worst["score"] < 70

                    ? "WARNING"

                    : "PASS",

            summary:

                $worst === null

                    ? "No files analyzed."

                    : sprintf(

                        "%s (%d)",

                        $worst["file"],

                        $worst["score"]

                    ),

            details:

                $details,

            recommendations:

                $worst !== null
                && $worst["score"] < 70

                    ? [

                        "Improve maintainability by reducing file size, complexity and responsibilities."

                    ]

                    : []

        );
    }

    public function category(): string
    {
        return "Architecture";
    }

    public function priority(): int
    {
        return 26;
    }
}
