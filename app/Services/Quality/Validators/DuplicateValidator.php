<?php

class DuplicateValidator
{

    public static function analyze(array $questions): array
    {

        $issues = [];

        $ids = [];

        $questionsSeen = [];

        foreach ($questions as $question) {

            $id = trim($question["id"] ?? "");

            if ($id !== "") {

                if (isset($ids[$id])) {

                    $issues[] = [

                        "severity" => "error",

                        "type" => "duplicate-id",

                        "questionId" => $id,

                        "message" =>
                            "Duplicate question ID."

                    ];

                }

                $ids[$id] = true;

            }

            $text = strtolower(

                trim(

                    $question["question"] ?? ""

                )

            );

            if ($text !== "") {

                if (isset($questionsSeen[$text])) {

                    $issues[] = [

                        "severity" => "warning",

                        "type" => "duplicate-question",

                        "questionId" => $id,

                        "message" =>
                            "Duplicate question text."

                    ];

                }

                $questionsSeen[$text] = true;

            }

        }

        return $issues;

    }

}
