<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Shared\TaxonomyStorageService;

final class CompletedAttemptHistoryFilter
{
    public const UNREGISTERED = '__unregistered__';

    /**
     * Filter only the current learner's saved attempts. Taxonomy values are
     * normalized only when the stored value matches a registered id, code, or
     * unique display name; unknown historical values remain in the unregistered
     * bucket and are never attributed to a guessed board or subject.
     *
     * @return array{
     *   attempts:array<int,array<string,mixed>>,
     *   board_filter:string,
     *   subject_filter:string,
     *   board_options:array<int,array{value:string,label:string,count:int}>,
     *   subject_options:array<int,array{value:string,label:string,count:int}>,
     *   adjusted:bool,
     *   has_filters:bool
     * }
     */
    public static function apply(array $attempts, string $requestedBoard = '', string $requestedSubject = ''): array
    {
        $boards = TaxonomyStorageService::boards();
        $subjects = TaxonomyStorageService::subjects();
        $boardIndex = self::taxonomyIndex($boards);
        $subjectIndex = self::taxonomyIndex($subjects);
        $boardOptions = self::options($attempts, 'board', $boardIndex, $boards, 'Unrecorded or unregistered board');
        $subjectOptions = self::options($attempts, 'subject', $subjectIndex, $subjects, 'Unrecorded or unregistered subject');
        $boardFilter = self::selectedFilter($requestedBoard, $boardOptions, $boardIndex);
        $subjectFilter = self::selectedFilter($requestedSubject, $subjectOptions, $subjectIndex);
        $adjusted = ($requestedBoard !== '' && $boardFilter === '')
            || ($requestedSubject !== '' && $subjectFilter === '');

        $filtered = [];
        foreach ($attempts as $attempt) {
            if (!is_array($attempt)) continue;
            $boardId = self::registeredId($attempt['board'] ?? null, $boardIndex);
            $subjectId = self::registeredId($attempt['subject'] ?? null, $subjectIndex);
            if (!self::matches($boardFilter, $boardId) || !self::matches($subjectFilter, $subjectId)) continue;
            $filtered[] = $attempt;
        }

        return [
            'attempts' => $filtered,
            'board_filter' => $boardFilter,
            'subject_filter' => $subjectFilter,
            'board_options' => $boardOptions,
            'subject_options' => $subjectOptions,
            'adjusted' => $adjusted,
            'has_filters' => $boardFilter !== '' || $subjectFilter !== '',
        ];
    }

    /** Validate an untrusted return target and reconstruct a local history URL. */
    public static function safeReturnUrl(array $attempts, string $requested, int $pageSize = 20): string
    {
        if ($requested === '' || preg_match('/[\x00-\x1F\x7F]/', $requested) === 1) return '/history';
        $parts = parse_url($requested);
        if (!is_array($parts)
            || ($parts['path'] ?? '') !== '/history'
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['fragment'])) {
            return '/history';
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        if ($query === []) return '/history';
        if (array_diff(array_keys($query), ['page', 'board', 'subject']) !== []) return '/history';
        foreach ($query as $value) {
            if (!is_string($value) && !is_numeric($value)) return '/history';
        }

        $rawPage = (string) ($query['page'] ?? '1');
        if (preg_match('/\A[1-9]\d{0,5}\z/', $rawPage) !== 1) return '/history';
        $state = self::apply(
            $attempts,
            (string) ($query['board'] ?? ''),
            (string) ($query['subject'] ?? '')
        );
        if ($state['adjusted']) return '/history';

        $pages = max(1, (int) ceil(count($state['attempts']) / max(1, $pageSize)));
        $parameters = ['page' => min((int) $rawPage, $pages)];
        if ($state['board_filter'] !== '') $parameters['board'] = $state['board_filter'];
        if ($state['subject_filter'] !== '') $parameters['subject'] = $state['subject_filter'];

        return '/history?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{ids:array<string,string>,aliases:array<string,string|null>} */
    private static function taxonomyIndex(array $records): array
    {
        $ids = [];
        $aliases = [];
        foreach ($records as $record) {
            if (!is_array($record) || !is_scalar($record['id'] ?? null)) continue;
            $id = trim((string) $record['id']);
            $key = self::key($id);
            if ($key === '') continue;
            $ids[$key] = $id;
            foreach (['code', 'name'] as $field) {
                if (!is_scalar($record[$field] ?? null)) continue;
                $alias = self::key((string) $record[$field]);
                if ($alias === '') continue;
                if (!array_key_exists($alias, $aliases)) {
                    $aliases[$alias] = $id;
                } elseif ($aliases[$alias] !== $id) {
                    $aliases[$alias] = null;
                }
            }
        }
        return ['ids' => $ids, 'aliases' => $aliases];
    }

    /** @param array{ids:array<string,string>,aliases:array<string,string|null>} $index */
    private static function registeredId(mixed $value, array $index): ?string
    {
        if (!is_scalar($value)) return null;
        $key = self::key((string) $value);
        if ($key === '') return null;
        return $index['ids'][$key] ?? $index['aliases'][$key] ?? null;
    }

    /**
     * @param array{ids:array<string,string>,aliases:array<string,string|null>} $index
     * @return array<int,array{value:string,label:string,count:int}>
     */
    private static function options(array $attempts, string $field, array $index, array $records, string $unregisteredLabel): array
    {
        $counts = [];
        $unregisteredCount = 0;
        foreach ($attempts as $attempt) {
            if (!is_array($attempt)) continue;
            $id = self::registeredId($attempt[$field] ?? null, $index);
            if ($id === null) {
                $unregisteredCount++;
            } else {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        $options = [];
        foreach ($records as $record) {
            if (!is_array($record) || !is_scalar($record['id'] ?? null)) continue;
            $id = trim((string) $record['id']);
            if ($id === '' || ($counts[$id] ?? 0) === 0) continue;
            $label = is_scalar($record['name'] ?? null) ? trim((string) $record['name']) : '';
            $options[] = ['value' => $id, 'label' => $label !== '' ? $label : $id, 'count' => $counts[$id]];
        }
        usort($options, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']) ?: strcmp($a['value'], $b['value']));
        if ($unregisteredCount > 0) {
            $options[] = ['value' => self::UNREGISTERED, 'label' => $unregisteredLabel, 'count' => $unregisteredCount];
        }
        return $options;
    }

    /**
     * @param array<int,array{value:string,label:string,count:int}> $options
     * @param array{ids:array<string,string>,aliases:array<string,string|null>} $index
     */
    private static function selectedFilter(string $requested, array $options, array $index): string
    {
        $requested = trim($requested);
        if ($requested === '') return '';
        if ($requested === self::UNREGISTERED) {
            foreach ($options as $option) {
                if ($option['value'] === self::UNREGISTERED) return self::UNREGISTERED;
            }
            return '';
        }

        $id = self::registeredId($requested, $index);
        if ($id === null) return '';
        foreach ($options as $option) {
            if ($option['value'] === $id) return $id;
        }
        return '';
    }

    private static function matches(string $filter, ?string $id): bool
    {
        if ($filter === '') return true;
        if ($filter === self::UNREGISTERED) return $id === null;
        return $id === $filter;
    }

    private static function key(string $value): string
    {
        return strtolower(trim($value));
    }
}
