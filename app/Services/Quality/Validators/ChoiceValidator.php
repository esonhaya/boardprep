<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators;

class ChoiceValidator
{

    public static function validate(array $question): array
    {

        $issues = [];

        $choices = $question["choices"] ?? [];

        if (count($choices) < 4) {

            $issues[] = [

                "severity" => "error",

                "type" => "missing-choices",

                "message" => "Question has fewer than four choices."

            ];

        }

        foreach ($choices as $index => $choice) {

            if (trim($choice) === "") {

                $issues[] = [

                    "severity" => "error",

                    "type" => "empty-choice",

                    "message" =>
                        "Choice " . ($index + 1) . " is empty."

                ];

            }

            if ($choice !== trim($choice)) {

                $issues[] = [

                    "severity" => "info",

                    "type" => "choice-whitespace",

                    "message" =>
                        "Choice " . ($index + 1) . " contains unnecessary whitespace."

                ];

            }

        }

        if (count(array_unique($choices)) < count($choices)) {

            $issues[] = [

                "severity" => "warning",

                "type" => "duplicate-choices",

                "message" => "Question contains duplicate choices."

            ];

        }

        if (

            !in_array(

                $question["answer"] ?? "",

                $choices,

                true

            )

        ) {

            $issues[] = [

                "severity" => "error",

                "type" => "invalid-answer",

                "message" => "Answer does not match any choice."

            ];

        }

        return $issues;

    }

}
