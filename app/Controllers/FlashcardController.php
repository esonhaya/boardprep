<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Flashcard\FlashcardService;
use App\Services\Quiz\HistoricalQuizResultService;
use App\Services\Quiz\Result\QuizAnswerStatisticsPlan;
use App\Services\Shared\CsrfTokenService;
use App\Services\Shared\TaxonomyStorageService;

final class FlashcardController
{
    public static function index(): void
    {
        $parsed = self::filters(Request::query());
        $selection = self::emptySelection();
        $error = $parsed['error'];

        if ($error === null) {
            try {
                $selection = self::selection($parsed['mode'], $parsed['filters']);
            } catch (\Throwable) {
                $error = 'Flashcards are temporarily unavailable. Please try again.';
            }
        }

        $boards = $subjects = $topics = [];
        try {
            $boards = self::options(TaxonomyStorageService::boards());
            $subjects = self::options(TaxonomyStorageService::subjects());
            $topics = self::options(TaxonomyStorageService::topics());
        } catch (\Throwable) {
            $error ??= 'Flashcard filters are temporarily unavailable. Please try again.';
        }

        View::render('flashcards/index', [
            'pageTitle' => 'Flashcards | BoardPrep',
            'filters' => $parsed['filters'],
            'boards' => $boards,
            'subjects' => $subjects,
            'topics' => $topics,
            'mode' => $parsed['mode'],
            'selection' => $selection,
            'error' => $error,
        ]);
    }

    public static function review(): void
    {
        $parsed = self::filters(Request::query());
        $filters = $parsed['filters'];
        $mode = $parsed['mode'];
        $card = null;
        $selection = self::emptySelection();
        $error = $parsed['error'];
        $revealed = self::booleanQuery(Request::query('reveal', '0'));

        if ($error === null) {
            try {
                if ($revealed) {
                    $rawCardKey = Request::query('card_key');
                    if (!is_scalar($rawCardKey) || trim((string) $rawCardKey) === '') {
                        throw new \InvalidArgumentException('Missing flashcard.');
                    }
                    $card = self::service()->card((string) $rawCardKey);
                    if (!self::matches($card, $filters) || !self::allowedInMode($card, $mode)) {
                        throw new \InvalidArgumentException('Flashcard does not match the selected filters.');
                    }
                } else {
                    $selection = self::selection($mode, $filters);
                    $card = $selection['cards'][0] ?? null;
                }
            } catch (\Throwable) {
                $error = 'That flashcard is unavailable or no longer eligible.';
            }
        }

        View::render('flashcards/review', [
            'pageTitle' => 'Review Flashcards | BoardPrep',
            'filters' => $filters,
            'mode' => $mode,
            'card' => $card,
            'revealed' => $revealed,
            'selection' => $selection,
            'csrfToken' => CsrfTokenService::token(),
            'error' => $error,
        ]);
    }

