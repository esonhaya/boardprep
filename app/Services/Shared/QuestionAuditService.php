<?php

class QuestionAuditService
{

    public static function summary(): array
    {

        $questions =
            QuestionRepository::all();

        $subjects = [];
        $topics = [];
        $difficulty = [];

        $duplicates = [];
        $ids = [];

        $missingExplanation = 0;
        $missingChoices = 0;


        foreach ($questions as $question) {

            $subjects[
                $question["subject"] ?? "Unknown"
            ] =
                ($subjects[
                    $question["subject"] ?? "Unknown"
                ] ?? 0) + 1;


            $topics[
                $question["topic"] ?? "Unknown"
            ] =
                ($topics[
                    $question["topic"] ?? "Unknown"
                ] ?? 0) + 1;


            $difficulty[
                $question["difficulty"] ?? "Unknown"
            ] =
                ($difficulty[
                    $question["difficulty"] ?? "Unknown"
                ] ?? 0) + 1;


            if (empty($question["choices"])) {

                $missingChoices++;

            }


            if (empty($question["explanation"])) {

                $missingExplanation++;

            }


            if (isset($question["id"])) {

                if (isset($ids[$question["id"]])) {

                    $duplicates[] =
                        $question["id"];

                }

                $ids[
                    $question["id"]
                ] = true;

            }

        }


        return [

            "total" =>
                count($questions),

            "subjects" =>
                $subjects,

            "topics" =>
                $topics,

            "difficulty" =>
                $difficulty,

            "duplicateIds" =>
                $duplicates,

            "missingExplanation" =>
                $missingExplanation,

            "missingChoices" =>
                $missingChoices

        ];

    }

}
