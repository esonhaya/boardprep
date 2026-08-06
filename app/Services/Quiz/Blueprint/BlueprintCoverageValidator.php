<?php

declare(strict_types=1);

final class BlueprintCoverageValidator
{
    public static function validate(
        array $coverage
    ): array {

        $issues = [];

        foreach ($coverage as $row) {

            if (
                $row["generated"] <
                $row["required"]
            ) {

                $issues[] = [

                    "subject" =>
                        $row["subject"],

                    "domain" =>
                        $row["domain"],

                    "required" =>
                        $row["required"],

                    "generated" =>
                        $row["generated"],

                ];

            }

        }

        return $issues;

    }
}
