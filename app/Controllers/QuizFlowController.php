<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Quiz\QuizResultActionService;
use App\Services\Quiz\Start\QuizStartSessionWriter;

final class QuizFlowController
{
    public static function handle(): void
    {
        $action = trim(
            (string) Request::query(
                'action',
                Request::input('action', '')
            )
        );

        switch ($action) {
            case 'start':
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

            default:
                self::settings();
                return;
        }
    }

    private static function settings(): void
    {
        View::render(
            'quiz/settings',
            [
                'pageTitle' => 'Quiz',
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

        $result = \QuizResultService::build();

        $summary = $result['summary'] ?? [];
        $session = \SessionService::get('quiz_session', []);

        View::render(
            'quiz/result',
            [
                'summary' => $summary,
                'review' => $result['review'] ?? [],
                'actions' => QuizResultActionService::build(
                    $session,
                    $summary
                ),
            ]
        );
    }
}
