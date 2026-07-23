<?php

class QuestionEditorQueryService
{

    public static function getQuestions(
        array $filters
    ): array
    {

        $questions =
            QuestionRepository::all();


        if (
            ($filters["domain"] ?? "") !== ""
            ||
            ($filters["difficulty"] ?? "") !== ""
            ||
            ($filters["topic"] ?? "") !== ""
        ) {

            $questions =
                QuestionSearchRepository::filter(
                    $filters["domain"] ?? "",
                    $filters["difficulty"] ?? "",
                    $filters["topic"] ?? ""
                );

        }


        if (
            ($filters["search"] ?? "") !== ""
        ) {

            $questions =
                QuestionSearchRepository::search(
                    $filters["search"]
                );

        }


        return $questions;

    }

}
