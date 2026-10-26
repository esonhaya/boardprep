<?php

declare(strict_types=1);

namespace App\Services\Question\Quality;

/**
 * Bounded, filterable projection of the authoritative repository-health
 * findings. The queue never creates or persists issue classifications.
 */
final class QuestionQualityIssueQueueService
{
    private const STATUSES = ['FAIL', 'WARN', 'REVIEW', 'INFO'];
    private const PAGE_SIZES = [25, 50, 100];

    /**
     * @param array<int, object|array<string, mixed>> $issues
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function paginate(array $issues, array $input = []): array
    {
        $filters = self::normalizeFilters($input);
        $rows = [];
        $statusCounts = array_fill_keys(self::STATUSES, 0);
        $categoryCounts = [];
        $issueTypeCounts = [];
        $entityCounts = [];
        $boardCounts = [];

        foreach ($issues as $issue) {
            $row = self::toRow($issue);
            if ($row === null) {
                continue;
            }

            $status = strtoupper(trim((string) ($row['status'] ?? '')));
            $status = in_array($status, self::STATUSES, true) ? $status : 'INFO';
            $category = strtoupper(trim((string) ($row['category'] ?? 'UNKNOWN')));
            $entityType = strtolower(trim((string) ($row['entity_type'] ?? 'repository')));
            $evidence = is_array($row['evidence'] ?? null) ? $row['evidence'] : [];
            $board = trim((string) ($row['board_id'] ?? ''));
            if ($board === '') {
                $board = trim((string) ($evidence['board_id'] ?? ''));
            }

            $row['status'] = $status;
            $row['category'] = $category;
            $row['entity_type'] = $entityType;
            $row['board_id'] = $board;
            $row['issue_type'] = self::issueType($row);
            $row['quality_classification'] = match ($status) {
                'FAIL' => 'DETERMINISTIC_FINDING',
                'WARN' => 'WARNING_REQUIRES_TRIAGE',
                'REVIEW' => 'HUMAN_REVIEW_SIGNAL',
                default => 'INFORMATIONAL',
            };
            $rows[] = $row;
            $statusCounts[$status]++;
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            $issueTypeCounts[$row['issue_type']] = ($issueTypeCounts[$row['issue_type']] ?? 0) + 1;
            $entityCounts[$entityType] = ($entityCounts[$entityType] ?? 0) + 1;
            if ($board !== '') {
                $boardCounts[$board] = ($boardCounts[$board] ?? 0) + 1;
            }
        }

        $matching = array_values(array_filter(
            $rows,
            static fn(array $row): bool => self::matches($row, $filters)
        ));
        $total = count($matching);
        $pages = max(1, (int) ceil($total / $filters['per_page']));
        $page = min($filters['page_num'], $pages);

        ksort($categoryCounts, SORT_STRING);
        ksort($issueTypeCounts, SORT_STRING);
        ksort($entityCounts, SORT_STRING);
        ksort($boardCounts, SORT_STRING);

        return [
            'items' => array_slice($matching, ($page - 1) * $filters['per_page'], $filters['per_page']),
            'total' => $total,
            'page' => $page,
            'per_page' => $filters['per_page'],
            'pages' => $pages,
            'filters' => $filters,
            'status_counts' => $statusCounts,
            'category_counts' => $categoryCounts,
            'issue_type_counts' => $issueTypeCounts,
            'entity_counts' => $entityCounts,
            'board_counts' => $boardCounts,
            'source_count' => count($rows),
        ];
    }

    /** @param array<string, mixed> $input */
    private static function normalizeFilters(array $input): array
    {
        $status = strtoupper(self::scalarText($input['status'] ?? '', 20));
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $perPage = filter_var($input['per_page'] ?? 25, FILTER_VALIDATE_INT);
        if (!in_array($perPage, self::PAGE_SIZES, true)) {
            $perPage = 25;
        }
        $page = filter_var($input['page_num'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'queue' => self::scalarText($input['queue'] ?? '', 30) === 'needs-review' ? 'needs-review' : '',
            'status' => $status,
            'category' => strtoupper(self::scalarText($input['category'] ?? '', 80)),
            'issue_type' => strtoupper(self::scalarText($input['issue_type'] ?? '', 80)),
            'entity_type' => strtolower(self::scalarText($input['entity_type'] ?? '', 40)),
            'board' => strtolower(self::scalarText($input['board'] ?? '', 80)),
            'search' => self::scalarText($input['search'] ?? '', 120),
            'page_num' => is_int($page) ? $page : 1,
            'per_page' => $perPage,
        ];
    }

    private static function scalarText(mixed $value, int $limit): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = trim((string) $value);
        return function_exists('mb_substr')
            ? mb_substr($text, 0, $limit, 'UTF-8')
            : substr($text, 0, $limit);
    }

