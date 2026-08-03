<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionImportService
{
    public static function importJson(
        string $file
    ): array
    {

        if (
            !file_exists($file)
        ) {

            return [

                "imported" => 0,
                "rejected" => 0,

                "errors" => [

                    "Import file not found."

                ]

            ];

        }

        $questions =
            json_decode(
                file_get_contents($file),
                true
            );

        if (
            !is_array($questions)
        ) {

            return [

                "imported" => 0,
                "rejected" => 0,

                "errors" => [

                    "Invalid JSON format."

                ]

            ];

        }

        $repository =
            App::container()
                ->get(
                    QuestionRepository::class
                );

        $existingIds = [];

        foreach (
            $repository->all()
            as $question
        ) {

            if (
                isset(
                    $question["id"]
                )
            ) {

                $existingIds[
                    $question["id"]
                ] = true;

            }

        }

        $errors = [];

        $imported = 0;

        $rejected = 0;

        foreach (
            $questions as $index => $question
        ) {

            $validation =
                QuestionValidationService::validate(
                    $question
                );

            if (

                !empty(
                    $validation["errors"]
                )

            ) {

                $rejected++;

                $errors[] = [

                    "question" =>
                        $index + 1,

                    "errors" =>
                        $validation["errors"]

                ];

                continue;

            }

            $id =
                (string) (
                    $question["id"] ?? ""
                );

            if (

                $id !== ""

                &&

                isset(
                    $existingIds[$id]
                )

            ) {

                $rejected++;

                $errors[] = [

                    "question" =>
                        $id,

                    "errors" => [

                        "Duplicate ID"

                    ]

                ];

                continue;

            }

            $repository->create(
                $question
            );

            if (
                $id !== ""
            ) {

                $existingIds[$id] = true;

            }

            $imported++;

        }

        return [

            "imported" =>
                $imported,

            "rejected" =>
                $rejected,

            "errors" =>
                $errors

        ];

    }

}
