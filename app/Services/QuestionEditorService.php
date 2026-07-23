<?php

class QuestionEditorService
{

    public static function buildQuestion(
        int $id
    ): array
    {

        $formData = [

            "domain" =>
                trim($_POST["domain"] ?? ""),

            "topic" =>
                trim($_POST["topic"] ?? ""),

            "concept" =>
                trim($_POST["concept"] ?? ""),

            "difficulty" =>
                trim($_POST["difficulty"] ?? ""),

            "question" =>
                trim($_POST["question"] ?? ""),

            "choices" => [

                trim($_POST["choice_a"] ?? ""),
                trim($_POST["choice_b"] ?? ""),
                trim($_POST["choice_c"] ?? ""),
                trim($_POST["choice_d"] ?? "")

            ],

            "answer" =>
                trim($_POST["answer"] ?? ""),

            "explanation" =>
                trim($_POST["explanation"] ?? "")

        ];


        return QuestionMetadataService::build(
            $id,
            $formData
        );

    }



    public static function validate(
        array $question
    ): array
    {

        return QuestionValidationService::validate(
            $question
        );

    }



    public static function duplicates(
        array $question
    ): array
    {

        return QuestionDuplicateService::find(
            $question
        );

    }



    public static function prepareSave(
        array $question
    ): array
    {

        return [

            "errors" =>
                self::validate(
                    $question
                ),

            "duplicates" =>
                self::duplicates(
                    $question
                )

        ];

    }



    public static function save(
        array $question
    ): void
    {

        QuestionRepository::save(
            $question
        );

    }



    public static function update(
        int $id,
        array $question
    ): void
    {

        QuestionRepository::update(
            $id,
            $question
        );

    }

}
