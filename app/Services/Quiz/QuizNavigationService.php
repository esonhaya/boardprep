<?php

class QuizNavigationService
{

    public static function next(): void
    {

        $current =
            self::current() + 1;

        SessionService::set(
            "currentQuestion",
            $current
        );

        $questions =
            SessionService::get(
                "questions",
                []
            );

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
            (int) SessionService::get(
                "currentQuestion",
                0
            );

    }

    public static function isLastQuestion(): bool
    {

        return
            self::current()
            >=
            count(
                SessionService::get(
                    "questions",
                    []
                )
            ) - 1;

    }

    public static function reset(): void
    {

        SessionService::set(
            "currentQuestion",
            0
        );

    }

}
