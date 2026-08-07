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

            self::renderQuestion(
                $question,
                $current,
                count($questions)
            );

            return;
        }

        self::storeAnswer(
            $question,
            $answer
        );

        $mode =
            SessionService::get(
                "mode",
                "practice"
            );

        if ($mode === "practice") {

            self::storeFeedback(
                $question,
                $answer
            );

            self::renderQuestion(
                $question,
                $current,
                count($questions),
                $mode
            );

            return;
        }

        QuizNavigationService::next();
    }

    private static function storeAnswer(
        array $question,
        mixed $answer
    ): void {

        $answers =
            SessionService::get(
                "answers",
                []
            );

        $answers[$question["id"]] =
            $answer;

        SessionService::set(
            "answers",
            $answers
        );
    }

    private static function storeFeedback(
        array $question,
        mixed $answer
    ): void {

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
    }

    private static function renderQuestion(
        array $question,
        int $current,
        int $total,
        ?string $mode = null
    ): void {

        View::render(
            "quiz/index",
            [
                "question" =>
                    $question,

                "current" =>
                    $current,

                "total" =>
                    $total,

                "mode" =>
                    $mode
                    ??
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
    }
}
