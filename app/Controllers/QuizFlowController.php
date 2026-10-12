<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Quiz\QuizResultActionService;
use App\Services\Quiz\ExamTimerService;
use App\Services\Quiz\Start\QuizStartSessionWriter;
use App\Services\Quiz\Start\QuizStartInputNormalizer;
use App\Services\Board\BoardViewService;
use App\Services\Shared\CsrfTokenService;
use App\Services\Quiz\LET\LETVariantRegistry;
use App\Services\Study\StudyLibraryService;
use App\Services\Question\QuestionIdMigrationService;

final class QuizFlowController
{
    public static function handle(): void
    {
        $method = Request::method();
        $rawAction = $method === 'POST'
            ? Request::input('action', '')
            : Request::query('action', '');
        if (!is_scalar($rawAction)) {
            self::rejectAction();
        }

        $action = trim((string) $rawAction);

        if ($method === 'POST'
            && App::config('environment') === 'production'
            && !CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'Your quiz form expired. Please try again.');
            Response::redirect('/quiz');
        }

        $allowed = $method === 'POST'
            ? ['start', 'submit', 'next', 'previous', 'jump', 'finish', 'timer-check']
            : ['', 'start', 'result', 'review'];

        if (!in_array($action, $allowed, true)) {
            self::rejectAction();
        }

        if ($action !== 'start') {
            ExamTimerService::enforceIfExpired();
        }

        switch ($action) {
            case 'start':
                if ($method === 'GET') {
                    self::settings();
                    return;
                }
                \QuizStartService::start();
                return;

            case 'submit':
                \QuizSubmissionService::submit();
                return;

            case 'next':
                \QuizNavigationService::next();
                return;

            case 'previous':
                \QuizNavigationService::previous();
                return;

            case 'jump':
                \QuizNavigationService::jump();
                return;

            case 'timer-check':
                \QuizNavigationService::renderCurrent();
                return;

            case 'review':
                self::review();
                return;

            case 'finish':
                self::finish();
                return;

            case 'result':
                self::result();
                return;

            default:
                if ($action !== '') {
                    self::rejectAction();
                }
                if (is_array(\SessionService::get('quiz_session'))
                    && is_array(\SessionService::get('questions'))
                    && \SessionService::get('attempt_persisted') !== true) {
                    \QuizNavigationService::renderCurrent();
                    return;
                }
                self::settings();
                return;
        }
    }

    private static function rejectAction(): never
    {
        \SessionService::flash('error', 'That quiz action is invalid or expired. Please try again.');
        Response::redirect('/quiz');
    }

    private static function settings(): void
    {
        $settings = QuizStartInputNormalizer::normalize(Request::query());
        View::render(
            'quiz/settings',
            [
                'pageTitle' => 'Quiz',
                'settings' => $settings,
                'boards' => BoardViewService::all(),
                'letSpecializations' => LETVariantRegistry::specializations(),
            ]
        );
    }

    private static function finish(): void
    {
        if (\SessionService::get('mode', 'practice') === 'exam') {
            $revision = \SessionService::get('exam_answer_revision', 0);
            $reviewedRevision = \SessionService::get('exam_reviewed_revision', null);
            if (!is_int($revision) || $reviewedRevision !== $revision) {
                \SessionService::flash('error', 'Review your answers before submitting the exam simulation.');
                Response::redirect('/quiz?action=review', 303);
            }
        }

        $resultInput = \QuizResultSessionReader::read();
        $questions = $resultInput['questions'];

        if (empty($questions)) {
            QuizStartSessionWriter::clear();
            \SessionService::flash('error', 'That quiz session was stale or invalid. Please start a new quiz.');
            Response::redirect('/quiz');
            return;
        }

        \QuizResultService::build();
        Response::redirect('/quiz?action=result', 303);
    }

    private static function result(): void
    {
        if (!\SessionService::has('attempt_persisted')) {
            \SessionService::flash('error', 'That quiz result is unavailable or expired.');
            Response::redirect('/quiz');
        }

        $result = \QuizResultService::build();

        $summary = $result['summary'] ?? [];
        $review = $result['review'] ?? [];
        $session = \SessionService::get('quiz_session', []);
        $review = QuestionIdMigrationService::canonicalizeReview(
            is_array($review) ? $review : [],
            is_array($session) ? $session : []
        );
        $studyReferencesByQuestion = StudyLibraryService::forQuestions(array_map(
            static fn(mixed $item): mixed => is_array($item) && is_array($item['question'] ?? null)
                ? ($item['question']['id'] ?? null)
                : null,
            $review
        ));

        View::render(
            'quiz/result',
            [
                'summary' => $summary,
                'review' => $review,
                'quizSession' => is_array($session) ? $session : [],
                'studyReferencesByQuestion' => $studyReferencesByQuestion,
                'actions' => QuizResultActionService::build(
                    is_array($session) ? $session : [],
                    $summary,
                    $result['review'] ?? []
                ),
            ]
        );
    }

    private static function review(): void
    {
        if (\SessionService::get('mode', 'practice') !== 'exam') {
            \SessionService::flash('error', 'Answer review is available in an exam simulation.');
            Response::redirect('/quiz?action=start', 303);
        }

        $questions = \SessionService::get('questions', []);
        $answers = \SessionService::get('answers', []);
        if (!is_array($questions) || $questions === [] || !is_array($answers)) {
            \App\Services\Quiz\Session\QuizSessionRecoveryService::abandonInvalidSession();
        }
        $questions = array_values($questions);
        foreach ($questions as $question) {
            if (!\App\Services\Quiz\Session\QuizSessionQuestion::isCurrent($question)) {
                \App\Services\Quiz\Session\QuizSessionRecoveryService::abandonInvalidSession();
            }
        }

        $revision = \SessionService::get('exam_answer_revision', 0);
        $revision = is_int($revision) && $revision >= 0 ? $revision : 0;
        \SessionService::set('exam_reviewed_revision', $revision);
        View::render('quiz/review', [
            'questions' => $questions,
            'answers' => $answers,
            'current' => \QuizNavigationService::current(),
        ]);
    }
}
