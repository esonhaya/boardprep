<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Repositories\QuestionRepository;

class QuestionService
{
    public static function build(
        int $id,
        array $input
    ): array {

        $formData = [

            "subject" =>
                trim($input["subject"] ?? ""),

            "domain" =>
                trim($input["domain"] ?? ""),

            "topic" =>
                trim($input["topic"] ?? ""),

            "concept" =>
                trim($input["concept"] ?? ""),

            "difficulty" =>
                trim($input["difficulty"] ?? ""),

            "question" =>
                trim($input["question"] ?? ""),

            "choices" => [

                trim($input["choice_a"] ?? ""),
                trim($input["choice_b"] ?? ""),
                trim($input["choice_c"] ?? ""),
                trim($input["choice_d"] ?? "")

            ],

            "answer" =>
                trim($input["answer"] ?? ""),

            "explanation" =>
                trim($input["explanation"] ?? "")

        ];

        return QuestionMetadataService::build(
            $id,
            $formData
        );
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

        QuestionRepository::save(
            $question
        );
    }

    public static function update(
        int $id,
        array $question
    ): void {

        QuestionRepository::update(
            $id,
            $question
        );
    }
}
