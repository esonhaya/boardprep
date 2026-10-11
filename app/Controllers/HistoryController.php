<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningProgressService;
use App\Services\Quiz\HistoricalQuizResultService;
use App\Services\Study\StudyLibraryService;

final class HistoryController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        View::render("history/index", [
            "pageTitle" => "Quiz History",
            "history" => $attempts,
            "summary" => LearningProgressService::build($attempts),
        ]);
    }

    public static function result(): void
    {
        $attemptId = Request::queryString('id');
        $result = HistoricalQuizResultService::findForLearner($attemptId);

        if ($result === null) {
            \SessionService::flash(
                'error',
                'That quiz result is unavailable or no longer belongs to this learner.'
            );
            Response::redirect('/history', 303);
        }

        View::render('quiz/result', [
            'pageTitle' => 'Historical Quiz Result',
            'summary' => $result['summary'],
            'review' => $result['review'],
            'studyReferencesByQuestion' => StudyLibraryService::forQuestions(array_map(
                static fn(mixed $item): mixed => is_array($item) && is_array($item['question'] ?? null)
                    ? ($item['question']['id'] ?? null)
                    : null,
                $result['review']
            )),
            'actions' => $result['actions'],
            'historical' => true,
            'completedAt' => $result['completedAt'],
            'historicalContext' => $result['attempt'],
            'reviewUnavailable' => $result['reviewUnavailable'],
        ]);
    }
}
