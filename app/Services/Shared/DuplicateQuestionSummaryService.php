<?php

class DuplicateQuestionSummaryService
{

    public static function summary(): array
    {

        $duplicates = [];
        $ids = [];

        foreach (QuestionRepository::all() as $question) {

            if (!isset($question["id"])) {
                continue;
            }

            if (isset($ids[$question["id"]])) {

                $duplicates[] =
                    $question["id"];

            }

            $ids[$question["id"]] = true;

        }

        return $duplicates;

    }

}
