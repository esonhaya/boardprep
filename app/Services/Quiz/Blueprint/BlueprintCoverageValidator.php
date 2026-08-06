<?php

declare(strict_types=1);

final class BlueprintCoverageValidator
{
    public static function validate(
        array $report
    ): array {

        $issues = [];

        foreach ($report as $row) {

            $required =
                (int) (
                    $row["section"]["questions"]
                    ??
                    0
                );

            if (
                $required > 0 &&
                $row["available"] < $required
            ) {

                $issues[] = [

                    "section" =>
                        $row["section"],

                    "required" =>
                        $required,

                    "available" =>
                        $row["available"],

                ];

            }

        }

        return $issues;

    }
}
