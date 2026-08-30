<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\View;

class QuizNavigationService
{
    public static function next(): void
    {
        if (SessionService::get('quiz_completed', false) === true
            || SessionService::has('attempt_persisted')) {
            Response::redirect('/quiz?action=finish');
        }

        $questions =
            SessionService::get(
                "questions",
                []
            );

        if (!is_array($questions) || empty($questions)) {
            Response::redirect('/quiz');
        }

        $current = self::current() + 1;

        if ($current >= count($questions) || !is_array($questions[$current] ?? null)) {
            Response::redirect('/quiz?action=finish');
        }

        SessionService::set("currentQuestion", $current);

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
        $questions =
            SessionService::get(
                "questions",
                []
            );

        if (empty($questions)) {
            return false;
        }

        return
            self::current()
            >=
            count($questions) - 1;
    }

    public static function reset(): void
    {
        SessionService::set(
            "currentQuestion",
            0
        );
    }
}