    public static function submitReview(): void
    {
        $parsed = self::filters(Request::input());
        $filters = $parsed['filters'];
        $mode = $parsed['mode'];
        $redirect = self::reviewUrl($filters, $mode);

        if ($parsed['error'] !== null) {
            \SessionService::flash('error', (string) $parsed['error']);
            Response::redirect($redirect, 303);
        }

        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'Your review form expired. Please try again.');
            Response::redirect($redirect, 303);
        }

        $cardKey = Request::input('card_key');
        $rating = Request::input('rating');
        if (!is_scalar($cardKey) || !is_scalar($rating)) {
            \SessionService::flash('error', 'That review submission was invalid.');
            Response::redirect($redirect, 303);
        }

        try {
            $card = self::service()->card(trim((string) $cardKey));
            if (!self::matches($card, $filters) || !self::allowedInMode($card, $mode)) {
                throw new \InvalidArgumentException('Card is not in the selected review set.');
            }
            self::service()->review(trim((string) $cardKey), trim((string) $rating));
            \SessionService::flash('success', 'Review saved.');
        } catch (\Throwable) {
            \SessionService::flash('error', 'That card or rating is invalid. No review was saved.');
        }

        Response::redirect($redirect, 303);
    }

    public static function missed(): void
    {
        $historicalAttemptId = Request::query('attempt_id');
        $historicalResult = is_scalar($historicalAttemptId)
            ? HistoricalQuizResultService::findForLearner((string) $historicalAttemptId)
            : null;

        if ($historicalAttemptId !== null) {
            if ($historicalResult === null) {
                \SessionService::flash('error', 'That quiz result is unavailable or no longer belongs to this learner.');
                Response::redirect('/history', 303);
            }
            $missed = HistoricalQuizResultService::missedQuestionIds(
                $historicalResult['review']
            );
            $quizSessionId = 'historical-' . $historicalResult['attempt']['id'];
        } else {
            $result = \QuizResultSessionReader::read();
            if (!\SessionService::has('attempt_persisted') || $result['questions'] === []) {
                \SessionService::flash('error', 'That quiz result is unavailable or expired.');
                Response::redirect('/quiz?action=result', 303);
            }

            $plan = QuizAnswerStatisticsPlan::build($result['questions'], $result['answers']);
            $missed = [];
            foreach ($plan as $entry) {
                if (($entry['correct'] ?? null) === false) {
                    $missed[] = (string) ($entry['question_id'] ?? '');
                }
            }
            $missed = array_values(array_filter($missed));
            $quizSession = $result['session'];
            $quizSessionId = is_scalar($quizSession['id'] ?? null)
                ? trim((string) $quizSession['id'])
                : '';
        }

        if ($missed === []) {
            \SessionService::flash('info', 'There are no missed eligible concepts in this quiz.');
            Response::redirect($historicalAttemptId !== null ? '/history' : '/quiz?action=result', 303);
        }

        if ($quizSessionId === '') {
            \SessionService::flash('error', 'That quiz result is unavailable or expired.');
            Response::redirect($historicalAttemptId !== null ? '/history' : '/quiz?action=result', 303);
        }

        \SessionService::set('flashcard_missed_scope', [
            'quiz_session_id' => $quizSessionId,
            'question_ids' => $missed,
        ]);
        Response::redirect('/flashcards/review?mode=missed', 303);
    }

    private static function service(): FlashcardService
    {
        return App::container()->get(FlashcardService::class);
    }

    /** @return array{filters:array<string,mixed>,error:?string} */
    private static function filters(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $filters = ['include_new' => true];
        $mode = 'normal';
        $error = null;
        try {
            $allowed = [
                'board' => self::ids(TaxonomyStorageService::boards()),
                'subject' => self::ids(TaxonomyStorageService::subjects()),
                'topic' => self::ids(TaxonomyStorageService::topics()),
            ];
        } catch (\Throwable) {
            return [
                'filters' => ['include_new' => false, 'board' => '__invalid__'],
                'error' => 'Flashcard filters are temporarily unavailable. Please try again.',
            ];
        }

        if (array_key_exists('mode', $input)) {
            if (!is_scalar($input['mode']) || !in_array((string) $input['mode'], ['normal', 'weakness', 'missed'], true)) {
                return ['filters' => $filters, 'mode' => 'normal', 'error' => 'Please choose a valid flashcard review mode.'];
            }
            $mode = (string) $input['mode'];
        }

        foreach (['board', 'subject', 'topic'] as $name) {
            if (!array_key_exists($name, $input) || $input[$name] === '') {
                continue;
            }
            if (!is_scalar($input[$name])) {
                $error = 'Please choose a valid flashcard filter.';
                $filters[$name] = '__invalid__';
                continue;
            }
            $value = strtolower(trim((string) $input[$name]));
            if (!in_array($value, $allowed[$name], true)) {
                $error = 'Please choose a valid flashcard filter.';
                $filters[$name] = '__invalid__';
                continue;
            }
            $filters[$name] = $value;
        }

        if (array_key_exists('include_new', $input)) {
            if (!is_scalar($input['include_new']) || !in_array((string) $input['include_new'], ['0', '1'], true)) {
                $error = 'Please choose a valid new-card setting.';
                $filters['include_new'] = false;
            } else {
                $filters['include_new'] = (string) $input['include_new'] === '1';
            }
        }

        return ['filters' => $filters, 'mode' => $mode, 'error' => $error];
    }

    /** @return array{cards:list<array<string,mixed>>,due_count:int,new_count:int,total_count:int} */
    private static function selection(string $mode, array $filters): array
    {
        if ($mode === 'missed') {
            return self::service()->selectionForQuestionIds(self::missedQuestionIds(), 10);
        }
        if ($mode === 'weakness') {
            return self::service()->weaknessSelection(10);
        }

        return self::service()->dueSelection($filters, 10);
    }

    /** @return list<string> */
    private static function missedQuestionIds(): array
    {
        $scope = \SessionService::get('flashcard_missed_scope', []);
        if (!is_array($scope) || !is_array($scope['question_ids'] ?? null)) {
            return [];
        }
        $session = \SessionService::get('quiz_session', []);
        $sessionId = is_array($session) && is_scalar($session['id'] ?? null)
            ? (string) $session['id']
            : '';
        if ($sessionId === '' || $sessionId !== (string) ($scope['quiz_session_id'] ?? '')) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $id): string => is_scalar($id) ? trim((string) $id) : '',
            $scope['question_ids']
        )));
    }

    private static function allowedInMode(array $card, string $mode): bool
    {
        if ($mode === 'missed') {
            return in_array((string) ($card['question_id'] ?? ''), self::missedQuestionIds(), true);
        }
        if ($mode === 'weakness') {
            return self::service()->isWeaknessCard((string) ($card['card_key'] ?? ''));
        }

        return true;
    }

    /** @param list<array<string,mixed>> $records */
    private static function options(array $records): array
    {
        return array_values(array_filter($records, static fn(array $record): bool =>
            strtolower((string) ($record['status'] ?? 'active')) !== 'archived'
        ));
    }

    /** @param list<array<string,mixed>> $records @return list<string> */
    private static function ids(array $records): array
    {
        return array_values(array_filter(array_map(
            static fn(array $record): string => strtolower(trim((string) ($record['id'] ?? ''))),
            self::options($records)
        )));
    }

    private static function booleanQuery(mixed $value): bool
    {
        return is_scalar($value) && (string) $value === '1';
    }

    private static function matches(array $card, array $filters): bool
    {
        foreach (['board', 'subject', 'topic'] as $name) {
            if (isset($filters[$name]) && (string) ($card[$name] ?? '') !== (string) $filters[$name]) {
                return false;
            }
        }

        return true;
    }

    private static function reviewUrl(array $filters, string $mode = 'normal'): string
    {
        return '/flashcards/review?' . http_build_query(self::queryValues($filters, $mode));
    }

    /** @return array<string,string|int> */
    private static function queryValues(array $filters, string $mode = 'normal'): array
    {
        $query = [];
        foreach (['board', 'subject', 'topic'] as $name) {
            if (isset($filters[$name]) && is_string($filters[$name]) && $filters[$name] !== '__invalid__') {
                $query[$name] = $filters[$name];
            }
        }
        $query['include_new'] = !empty($filters['include_new']) ? 1 : 0;
        if ($mode !== 'normal') {
            $query['mode'] = $mode;
        }

        return $query;
    }

    /** @return array{cards:list<array<string,mixed>>,due_count:int,new_count:int,total_count:int} */
    private static function emptySelection(): array
    {
        return ['cards' => [], 'due_count' => 0, 'new_count' => 0, 'total_count' => 0];
    }
}
