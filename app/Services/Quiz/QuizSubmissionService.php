<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;

class QuizSubmissionService
{
    public static function submit(): void
    {
        $questions =
            SessionService::get(
                "questions",
                []
            );

        $current =
            QuizNavigationService::current();

        $question =
            $questions[$current] ?? null;

        if (!$question) {

            Response::redirect(
                "/quiz/finish"
            );

        }

        $answer =
            Request::input(
                "answer"
            );

        if ($answer === null) {

            FlashMessageService::error(
                "Please select an answer before continuing."
            );

            View::render(
                "quiz/index",
                [
                    "question" =>
                        $question,

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
                        SessionService::get(
                            "feedback"
                        )
                ]
            );

            return;

        }

        $answers =
            SessionService::get(
                "answers",
                []
            );

        $answers[
            $question["id"]
        ] = $answer;

        SessionService::set(
            "answers",
            $answers
        );

        $mode =
            SessionService::get(
                "mode",
                "practice"
            );

        if ($mode === "practice") {

            SessionService::set(
                "feedback",
                [
                    "correct" =>
                        QuizScoringService::checkAnswer(
                            $question,
                            $answer
                        )
                ]
            );

            View::render(
                "quiz/index",
                [
                    "question" =>
                        $question,

                    "current" =>
                        $current,

                    "total" =>
                        count($questions),

                    "mode" =>
                        $mode,

                    "feedback" =>
                        SessionService::get(
                            "feedback"
                        )
                ]
            );

            return;

        }

        QuizNavigationService::next();
    }
}
