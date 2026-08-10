<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Scanners\PhpSourceScanner;
use Tools\Doctor\Scanners\TokenScanner;

final class UnusedImportCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot =
            DoctorContext::snapshot();

        $unused = [];

        foreach (

            $snapshot->imports

            as $file => $imports

        ) {

            $contents =
                PhpSourceScanner::contents(
                    $file
                );

            if ($contents === "") {
                continue;
            }

            $identifiers =
                TokenScanner::identifiers(
                    $contents
                );

            foreach (

                $imports

                as $import

            ) {

                $short =
                    basename(
                        str_replace(
                            "\\",
                            "/",
                            $import
                        )
                    );

                if (

                    !in_array(
                        $short,
                        $identifiers,
                        true
                    )

                ) {

                    $unused[] =
                        "{$file} :: {$short}";

                }

            }

        }

        return new CheckResult(

            title:
                "Unused Imports",

            status:

                empty($unused)
                    ? "PASS"
                    : "WARNING",

            summary:

                empty($unused)

                ? "No unused imports found."

                : count($unused)
                    . " unused imports found.",

            details:

                array_slice(
                    $unused,
                    0,
                    20
                ),

            recommendations:

                empty($unused)

                ? []

                : [

                    "Remove unused imports to reduce noise."

                ]

        );

    }

    public function category(): string
    {
        return "Quality";
    }

    public function priority(): int
    {
        return 40;
    }
}
