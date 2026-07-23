<?php

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

            header(
                "Location: ?page=quiz&action=finish"
            );

            exit;

        }

        $answer =
            $_POST["answer"] ?? null;

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
