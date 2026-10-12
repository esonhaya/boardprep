<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\Request;
use App\Core\View;
use App\Services\Quiz\Session\QuizSessionQuestion;
use App\Services\Quiz\Session\QuizSessionRecoveryService;

class QuizNavigationService
{
    public static function next(): void
    {
        self::redirectIfCompleted();
        $questions = self::sessionQuestions();
        if (!self::isCurrentValid($questions)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }
        if (SessionService::get('mode', 'practice') === 'exam'
            && self::current() >= count($questions) - 1) {
            Response::redirect('/quiz?action=review', 303);
        }
        $current = self::nextQuestionIndex($questions);

        SessionService::set("currentQuestion", $current);
        self::renderNextQuestion($questions, $current);
    }

    public static function previous(): void
    {
        self::redirectIfCompleted();
        $questions = self::sessionQuestions();
        if (!self::isCurrentValid($questions)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }
        $current = max(0, self::current() - 1);
        SessionService::set('currentQuestion', $current);
        self::renderNextQuestion($questions, $current);
    }

    public static function jump(): void
    {
        self::redirectIfCompleted();
        if (SessionService::get('mode', 'practice') !== 'exam') {
            Response::redirect('/quiz');
        }
        $questions = self::sessionQuestions();
        $rawIndex = Request::input('index');
        if (!is_scalar($rawIndex) || preg_match('/^\\d{1,4}$/', (string) $rawIndex) !== 1) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }
        $current = (int) $rawIndex;
        if ($current < 0 || $current >= count($questions)
            || !QuizSessionQuestion::isCurrent($questions[$current] ?? null)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }
        SessionService::set('currentQuestion', $current);
        self::renderNextQuestion($questions, $current);
    }

    public static function renderCurrent(): void
    {
        self::redirectIfCompleted();
        $questions = self::sessionQuestions();
        if (!self::isCurrentValid($questions)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }
        $current = self::current();
        if (!QuizSessionQuestion::isCurrent($questions[$current] ?? null)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }
        self::renderNextQuestion($questions, $current);
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

    private static function redirectIfCompleted(): void
    {
        if (SessionService::get('quiz_completed', false) === true
            || SessionService::has('attempt_persisted')) {
            Response::redirect('/quiz?action=result');
        }
    }

    private static function sessionQuestions(): array
    {
        $questions = SessionService::get("questions", []);
        if (!is_array($questions) || empty($questions)) {
            Response::redirect('/quiz');
        }

        return array_values($questions);
    }

    private static function nextQuestionIndex(array $questions): int
    {
        if (!self::isCurrentValid($questions)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }

        $current = self::current() + 1;
        if ($current >= count($questions)) {
            // Exam-mode submission reaches this boundary through a browser
            // redirect. Persist the result before landing on the idempotent
            // result reader; the old GET-only finish redirect could discard
            // the completed attempt because that action accepts POST only.
            \QuizResultService::build();
            Response::redirect('/quiz?action=result');
        }

        if (!\App\Services\Quiz\Session\QuizSessionQuestion::isCurrent($questions[$current] ?? null)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }

        return $current;
    }

    private static function renderNextQuestion(array $questions, int $current): void
    {
        SessionService::remove("feedback");
        View::render("quiz/index", [
            "question" => $questions[$current],
            "current" => $current,
            "total" => count($questions),
            "mode" => SessionService::get("mode", "practice"),
            "feedback" => null,
        ]);
    }
}
