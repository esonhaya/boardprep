<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Rules\Rules;

final class CyclomaticComplexityCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $metrics =
            DoctorContext::snapshot()
                ->metric("cyclomatic");

        usort(

            $metrics,

            fn ($a, $b) =>
                $b["score"] <=> $a["score"]

        );

        $worst =
            $metrics[0] ?? null;

        $threshold =
            Rules::cyclomaticComplexity();

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

                "%2d  %s::%s()",

                $metric["score"],

                basename(
                    $metric["file"]
                ),

                $metric["method"]

            );

        }

        return new CheckResult(

            title: "Cyclomatic Complexity",

            status:

                $worst !== null
                && $worst["score"] > $threshold

                    ? "WARNING"

                    : "PASS",

            summary:

                $worst === null

                    ? "No methods analyzed."

                    : sprintf(

                        "%s::%s() (%d)",

                        basename(
                            $worst["file"]
                        ),

                        $worst["method"],

                        $worst["score"]

                    ),

            details:

                $details,

            recommendations:

                $worst !== null
                && $worst["score"] > $threshold

                    ? [

                        "Reduce branching by extracting smaller methods."

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
        return 25;
    }
}