    /** @param object|array<string, mixed> $issue */
    private static function toRow(object|array $issue): ?array
    {
        if (is_array($issue)) {
            return $issue;
        }
        if (method_exists($issue, 'toArray')) {
            $row = $issue->toArray();
            return is_array($row) ? $row : null;
        }

        return get_object_vars($issue);
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $filters */
    private static function matches(array $row, array $filters): bool
    {
        $status = strtoupper((string) ($row['status'] ?? 'INFO'));
        if ($filters['queue'] === 'needs-review' && $status === 'INFO') {
            return false;
        }
        if ($filters['status'] !== '' && $status !== $filters['status']) {
            return false;
        }
        if ($filters['category'] !== '' && strtoupper((string) ($row['category'] ?? '')) !== $filters['category']) {
            return false;
        }
        if ($filters['issue_type'] !== '' && strtoupper((string) ($row['issue_type'] ?? '')) !== $filters['issue_type']) {
            return false;
        }
        if ($filters['entity_type'] !== '' && strtolower((string) ($row['entity_type'] ?? '')) !== $filters['entity_type']) {
            return false;
        }
        if ($filters['board'] !== '' && strtolower((string) ($row['board_id'] ?? '')) !== $filters['board']) {
            return false;
        }
        if ($filters['search'] === '') {
            return true;
        }

        $searchable = implode(' ', array_map(
            static fn(mixed $value): string => is_scalar($value) ? (string) $value : '',
            [
                $row['entity_id'] ?? '',
                $row['code'] ?? '',
                $row['message'] ?? '',
                $row['suggested_action'] ?? '',
            ]
        ));

        $position = function_exists('mb_stripos')
            ? mb_stripos($searchable, $filters['search'], 0, 'UTF-8')
            : stripos($searchable, $filters['search']);

        return $position !== false;
    }

    /** Classify only what an existing machine finding directly supports. */
    private static function issueType(array $row): string
    {
        $code = strtoupper((string) ($row['code'] ?? ''));
        $category = strtoupper((string) ($row['category'] ?? ''));
        $status = strtoupper((string) ($row['status'] ?? ''));

        if (str_contains($code, 'DUPLICATE')) {
            return 'DUPLICATE_REVIEW_REQUIRED';
        }
        if (str_contains($code, 'ANSWER') || $code === 'QUESTION_REUSE_REVIEW_HOLD') {
            return 'ANSWER_KEY_REVIEW_REQUIRED';
        }
        if (str_contains($code, 'EXPLANATION')) {
            return 'EXPLANATION_REVIEW_REQUIRED';
        }
        if (str_contains($code, 'VERSION') || str_contains($code, 'CURRENTNESS')) {
            return 'SOURCE_CURRENTNESS_WARNING';
        }
        if (str_contains($code, 'SCOPE') || str_contains($code, 'SEMANTIC_FIT')) {
            return 'SOURCE_SCOPE_WARNING';
        }
        if (str_contains($code, 'TAXONOMY')) {
            return 'TAXONOMY_MAPPING_REQUIRED';
        }
        if (str_contains($code, 'STUDY_LINK') && (str_contains($code, 'BROKEN') || str_contains($code, 'MISSING') || str_contains($code, 'UNRESOLVED'))) {
            return 'STUDY_SECTION_MISSING';
        }
        if (str_contains($code, 'SOURCE_URL') || str_contains($code, 'SOURCE_TEXT')
            || str_contains($code, 'SOURCE_REFERENCE') || str_contains($code, 'SOURCE_RECORD')) {
            return 'SOURCE_REVIEW_REQUIRED';
        }
        if ($category === 'BALANCE' || $category === 'DEPTH_COVERAGE') {
            return 'REPOSITORY_COVERAGE_SIGNAL';
        }
        if ($status === 'FAIL') {
            return 'DETERMINISTIC_TECHNICAL_ERROR';
        }

        return 'REVIEW_SIGNAL';
    }
}
