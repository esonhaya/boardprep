<?php

class MetadataValidator
{
    private const VALID_DIFFICULTIES = [

        "easy",
        "medium",
        "hard"

    ];

    private const VALID_STATUSES = [

        "draft",
        "active",
        "archived"

    ];

    public static function validate(array $question): array
    {
        $issues = [];

        if (empty($question["id"])) {

            $issues[] = [

                "severity" => "error",

                "type" => "missing-id",

                "message" => "Question has no ID."

            ];

        }

        foreach (

            [

                "board",
                "subject",
                "difficulty"

            ]

            as $field

        ) {

            if (trim($question[$field] ?? "") === "") {

                $issues[] = [

                    "severity" => "error",

                    "type" => "missing-" . $field,

                    "message" =>
                        ucfirst($field) . " is missing."

                ];

            }

        }

        $difficulty = strtolower(

            trim(

                $question["difficulty"] ?? ""

            )

        );

        if (

            $difficulty !== ""

            &&

            !in_array(

                $difficulty,

                self::VALID_DIFFICULTIES,

                true

            )

        ) {

            $issues[] = [

                "severity" => "warning",

                "type" => "invalid-difficulty",

                "message" =>
                    "Difficulty value is invalid."

            ];

        }

        $status = strtolower(

            trim(

                $question["status"] ?? ""

            )

        );

        if (

            $status !== ""

            &&

            !in_array(

                $status,

                self::VALID_STATUSES,

                true

            )

        ) {

            $issues[] = [

                "severity" => "warning",

                "type" => "invalid-status",

                "message" =>
                    "Status value is invalid."

            ];

        }

        if ($status === "draft") {

            $issues[] = [

                "severity" => "info",

                "type" => "draft",

                "message" => "Question is still a draft."

            ];

        }

        return $issues;
    }
}
