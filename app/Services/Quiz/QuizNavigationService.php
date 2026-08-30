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

        $questions = array_values($questions);

        if (!self::isCurrentValid($questions)) {
            self::abandonInvalidSession();
        }

        $current = self::current() + 1;

        if ($current >= count($questions)) {
            Response::redirect('/quiz?action=finish');
        }

        if (!\App\Services\Quiz\Session\QuizSessionQuestion::isCurrent($questions[$current] ?? null)) {
            self::abandonInvalidSession();
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
        $current = SessionService::get("currentQuestion", 0);

        if (is_int($current)) {
            return max(0, $current);
        }

        if (is_string($current) && preg_match('/^\d{1,9}$/', $current) === 1) {
            return (int) $current;
        }

        return 0;
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

    public static function isCurrentValid(array $questions): bool
    {
        $raw = SessionService::get('currentQuestion', 0);
        $current = self::current();

        return (is_int($raw) || (is_string($raw) && preg_match('/^\d{1,9}$/', $raw) === 1))
            && $current < count($questions);
    }

    private static function abandonInvalidSession(): never
    {
        \App\Services\Quiz\Start\QuizStartSessionWriter::clear();
        SessionService::flash('error', 'That quiz session was stale or invalid. Please start a new quiz.');
        Response::redirect('/quiz');
    }
}
