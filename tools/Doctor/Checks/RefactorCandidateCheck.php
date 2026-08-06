<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class RefactorCandidateCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $methods =
            DoctorContext::snapshot()
                ->methods;

        usort(

            $methods,

            fn($a, $b) =>
                $b["lines"] <=> $a["lines"]

        );

        $details = [];

        foreach (

            array_slice(
                $methods,
                0,
                10
            )

            as $method

        ) {

            $details[] = sprintf(

                "%3d  %s::%s()",

                $method["lines"],

                basename(
                    $method["file"]
                ),

                $method["name"]

            );

        }

        return new CheckResult(

            title: "Refactor Candidates",

            status:

                ($methods[0]["lines"] ?? 0) > 60

                    ? "WARNING"

                    : "PASS",

            summary:

                empty($methods)

                    ? "No methods."

                    : "Largest methods suitable for extraction.",

            details:

                $details,

            recommendations:

                [

                    "Extract helper methods before adding new functionality."

                ]

        );
    }

    public function category(): string
    {
        return "Architecture";
    }

    public function priority(): int
    {
        return 28;
    }
}
