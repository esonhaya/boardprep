<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Quiz\QuizResultActionService;
use App\Services\Quiz\Start\QuizStartSessionWriter;
use App\Services\Quiz\Start\QuizStartInputNormalizer;
use App\Services\Board\BoardViewService;
use App\Services\Shared\CsrfTokenService;
use App\Services\Quiz\LET\LETVariantRegistry;
use App\Services\Study\StudyLibraryService;

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
            ? ['start', 'submit', 'next', 'finish']
            : ['', 'start', 'result'];

        if (!in_array($action, $allowed, true)) {
            self::rejectAction();
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
                'studyReferencesByQuestion' => $studyReferencesByQuestion,
                'actions' => QuizResultActionService::build(
                    $session,
                    $summary,
                    $result['review'] ?? []
                ),
            ]
        );
    }
}
