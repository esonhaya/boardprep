<?php

class QuizStartService
{

    public static function start(): void
    {

        $count =
            (int)($_GET["count"] ?? 10);

        $difficulty =
            $_GET["difficulty"] ?? "mixed";

        $mode =
            $_GET["mode"] ?? "practice";

        $adaptive =
            isset($_GET["adaptive"]);

        $questions =
            QuestionRepository::all();

        $questions =
            QuizGenerationService::generate(
                $questions,
                [
                    "blueprint" =>
                        $_GET["exam"] ?? "LET",

                    "difficulty" =>
                        $difficulty,

                    "shuffle" =>
                        true,

                    "limit" =>
                        $count,

                    "adaptive" =>
                        $adaptive
                ]
            );

        SessionService::set(
            "questions",
            $questions
        );

        SessionService::set(
            "answers",
            []
        );

        SessionService::set(
            "mode",
            $mode
        );

        QuizNavigationService::reset();

        View::render(
            "quiz/index",
            [
                "question" =>
                    $questions[0] ?? null,

                "current" =>
                    0,

                "total" =>
                    count($questions),

                "mode" =>
                    $mode,

                "feedback" =>
                    null
            ]
        );

    }

}
