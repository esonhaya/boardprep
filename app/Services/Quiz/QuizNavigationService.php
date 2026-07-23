<?php

class QuizNavigationService
{

    public static function next(): void
    {

        $_SESSION["currentQuestion"]++;

        $questions =
            SessionService::get(
                "questions",
                []
            );

        $current =
            self::current();

        if (
            $current >= count($questions)
        ) {

            header(
                "Location: ?page=quiz&action=finish"
            );

            exit;

        }

        SessionService::remove(
            "feedback"
        );

        View::render(
            "quiz/index",
            [

                "question" =>
                    $questions[$current],

                "current" =>
                    $current,

                "total" =>
                    count($questions),

                "mode" =>
                    SessionService::get(
                        "mode",
                        "practice"
                    ),

                "feedback" =>
                    null

            ]
        );

    }

    public static function current(): int
    {

        return
            $_SESSION["currentQuestion"] ?? 0;

    }

    public static function isLastQuestion(): bool
    {

        return
            self::current()
            >=
            count(
                $_SESSION["questions"] ?? []
            ) - 1;

    }

    public static function reset(): void
    {

        $_SESSION["currentQuestion"] = 0;

    }

}
