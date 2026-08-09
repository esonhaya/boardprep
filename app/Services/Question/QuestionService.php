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

        $now =
            date(
                DATE_ATOM
            );

        $existing =
            $id > 0
                ? self::repository()->find((string) $id)
                : null;

        $options = [];

        for (
            $i = 1;
            $i <= 4;
            $i++
        ) {
            $optionId =
                "option-{$i}";

            $options[] = [
                "id" =>
                    $optionId,

                "text" =>
                    trim(
                        (string) (
                            $input["option_{$i}"]
                            ?? ""
                        )
                    ),

                "correct" =>
                    (
                        ($input["correct_option"] ?? "")
                        === $optionId
                    )
            ];
        }

        $question = [

            "id" =>
                $id > 0
                    ? $id
                    : ($existing["id"] ?? null),

            "status" =>
                $existing["status"]
                ?? "active",

            "source" =>
                $existing["source"]
                ?? "manual",

            "timesUsed" =>
                $existing["timesUsed"]
                ?? 0,

            "timesCorrect" =>
                $existing["timesCorrect"]
                ?? 0,

            "timesIncorrect" =>
                $existing["timesIncorrect"]
                ?? 0,

            "bookmarks" =>
                $existing["bookmarks"]
                ?? 0,

            "reports" =>
                $existing["reports"]
                ?? 0,

            "helpfulExplanation" =>
                $existing["helpfulExplanation"]
                ?? 0,

            "notHelpfulExplanation" =>
                $existing["notHelpfulExplanation"]
                ?? 0,

            "createdAt" =>
                $existing["createdAt"]
                ?? $now,

            "updatedAt" =>
                $now,

            "taxonomy" => [

                "board_id" =>
                    trim(
                        (string) (
                            $input["board_id"]
                            ?? $input["board"]
                            ?? (
                                $existing["taxonomy"]["board_id"]
                                ?? ""
                            )
                        )
                    ),

                "subject_id" =>
                    trim(
                        (string) (
                            $input["subject_id"]
                            ?? $input["subject"]
                            ?? (
                                $existing["taxonomy"]["subject_id"]
                                ?? ""
                            )
                        )
                    ),

                "domain_id" =>
                    trim(
                        (string) (
                            $input["domain_id"]
                            ?? $input["domain"]
                            ?? (
                                $existing["taxonomy"]["domain_id"]
                                ?? ""
                            )
                        )
                    ),

                "topic_id" =>
                    trim(
                        (string) (
                            $input["topic_id"]
                            ?? $input["topic"]
                            ?? (
                                $existing["taxonomy"]["topic_id"]
                                ?? ""
                            )
                        )
                    ),

                "concept_id" =>
                    trim(
                        (string) (
                            $input["concept_id"]
                            ?? $input["concept"]
                            ?? (
                                $existing["taxonomy"]["concept_id"]
                                ?? ""
                            )
                        )
                    )
            ],

            "difficulty" =>
                trim(
                    (string) (
                        $input["difficulty"]
                        ?? (
                            $existing["difficulty"]
                            ?? ""
                        )
                    )
                ),

            "type" =>
                trim(
                    (string) (
                        $input["type"]
                        ?? (
                            $existing["type"]
                            ?? "multiple_choice"
                        )
                    )
                ),

            "question" =>
                trim(
                    (string) (
                        $input["question"]
                        ?? (
                            $existing["question"]
                            ?? ""
                        )
                    )
                ),

            "options" =>
                $options,

            "explanation" =>
                trim(
                    (string) (
                        $input["explanation"]
                        ?? (
                            $existing["explanation"]
                            ?? ""
                        )
                    )
                )
        ];

        return $question;
    }

    public static function validate(
        array $question
    ): array {

        return \QuestionValidationService::validate(
            $question
        );
    }

    public static function validateForSave(
        array $question
    ): array {

        $validation =
            self::validate(
                $question
            );

        return [
            "valid" =>
                $validation["valid"],

            "errors" =>
                $validation["errors"],

            "duplicates" =>
                self::findDuplicates(
                    $question
                )
        ];
    }

    public static function save(
        array $question
    ): array {

        return self::repository()->create(
            $question
        );
    }

    public static function update(
        int|string $id,
        array $question
    ): ?array {

        $question["updatedAt"] =
            date(
                DATE_ATOM
            );

        return self::repository()->update(
            (string) $id,
            $question
        );
    }

    private static function findDuplicates(
        array $question
    ): array {

        return QuestionDuplicateService::find(
            $question
        );
    }

    private static function repository(): QuestionRepository
    {
        return App::container()
            ->get(
                QuestionRepository::class
            );
    }
}
