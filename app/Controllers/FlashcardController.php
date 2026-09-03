<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Flashcard\FlashcardService;
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
                $selection = self::service()->dueSelection($parsed['filters'], 10);
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
            'selection' => $selection,
            'error' => $error,
        ]);
    }

    public static function review(): void
    {
        $parsed = self::filters(Request::query());
        $filters = $parsed['filters'];
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
                    if (!self::matches($card, $filters)) {
                        throw new \InvalidArgumentException('Flashcard does not match the selected filters.');
                    }
                } else {
                    $selection = self::service()->dueSelection($filters, 10);
                    $card = $selection['cards'][0] ?? null;
                }
            } catch (\Throwable) {
                $error = 'That flashcard is unavailable or no longer eligible.';
            }
        }

        View::render('flashcards/review', [
            'pageTitle' => 'Review Flashcards | BoardPrep',
            'filters' => $filters,
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
        $redirect = self::reviewUrl($filters);

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
            self::service()->review(trim((string) $cardKey), trim((string) $rating));
            \SessionService::flash('success', 'Review saved.');
        } catch (\Throwable) {
            \SessionService::flash('error', 'That card or rating is invalid. No review was saved.');
        }

        Response::redirect($redirect, 303);
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

        return ['filters' => $filters, 'error' => $error];
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

    private static function reviewUrl(array $filters): string
    {
        return '/flashcards/review?' . http_build_query(self::queryValues($filters));
    }

    /** @return array<string,string|int> */
    private static function queryValues(array $filters): array
    {
        $query = [];
        foreach (['board', 'subject', 'topic'] as $name) {
            if (isset($filters[$name]) && is_string($filters[$name]) && $filters[$name] !== '__invalid__') {
                $query[$name] = $filters[$name];
            }
        }
        $query['include_new'] = !empty($filters['include_new']) ? 1 : 0;

        return $query;
    }

    /** @return array{cards:list<array<string,mixed>>,due_count:int,new_count:int,total_count:int} */
    private static function emptySelection(): array
    {
        return ['cards' => [], 'due_count' => 0, 'new_count' => 0, 'total_count' => 0];
    }
}
