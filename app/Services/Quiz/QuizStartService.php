<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuizStartService
{
    public static function start(): void
    {

        $count =
            (int) ($_GET["count"] ?? 10);

        $difficulty =
            $_GET["difficulty"] ?? "mixed";

        $mode =
            $_GET["mode"] ?? "practice";

        $adaptive =
            isset($_GET["adaptive"]);

        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

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

        if (empty($questions)) {

            FlashMessageService::error(
                "No questions matched the selected quiz settings."
            );

            redirect("/quiz");

            return;

        }

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
                    $questions[0],

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
