<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Quiz\Session\QuizSessionRecoveryService;
use App\Services\Study\StudyLibraryService;

class QuizSubmissionService
{
    public static function submit(): void
    {
        self::redirectIfAlreadyPersisted();

        $questions = self::sessionQuestions();
        [$question, $current] = self::activeQuestion($questions);

        if (self::postedQuestionIsStale($question)) {
            $savedQuestionIndex = self::previouslySavedExamSubmission($questions);
            if ($savedQuestionIndex !== null) {
                $status = sprintf(
                    'Your answer for question %d was already recorded. This session is now on question %d of %d.',
                    $savedQuestionIndex + 1,
                    $current + 1,
                    count($questions)
                );
                self::renderQuestion($question, $current, count($questions), null, $status);
                return;
            }
            if (self::saveDelayedExamSubmission($questions, $question, $current)) {
                return;
            }
            self::renderStaleQuestion($question, $current, count($questions));
            return;
        }

        $answer = Request::input("answer");
        if (!is_scalar($answer)) {
            self::renderMissingAnswer($question, $current, count($questions));
            return;
        }
        $answer = self::validAnswerFor($question, $answer);
        if ($answer === null) {
            self::renderInvalidAnswer($question, $current, count($questions));
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

        QuizNavigationService::next(true);
    }

    private static function redirectIfAlreadyPersisted(): void
    {
        if (SessionService::has("attempt_persisted")) {
            Response::redirect("/quiz?action=result");
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
        if (self::isExamMode() && (!is_scalar($postedQuestionId) || trim((string) $postedQuestionId) === '')) {
            return true;
        }

        return $postedQuestionId !== null
            && (!is_scalar($postedQuestionId)
                || (string) $postedQuestionId !== self::questionId($question));
    }

    /** Confirm a retried request only when its exact answer is already stored. */
    private static function previouslySavedExamSubmission(array $questions): ?int
    {
        if (!self::isExamMode()) {
            return null;
        }

        $postedQuestionId = Request::input('question_id');
        $postedAnswer = Request::input('answer');
        if (!is_scalar($postedQuestionId) || !is_scalar($postedAnswer)) {
            return null;
        }

        $questionId = trim((string) $postedQuestionId);
        $answer = trim((string) $postedAnswer);
        if ($questionId === '' || $answer === '') {
            return null;
        }

        $targetQuestion = null;
        foreach ($questions as $candidate) {
            if (is_array($candidate) && self::questionId($candidate) === $questionId) {
                $targetQuestion = $candidate;
                break;
            }
        }
        $answer = $targetQuestion === null ? null : self::validAnswerFor($targetQuestion, $answer);
        $answers = SessionService::get('answers', []);
        if ($answer === null || !is_array($answers) || !is_scalar($answers[$questionId] ?? null)
            || strtoupper(trim((string) $answers[$questionId])) !== $answer) {
            return null;
        }

        foreach ($questions as $index => $question) {
            if (is_array($question) && self::questionId($question) === $questionId) {
                return $index;
            }
        }

        return null;
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

    /** Save a delayed exam request only to its own unanswered session question. */
    private static function saveDelayedExamSubmission(array $questions, array $activeQuestion, int $current): bool
    {
        if (!self::isExamMode()) {
            return false;
        }

        $postedQuestionId = Request::input('question_id');
        $postedAnswer = Request::input('answer');
        if (!is_scalar($postedQuestionId) || !is_scalar($postedAnswer)) {
            return false;
        }
        $postedQuestionId = trim((string) $postedQuestionId);
        if ($postedQuestionId === '') {
            return false;
        }

        foreach ($questions as $index => $targetQuestion) {
            if (!is_array($targetQuestion) || self::questionId($targetQuestion) !== $postedQuestionId) {
                continue;
            }
            $answer = self::validAnswerFor($targetQuestion, $postedAnswer);
            if ($answer === null) {
                return false;
            }

            $answers = SessionService::get('answers', []);
            if (!is_array($answers)) {
                return false;
            }
            if (array_key_exists($postedQuestionId, $answers)) {
                $saved = is_scalar($answers[$postedQuestionId])
                    ? strtoupper(trim((string) $answers[$postedQuestionId]))
                    : '';
                $status = $saved === $answer
                    ? sprintf('Your answer for question %d was saved. Your current question remains %d of %d.', $index + 1, $current + 1, count($questions))
                    : sprintf('The previously saved answer for question %d was kept. Your current question remains %d of %d.', $index + 1, $current + 1, count($questions));
                self::renderQuestion($activeQuestion, $current, count($questions), null, $status);
                return true;
            }

            if (!self::storeAnswer($targetQuestion, $answer)) {
                return false;
            }
            $status = sprintf(
                'Your answer for question %d was saved. Your current question remains %d of %d.',
                $index + 1,
                $current + 1,
                count($questions)
            );
            self::renderQuestion($activeQuestion, $current, count($questions), null, $status);
            return true;
        }

        return false;
    }

    private static function validAnswerFor(array $question, mixed $answer): ?string
    {
        if (!is_scalar($answer) || !is_array($question['choices'] ?? null)) {
            return null;
        }
        $answer = strtoupper(trim((string) $answer));
        if (preg_match('/^[A-Z]$/', $answer) !== 1) {
            return null;
        }
        $choiceIndex = ord($answer) - ord('A');
        return $choiceIndex >= 0 && $choiceIndex < count($question['choices']) ? $answer : null;
    }

    private static function renderInvalidAnswer(array $question, int $current, int $total): void
    {
        SessionService::flash('error', 'That answer option is not available for this question. Please choose one of the listed options.');
        self::renderQuestion($question, $current, $total);
    }

    private static function isPracticeMode(): bool
    {
        return SessionService::get("mode", "practice") === "practice";
    }

    private static function isExamMode(): bool
    {
        return SessionService::get('mode', 'practice') === 'exam';
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
        $alreadyAnswered = array_key_exists($id, $answers);
        if ($id === "" || ($alreadyAnswered && !self::isExamMode())) {
            return false;
        }

        $changed = !$alreadyAnswered
            || !is_scalar($answers[$id])
            || (string) $answers[$id] !== $answer;
        $answers[$id] = $answer;

        SessionService::set(
            "answers",
            $answers
        );

        if (self::isExamMode() && $changed) {
            $revision = SessionService::get('exam_answer_revision', 0);
            $revision = is_int($revision) && $revision >= 0 ? $revision : 0;
            SessionService::set('exam_answer_revision', $revision + 1);
            SessionService::remove('exam_reviewed_revision');
        }

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
        ?string $mode = null,
        ?string $answerSaveStatus = null
    ): void {

        $quizContext = SessionService::get('quiz_session', []);
        $quizContext = is_array($quizContext) ? $quizContext : [];
        $studyReferences = [];
        if (SessionService::get('feedback') !== null) {
            $studyReferences = StudyLibraryService::forQuestion(
                self::questionId($question),
                is_scalar($quizContext['board'] ?? null) ? (string) $quizContext['board'] : null,
                is_scalar($quizContext['subject'] ?? null) ? (string) $quizContext['subject'] : null
            );
        }

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
                    ),

                "answerSaveStatus" => $answerSaveStatus,

                "studyReferences" => $studyReferences,
            ]
        );
    }
}
