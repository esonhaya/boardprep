<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionService
{
    public static function build(
        int $id,
        array $input
    ): array {

        return [

            "id" =>
                (string) $id,

            "code" =>
                sprintf(
                    "Q%06d",
                    $id
                ),

            "taxonomy" =>
                self::buildTaxonomy(
                    $input
                ),

            "type" =>
                "multiple_choice",

            "difficulty" =>
                trim(
                    $input["difficulty"] ?? "easy"
                ),

            "question" =>
                trim(
                    $input["question"] ?? ""
                ),

            "options" =>
                self::buildOptions(
                    $input
                ),

            "explanation" =>
                trim(
                    $input["explanation"] ?? ""
                ),

            "hint" =>
                trim(
                    $input["hint"] ?? ""
                ),

            "tags" => [],

            "source" =>
                trim(
                    $input["source"] ?? ""
                ),

            "status" =>
                "approved"

        ];

    }

    private static function buildTaxonomy(
        array $input
    ): array {

        return [

            "board_id" =>
                trim(
                    $input["board"] ?? ""
                ),

            "subject_id" =>
                trim(
                    $input["subject"] ?? ""
                ),

            "domain_id" =>
                trim(
                    $input["domain"] ?? ""
                ),

            "topic_id" =>
                trim(
                    $input["topic"] ?? ""
                ),

            "concept_id" =>
                trim(
                    $input["concept"] ?? ""
                )

        ];

    }

    private static function buildOptions(
        array $input
    ): array {

        $correct =
            trim(
                $input["correct_option"] ?? ""
            );

        return [

            [

                "id" =>
                    "option-1",

                "text" =>
                    trim(
                        $input["option_1"] ?? ""
                    ),

                "correct" =>
                    $correct === "option-1"

            ],

            [

                "id" =>
                    "option-2",

                "text" =>
                    trim(
                        $input["option_2"] ?? ""
                    ),

                "correct" =>
                    $correct === "option-2"

            ],

            [

                "id" =>
                    "option-3",

                "text" =>
                    trim(
                        $input["option_3"] ?? ""
                    ),

                "correct" =>
                    $correct === "option-3"

            ],

            [

                "id" =>
                    "option-4",

                "text" =>
                    trim(
                        $input["option_4"] ?? ""
                    ),

                "correct" =>
                    $correct === "option-4"

            ]

        ];

    }

    public static function validate(
        array $question
    ): array {

        return QuestionValidationService::validate(
            $question
        );

    }

    public static function findDuplicates(
        array $question
    ): array {

        return QuestionDuplicateService::find(
            $question
        );

    }

    public static function validateForSave(
        array $question
    ): array {

        return [

            "errors" =>
                self::validate(
                    $question
                ),

            "duplicates" =>
                self::findDuplicates(
                    $question
                )

        ];

    }

    public static function save(
        array $question
    ): void {

        App::container()
            ->get(
                QuestionRepository::class
            )
            ->create(
                $question
            );

    }

    public static function update(
        int $id,
        array $question
    ): void {

        App::container()
            ->get(
                QuestionRepository::class
            )
            ->update(
                (string) $id,
                $question
            );

    }

}
