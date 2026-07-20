<?php

class QuestionImportService
{

    public static function importJson(
        string $file
    ): array
    {

        if (!file_exists($file)) {

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


        if (!is_array($questions)) {

            return [

                "imported" => 0,
                "rejected" => 0,
                "errors" => [
                    "Invalid JSON format."
                ]

            ];

        }


        $existing =
            QuestionRepository::all();

        $existingIds = [];


        foreach ($existing as $question) {

            if (isset($question["id"])) {

                $existingIds[
                    $question["id"]
                ] = true;

            }

        }


        $valid = [];
        $errors = [];

        $imported = 0;
        $rejected = 0;


        foreach ($questions as $index => $question) {

            $validation =
                QuestionValidationService::validate(
                    $question
                );


            if (!empty($validation)) {

                $rejected++;

                $errors[] = [

                    "question" =>
                        $index + 1,

                    "errors" =>
                        $validation

                ];

                continue;

            }


            if (
                isset(
                    $existingIds[
                        $question["id"]
                    ]
                )
            ) {

                $rejected++;

                $errors[] = [

                    "question" =>
                        $question["id"],

                    "errors" => [
                        "Duplicate ID"
                    ]

                ];

                continue;

            }


            $existingIds[
                $question["id"]
            ] = true;

            $valid[] =
                $question;

            $imported++;

        }


        if (!empty($valid)) {

            file_put_contents(

                __DIR__
                .
                "/../../storage/questions.json",

                json_encode(

                    array_merge(
                        $existing,
                        $valid
                    ),

                    JSON_PRETTY_PRINT

                )

            );

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
