<?php

class MetadataRepairService
{

    public static function scan(): array
    {

        $questions =
            QuestionRepository::all();

        $report = [];

        foreach ($questions as $question) {

            $issues = [];


            if (
                empty(
                    trim(
                        $question["subject"] ?? ""
                    )
                )
            ) {

                $issues[] =
                    "Missing Subject";

            }


            if (
                empty(
                    trim(
                        $question["domain"] ?? ""
                    )
                )
            ) {

                $issues[] =
                    "Missing Domain";

            }


            if (
                empty(
                    trim(
                        $question["topic"] ?? ""
                    )
                )
            ) {

                $issues[] =
                    "Missing Topic";

            }


            if (
                empty(
                    trim(
                        $question["concept"] ?? ""
                    )
                )
            ) {

                $issues[] =
                    "Missing Concept";

            }


            if (
                !in_array(

                    strtolower(
                        $question["difficulty"] ?? ""
                    ),

                    [
                        "easy",
                        "medium",
                        "hard"
                    ]

                )
            ) {

                $issues[] =
                    "Invalid Difficulty";

            }


            if (!empty($issues)) {

                $report[] = [

                    "id" =>
                        $question["id"] ?? 0,

                    "question" =>
                        $question["question"] ?? "",

                    "issues" =>
                        $issues

                ];

            }

        }

        return $report;

    }



    public static function repair(): int
    {

        $questions =
            QuestionRepository::all();

        $updated = 0;

        foreach ($questions as &$question) {

            if (
                empty(
                    trim(
                        $question["subject"] ?? ""
                    )
                )
            ) {

                $question["subject"] =
                    self::guessSubject(
                        $question
                    );

                $updated++;

            }

        }

        QuestionRepository::saveAll(
            $questions
        );

        return $updated;

    }



    private static function guessSubject(
        array $question
    ): string
    {

        $domain =
            strtolower(
                trim(
                    $question["domain"] ?? ""
                )
            );


        return match ($domain) {

            "language" =>
                "English",

            default =>
                "General"

        };

    }

}
