<?php

class QuizHistoryService
{

    public static function all(): array
    {

        return SessionService::get(
            "quizHistory",
            []
        );

    }


    public static function filterUnused(
        array $questions
    ): array
    {

        $used = SessionService::get(
            "usedQuestions",
            []
        );

        $unused = array_filter(
            $questions,
            function ($question) use ($used) {

                if (!isset($question["id"])) {
                    return true;
                }

                return !in_array(
                    $question["id"],
                    $used,
                    true
                );

            }
        );

        if (empty($unused)) {

            SessionService::remove(
                "usedQuestions"
            );

            return $questions;

        }

        return array_values($unused);

    }


    public static function remember(
        array $questions
    ): void
    {

        $used = SessionService::get(
            "usedQuestions",
            []
        );

        foreach ($questions as $question) {

            if (!isset($question["id"])) {
                continue;
            }

            $used[] = $question["id"];

        }

        SessionService::set(
            "usedQuestions",
            array_values(
                array_unique($used)
            )
        );

    }

}
