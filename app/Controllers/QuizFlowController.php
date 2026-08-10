<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

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
        $questions = \SessionService::get(
            'questions',
            []
        );

        if (empty($questions)) {
            Response::redirect('/quiz');
            return;
        }

        $result = \QuizResultService::build();

        View::render(
            'quiz/result',
            [
                'summary' => $result['summary'] ?? [],
                'review' => $result['review'] ?? [],
            ]
        );
    }
}
