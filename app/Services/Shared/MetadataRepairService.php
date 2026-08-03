<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class MetadataRepairService
{
    private static function repository(): QuestionRepository
    {

        return App::container()
            ->get(
                QuestionRepository::class
            );

    }

    public static function scan(): array
    {

        $report = [];

        foreach (

            self::repository()->all()

            as $question

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

        $updated = 0;

        foreach (

            self::repository()->all()

            as $question

        ) {

            if (

                self::repairQuestion(
                    $question
                )

            ) {

                self::repository()->update(

                    (string) $question["id"],

                    $question

                );

                $updated++;

            }

        }

        return $updated;

    }

    private static function repairQuestion(
        array &$question
    ): bool
    {

        $changed = false;

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

            $changed = true;

        }

        if (

            empty(
                $question["status"]
            )

        ) {

            $question["status"] =
                "approved";

            $changed = true;

        }

        if (

            empty(
                $question["options"]
            )

        ) {

            $question["options"] = [];

            $changed = true;

        }

        if (

            !isset(
                $question["tags"]
            )

        ) {

            $question["tags"] = [];

            $changed = true;

        }

        if (

            !isset(
                $question["hint"]
            )

        ) {

            $question["hint"] = "";

            $changed = true;

        }

        return $changed;

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
