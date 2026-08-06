<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Rules\Rules;

final class LargestMethodCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot =
            DoctorContext::snapshot();

        $largest = null;

        foreach ($snapshot->methods as $method) {

            if (

                $largest === null
                || $method["lines"] > $largest["lines"]

            ) {

                $largest = $method;

            }

        }

        $threshold =
            Rules::methodMaxLines();

        $lines =
            $largest["lines"] ?? 0;

        return new CheckResult(

            title: "Largest Method",

            status:
                $lines > $threshold
                    ? "WARNING"
                    : "PASS",

            summary:
                $largest["file"] ?? "",

            details: [

                "Method    : " . ($largest["name"] ?? ""),

                "Length    : {$lines} lines",

                "Threshold : {$threshold}"

            ],

            recommendations:

                $lines > $threshold

                ? [

                    "Split {$largest["name"]}() into smaller methods."

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
