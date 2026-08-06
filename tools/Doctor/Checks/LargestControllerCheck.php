<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Rules\Rules;

final class LargestControllerCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $largest =
            DoctorContext::snapshot()
                ->largestFile(
                    "/Controllers/"
                );

        $threshold =
            Rules::controllerMaxLines();

        $lines =
            $largest["lines"] ?? 0;

        return new CheckResult(

            title:
                "Largest Controller",

            status:
                $lines > $threshold
                    ? "WARNING"
                    : "PASS",

            summary:
                sprintf(
                    "%s (%d lines)",
                    $largest["path"] ?? "",
                    $lines
                ),

            details: [

                "Threshold : {$threshold}",
                "Current   : {$lines}"

            ],

            recommendations:

                $lines > $threshold

                ? [

                    "Split {$largest["path"]} into smaller action-oriented classes."

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
        return 20;
    }
}
