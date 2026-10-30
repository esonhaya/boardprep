<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningProgressService;
use App\Services\Learning\CompletedAttemptHistoryFilter;
use App\Services\Learning\MistakeRecoveryResultService;
use App\Services\Learning\NotesPracticeResultService;
use App\Services\Quiz\HistoricalQuizResultService;
use App\Services\Study\StudyLibraryService;

final class HistoryController
{
    private const HISTORY_PAGE_SIZE = 20;

    public static function index(): void
    {
        $allAttempts = LearningHistoryService::all();
        $historyFilters = CompletedAttemptHistoryFilter::apply(
            $allAttempts,
            Request::queryString('board'),
            Request::queryString('subject')
        );
        $attempts = $historyFilters['attempts'];
        $total = count($attempts);
        $pages = max(1, (int) ceil($total / self::HISTORY_PAGE_SIZE));
        $rawPage = Request::queryString('page', '1');
        $requestedPage = preg_match('/\A[1-9]\d{0,5}\z/', $rawPage) === 1 ? (int) $rawPage : 1;
        $page = min($requestedPage, $pages);
        $pageAttempts = array_slice($attempts, ($page - 1) * self::HISTORY_PAGE_SIZE, self::HISTORY_PAGE_SIZE);
        $filterParameters = [];
        if ($historyFilters['board_filter'] !== '') $filterParameters['board'] = $historyFilters['board_filter'];
        if ($historyFilters['subject_filter'] !== '') $filterParameters['subject'] = $historyFilters['subject_filter'];
        $historyFilterQuery = http_build_query($filterParameters, '', '&', PHP_QUERY_RFC3986);
        $returnParameters = ['page' => $page] + $filterParameters;
        $historyReturnUrl = '/history?' . http_build_query($returnParameters, '', '&', PHP_QUERY_RFC3986);
        View::render("history/index", [
            "pageTitle" => "Quiz History",
            "history" => $pageAttempts,
            "summary" => LearningProgressService::build($attempts),
            "historyPage" => $page,
            "historyPages" => $pages,
            "historyTotal" => $total,
            "historyAllTotal" => count($allAttempts),
            "historyPageSize" => self::HISTORY_PAGE_SIZE,
            "historyBoardOptions" => $historyFilters['board_options'],
            "historySubjectOptions" => $historyFilters['subject_options'],
            "historyBoardFilter" => $historyFilters['board_filter'],
            "historySubjectFilter" => $historyFilters['subject_filter'],
            "historyFiltersActive" => $historyFilters['has_filters'],
            "historyFiltersAdjusted" => $historyFilters['adjusted'],
            "historyFilterQuery" => $historyFilterQuery,
            "historyReturnUrl" => $historyReturnUrl,
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

        $noteContext = is_array($result['attempt']['notes_practice'] ?? null)
            ? $result['attempt']['notes_practice']
            : null;
        $recoveryContext = is_array($result['attempt']['mistake_recovery'] ?? null)
            ? $result['attempt']['mistake_recovery']
            : null;
        $notesPracticeReview = $noteContext !== null
            ? NotesPracticeResultService::create($noteContext, $result['review'])
            : null;
        $requestedReturnUrl = Request::queryString('return_to');
        $learnerHistory = $requestedReturnUrl !== '' || $recoveryContext !== null
            ? LearningHistoryService::all()
            : [];
        $historyReturnUrl = $requestedReturnUrl === ''
            ? '/history'
            : CompletedAttemptHistoryFilter::safeReturnUrl(
                $learnerHistory,
                $requestedReturnUrl,
                self::HISTORY_PAGE_SIZE
            );
        $actions = $result['actions'];
        foreach ($actions as &$action) {
            if (is_array($action) && ($action['label'] ?? '') === 'Back to History') {
                $action['url'] = $historyReturnUrl;
            }
        }
        unset($action);
        $markedQuestionIds = HistoricalQuizResultService::markedQuestionIds(
            $result['attempt'],
            $result['review']
        );
        $reviewFilter = Request::queryString('review_filter', 'all');
        $validFilters = ['all', 'incorrect', 'unanswered', 'correct'];
        if ($markedQuestionIds !== null) {
            $validFilters[] = 'marked';
        }
        if (!in_array($reviewFilter, $validFilters, true)) {
            $reviewFilter = 'all';
        }
        if ($notesPracticeReview !== null && ($notesPracticeReview['saved_note_available'] ?? false) !== true) {
            $actions = array_values(array_filter($actions, static fn(mixed $action): bool => !is_array($action)
                || !str_starts_with((string) ($action['url'] ?? ''), '/my-notes?note=')));
        }

        $reviewFilterParameters = ['id' => $attemptId];
        if ($historyReturnUrl !== '/history') {
            $reviewFilterParameters['return_to'] = $historyReturnUrl;
        }

        View::render('quiz/result', [
            'pageTitle' => 'Historical Quiz Result',
            'summary' => $result['summary'],
            'review' => $result['review'],
            'reviewFilter' => $reviewFilter,
            'markedQuestionIds' => $markedQuestionIds ?? [],
            'markedReviewAvailable' => $markedQuestionIds !== null,
            'reviewFilterBaseUrl' => '/history/result?' . http_build_query($reviewFilterParameters, '', '&', PHP_QUERY_RFC3986),
            'studyReferencesByQuestion' => StudyLibraryService::forQuestions(array_map(
                static fn(mixed $item): mixed => is_array($item) && is_array($item['question'] ?? null)
                    ? ($item['question']['id'] ?? null)
                    : null,
                $result['review']
            ),
                is_scalar($result['attempt']['board'] ?? null) ? (string) $result['attempt']['board'] : null,
                is_scalar($result['attempt']['subject'] ?? null) ? (string) $result['attempt']['subject'] : null
            ),
            'actions' => $actions,
            'historical' => true,
            'historyReturnUrl' => $historyReturnUrl,
            'completedAt' => $result['completedAt'],
            'historicalContext' => $result['attempt'],
            'reviewUnavailable' => $result['reviewUnavailable'],
            'notesPracticeReview' => $notesPracticeReview,
            'mistakeRecovery' => $recoveryContext !== null
                ? MistakeRecoveryResultService::create($recoveryContext, $result['review'], $learnerHistory)
                : null,
        ]);
    }
}
