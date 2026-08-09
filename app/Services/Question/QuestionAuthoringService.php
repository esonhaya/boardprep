<?php

declare(strict_types=1);

namespace App\Services\Question;

final class QuestionAuthoringService
{
    public static function prepare(
        int $id,
        array $input
    ): array {

        $question =
            QuestionService::build(
                $id,
                $input
            );

        $validation =
            QuestionService::validateForSave(
                $question
            );

        return [
            "question" => $question,
            "valid" => $validation["valid"],
            "errors" => $validation["errors"],
            "duplicates" => $validation["duplicates"],
        ];
    }

    public static function canSave(
        int $id,
        array $input
    ): bool {

        $result =
            self::prepare(
                $id,
                $input
            );

        return
            $result["valid"] === true
            && empty($result["duplicates"]);
    }

    public static function save(
        int $id,
        array $input
    ): ?array {

        $result =
            self::prepare(
                $id,
                $input
            );

        if (
            $result["valid"] !== true
            || !empty($result["duplicates"])
        ) {
            return null;
        }

        if ($id > 0) {
            return QuestionService::update(
                $id,
                $result["question"]
            );
        }

        return QuestionService::save(
            $result["question"]
        );
    }
}
