<?php

class QuestionValidationService
{
    public static function validate(
        array $question
    ): array {

        $errors = [];

        self::validateIdentity(
            $question,
            $errors
        );

        self::validateTaxonomy(
            $question,
            $errors
        );

        self::validateQuestion(
            $question,
            $errors
        );

        self::validateOptions(
            $question,
            $errors
        );

        self::validateExplanation(
            $question,
            $errors
        );

        return [

            "valid" =>
                empty($errors),

            "errors" =>
                $errors

        ];

    }

    private static function validateIdentity(
        array $question,
        array &$errors
    ): void {

        if (
            empty($question["id"])
        ) {

            $errors[] =
                "Missing ID";

        }

    }

    private static function validateTaxonomy(
        array $question,
        array &$errors
    ): void {

        $taxonomy =
            $question["taxonomy"] ?? [];

        if (
            empty($taxonomy["board_id"])
        ) {

            $errors[] =
                "Missing board";

        }

        if (
            empty($taxonomy["subject_id"])
        ) {

            $errors[] =
                "Missing subject";

        }

        if (
            empty($taxonomy["domain_id"])
        ) {

            $errors[] =
                "Missing domain";

        }

        if (
            empty($taxonomy["topic_id"])
        ) {

            $errors[] =
                "Missing topic";

        }

        if (
            empty($taxonomy["concept_id"])
        ) {

            $errors[] =
                "Missing concept";

        }

    }

    private static function validateQuestion(
        array $question,
        array &$errors
    ): void {

        if (
            empty($question["difficulty"])
        ) {

            $errors[] =
                "Missing difficulty";

        }

        if (
            empty($question["type"])
        ) {

            $errors[] =
                "Missing question type";

        }

        if (
            empty($question["question"])
        ) {

            $errors[] =
                "Missing question";

        }

    }

    private static function validateOptions(
        array $question,
        array &$errors
    ): void {

        $options =
            $question["options"] ?? [];

        if (
            count($options) < 2
        ) {

            $errors[] =
                "At least two options are required.";

            return;

        }

        $texts = [];

        $correctCount = 0;

        foreach (
            $options as $option
        ) {

            $text =
                trim(
                    $option["text"] ?? ""
                );

            if (
                $text === ""
            ) {

                $errors[] =
                    "Option text cannot be empty.";

                continue;

            }

            if (
                in_array(
                    $text,
                    $texts,
                    true
                )
            ) {

                $errors[] =
                    "Options must be unique.";

            }

            $texts[] =
                $text;

            if (
                !empty(
                    $option["correct"]
                )
            ) {

                $correctCount++;

            }

        }

        if (
            $correctCount === 0
        ) {

            $errors[] =
                "A correct option is required.";

        }

        if (
            $correctCount > 1
        ) {

            $errors[] =
                "Only one correct option is allowed.";

        }

    }

    private static function validateExplanation(
        array $question,
        array &$errors
    ): void {

        if (
            empty(
                $question["explanation"]
            )
        ) {

            $errors[] =
                "Missing explanation";

        }

    }

}
