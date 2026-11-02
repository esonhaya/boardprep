<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\Request;
use App\Core\View;
use App\Services\Quiz\Session\QuizSessionQuestion;
use App\Services\Quiz\Session\QuizSessionRecoveryService;

class QuizNavigationService
{
    public static function next(bool $fromAnswerSubmission = false): void
    {
        self::redirectIfCompleted();
        $questions = self::sessionQuestions();
        if (!self::isCurrentValid($questions)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }
        if (!$fromAnswerSubmission && self::rejectStaleExamNavigation($questions)) {
            return;
        }
        if (SessionService::get('mode', 'practice') === 'exam'
            && self::current() >= count($questions) - 1) {
            Response::redirect('/quiz?action=review', 303);
        }
        $current = self::nextQuestionIndex($questions);

        SessionService::set("currentQuestion", $current);
        $expectedRevision = $fromAnswerSubmission
            ? self::sessionDraftRevision()
            : self::postedDraftRevision();
        if (!self::persistDurableNavigation($expectedRevision)) {
            $current = self::current();
        }
        self::renderNextQuestion($questions, $current);
    }

    public static function previous(): void
    {
        self::redirectIfCompleted();
        $questions = self::sessionQuestions();
        if (!self::isCurrentValid($questions)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }
        if (self::rejectStaleExamNavigation($questions)) {
            return;
        }
        $current = max(0, self::current() - 1);
        SessionService::set('currentQuestion', $current);
        if (!self::persistDurableNavigation(self::postedDraftRevision())) {
            $current = self::current();
        }
        self::renderNextQuestion($questions, $current);
    }

    public static function jump(): void
    {
        self::redirectIfCompleted();
        if (SessionService::get('mode', 'practice') !== 'exam') {
            Response::redirect('/quiz');
        }
        $questions = self::sessionQuestions();
        if (!self::isCurrentValid($questions)) {
            QuizSessionRecoveryService::abandonInvalidSession();
        }
        if (self::rejectStaleExamNavigation($questions)) {
            return;
        }
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
        if (!self::persistDurableNavigation(self::postedDraftRevision())) {
            $current = self::current();
        }
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

    /**
     * Ignore an exam navigation form rendered for a question that is no longer
     * active. This prevents queued taps from skipping past an answer save.
     */
    private static function rejectStaleExamNavigation(array $questions): bool
    {
        if (SessionService::get('mode', 'practice') !== 'exam') {
            return false;
        }

        $current = self::current();
        $activeQuestion = $questions[$current] ?? null;
        $activeId = is_array($activeQuestion) && is_scalar($activeQuestion['id'] ?? null)
            ? trim((string) $activeQuestion['id'])
            : '';
        $submittedId = Request::input('from_question_id');
        if ($activeId !== '' && is_scalar($submittedId) && trim((string) $submittedId) === $activeId) {
            return false;
        }

        SessionService::flash('error', 'Your exam moved to another question before that navigation request arrived. Your current question was kept.');
        self::renderNextQuestion($questions, $current);
        return true;
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

    private static function persistDurableNavigation(int $expectedRevision): bool
    {
        $quizSession = SessionService::get('quiz_session', []);
        if (!is_array($quizSession)
            || !is_scalar($quizSession['durable_draft_id'] ?? null)
            || trim((string) ($quizSession['durable_draft_id'] ?? '')) === '') {
            return true;
        }
        $sessionId = is_scalar($quizSession['id'] ?? null) ? trim((string) $quizSession['id']) : '';
        try {
            (new \App\Services\Quiz\ExamDraftService())->saveCurrentSessionState(
                $expectedRevision,
                $sessionId
            );
            return true;
        } catch (\Throwable $exception) {
            $restored = false;
            try {
                $restored = (new \App\Services\Quiz\ExamDraftService())->restoreAuthoritativeSessionState();
            } catch (\Throwable) {
                // Keep the navigation failure fail-closed if durable storage is unavailable.
            }
            if (!$restored) {
                SessionService::flash('error', 'This exam session is no longer current. Open the quiz page to recover the saved attempt.');
                Response::redirect('/quiz?action=start', 303);
            }
            SessionService::flash('error', 'This exam changed in another request. The saved answers were kept; refresh before continuing.');
            return false;
        }
    }

    private static function postedDraftRevision(): int
    {
        $raw = Request::input('draft_revision');
        return is_scalar($raw) && preg_match('/\A\d{1,10}\z/', (string) $raw) === 1
            ? (int) $raw
            : -1;
    }

    private static function sessionDraftRevision(): int
    {
        $session = SessionService::get('quiz_session', []);
        return is_array($session) && is_int($session['durable_draft_revision'] ?? null)
            ? $session['durable_draft_revision']
            : -1;
    }
}
