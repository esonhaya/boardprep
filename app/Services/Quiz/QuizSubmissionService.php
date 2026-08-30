<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

class QuizSubmissionService
{
    public static function submit(): void
    {
        if (SessionService::has("attempt_persisted")) {
            Response::redirect("/quiz?action=finish");
        }

        $questions =
            SessionService::get(
                "questions",
                []
            );

        if (!is_array($questions)) {
            Response::redirect("/quiz");
        }

        $questions = array_values($questions);

        if (!QuizNavigationService::isCurrentValid($questions)) {
            \App\Services\Quiz\Start\QuizStartSessionWriter::clear();
            SessionService::flash('error', 'That quiz session was stale or invalid. Please start a new quiz.');
            Response::redirect('/quiz');
        }

        $current =
            QuizNavigationService::current();

        $question =
            $questions[$current] ?? null;

        if (!\App\Services\Quiz\Session\QuizSessionQuestion::isCurrent($question)) {
            \App\Services\Quiz\Start\QuizStartSessionWriter::clear();
            SessionService::flash('error', 'That quiz session was stale or invalid. Please start a new quiz.');
            Response::redirect('/quiz');
        }

        $postedQuestionId = Request::input("question_id");
        if ($postedQuestionId !== null && (
            !is_scalar($postedQuestionId)
            || (string) $postedQuestionId !== self::questionId($question)
        )) {
            SessionService::flash("error", "This quiz question is no longer active.");
            self::renderQuestion($question, $current, count($questions));
            return;
        }

        $answer =
            Request::input(
                "answer"
            );

        if ($answer === null || !is_scalar($answer)) {

            SessionService::flash(
                "error",
                "Please select an answer before continuing."
            );

            self::renderQuestion(
                $question,
                $current,
                count($questions)
            );

            return;
        }

        if (!self::storeAnswer(
            $question,
            (string) $answer
        )) {
            self::storeFeedback($question, self::existingAnswer($question));
            self::renderQuestion($question, $current, count($questions));
            return;
        }

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
