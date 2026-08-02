<?php

declare(strict_types=1);

class MetadataRepairService
{
    public static function scan(): array
    {

        $questions =
            QuestionRepository::all();

        $report = [];

        foreach (
            $questions as $question
        ) {

            $issues = [];

            self::scanTaxonomy(
                $question,
                $issues
            );

            self::scanDifficulty(
                $question,
                $issues
            );

            if (
                !empty($issues)
            ) {

                $report[] = [

                    "id" =>
                        $question["id"] ?? "",

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

        foreach (
            $questions as &$question
        ) {

            if (
                empty(
                    $question["taxonomy"]
                )
            ) {

                $question["taxonomy"] = [

                    "board_id" => "",
                    "subject_id" => "",
                    "domain_id" => "",
                    "topic_id" => "",
                    "concept_id" => ""

                ];

                $updated++;

            }

        }

        QuestionRepository::saveAll(
            $questions
        );

        return $updated;

    }

    private static function scanTaxonomy(
        array $question,
        array &$issues
    ): void
    {

        $taxonomy =
            $question["taxonomy"] ?? [];

        foreach (

            [

                "board_id" =>
                    "Missing board",

                "subject_id" =>
                    "Missing subject",

                "domain_id" =>
                    "Missing domain",

                "topic_id" =>
                    "Missing topic",

                "concept_id" =>
                    "Missing concept"

            ]

            as $key => $message

        ) {

            if (

                empty(

                    trim(

                        (string)

                        ($taxonomy[$key] ?? "")

                    )

                )

            ) {

                $issues[] =
                    $message;

            }

        }

    }

    private static function scanDifficulty(
        array $question,
        array &$issues
    ): void
    {

        if (

            !in_array(

                strtolower(

                    trim(

                        (string)

                        ($question["difficulty"] ?? "")

                    )

                ),

                [

                    "easy",
                    "medium",
                    "hard"

                ],

                true

            )

        ) {

            $issues[] =
                "Invalid difficulty";

        }

    }

}
