<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators;

class TaxonomyValidator
{

    public static function validate(array $question): array
    {

        $issues = [];

        foreach (

            [

                "domain",
                "topic",
                "concept"

            ]

            as $field

        ) {

            if (

                trim($question[$field] ?? "")

                ===

                ""

            ) {

                $issues[] = [

                    "severity" => "error",

                    "type" => "missing-" . $field,

                    "message" =>
                        ucfirst($field) . " is missing."

                ];

            }

        }

        return $issues;

    }

}
