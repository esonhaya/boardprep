<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Quiz\Session\QuizSessionRecoveryService;

class QuizSubmissionService
{
    public static function submit(): void
    {
        self::redirectIfAlreadyPersisted();

        $questions = self::sessionQuestions();
        [$question, $current] = self::activeQuestion($questions);

        if (self::postedQuestionIsStale($question)) {
            self::renderStaleQuestion($question, $current, count($questions));
            return;
        }

        $answer = Request::input("answer");
        if (!is_scalar($answer)) {
            self::renderMissingAnswer($question, $current, count($questions));
            return;
        }

        if (!self::storeAnswer($question, (string) $answer)) {
            self::storeFeedback($question, self::existingAnswer($question));
            self::renderQuestion($question, $current, count($questions));
            return;
        }

        if (self::isPracticeMode()) {
            self::renderPracticeFeedback($question, $current, $answer, count($questions));
            return;
        }

        QuizNavigationService::next();
    }

    private static function redirectIfAlreadyPersisted(): void
    {
        if (SessionService::has("attempt_persisted")) {
            Response::redirect("/quiz?action=finish");
        }
    }

    private static function sessionQuestions(): array
    {
        $questions = SessionService::get("questions", []);
        if (!is_array($questions)) {
            Response::redirect("/quiz");
        }

        return array_values($questions);
    }

    private static function activeQuestion(array $questions): array
    {
        if (!QuizNavigationService::isCurrentValid($questions)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }

        $current = QuizNavigationService::current();
        $question = $questions[$current] ?? null;
        if (!\App\Services\Quiz\Session\QuizSessionQuestion::isCurrent($question)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }

        return [$question, $current];
    }

    private static function postedQuestionIsStale(array $question): bool
    {
        $postedQuestionId = Request::input("question_id");

        return $postedQuestionId !== null
            && (!is_scalar($postedQuestionId)
                || (string) $postedQuestionId !== self::questionId($question));
    }

    private static function renderStaleQuestion(array $question, int $current, int $total): void
    {
        SessionService::flash("error", "This quiz question is no longer active.");
        self::renderQuestion($question, $current, $total);
    }

    private static function renderMissingAnswer(array $question, int $current, int $total): void
    {
        SessionService::flash("error", "Please select an answer before continuing.");
        self::renderQuestion($question, $current, $total);
    }

    private static function isPracticeMode(): bool
    {
        return SessionService::get("mode", "practice") === "practice";
    }

    private static function renderPracticeFeedback(
        array $question,
        int $current,
        mixed $answer,
        int $total
    ): void {
        self::storeFeedback($question, $answer);
        self::renderQuestion($question, $current, $total, "practice");
    }

    private static function storeAnswer(
        array $question,
        string $answer
    ): bool {

        $answers =
            SessionService::get(
                "answers",
                []
            );
        if (!is_array($answers)) {
            $answers = [];
        }

        $id = self::questionId($question);
        if ($id === "" || array_key_exists($id, $answers)) {
            return false;
        }

        $answers[$id] = $answer;

        SessionService::set(
            "answers",
            $answers
        );

        return true;
    }

    private static function existingAnswer(array $question): ?string
    {
        $answers = SessionService::get("answers", []);
        $id = self::questionId($question);
        $answer = is_array($answers) ? ($answers[$id] ?? null) : null;
        return is_scalar($answer) ? (string) $answer : null;
    }

    private static function questionId(array $question): string
    {
        $id = $question["id"] ?? null;
        return is_scalar($id) ? trim((string) $id) : "";
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
