<?php

declare(strict_types=1);

namespace App\Services\Question;

use RuntimeException;

/**
 * Read-only access to recovered Batch 695 academic-review proposals.
 *
 * The proposal inventory is deliberately separate from QuestionRepository,
 * eligibility, and learner question pools. Reviewers inspect its snapshots
 * here; it never supplies scoring or canonical question records.
 */
final class AcademicReviewProposalService
{
    private const INVENTORY_PATH = '/docs/reports/batch-713-cohort-disposition.json';

    /** @var array<string, mixed>|null */
    private static ?array $inventory = null;

    /** @return array<string, mixed> */
    public static function inventory(): array
    {
        if (self::$inventory !== null) {
            return self::$inventory;
        }

        $path = dirname(__DIR__, 3) . self::INVENTORY_PATH;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('The Batch 695 proposal inventory is unavailable.');
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)
            || ($decoded['schema'] ?? '') !== 'boardprep.batch-713-b695-cohort-disposition.v1'
            || !is_array($decoded['items'] ?? null)) {
            throw new RuntimeException('The Batch 695 proposal inventory has an unsupported format.');
        }

        self::$inventory = $decoded;
        return self::$inventory;
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return array_values(array_filter(self::inventory()['items'], 'is_array'));
    }

    /** @return array<string, mixed>|null */
    public static function find(string $id): ?array
    {
        $requested = trim($id);
        if (preg_match('/^batch695-(\d+)$/', $requested, $match) === 1) {
            $requested = $match[1];
        }
        if (preg_match('/^\d+$/', $requested) !== 1) {
            return null;
        }

        foreach (self::all() as $proposal) {
            if ((string) ($proposal['question_id'] ?? '') === $requested) {
                return $proposal;
            }
        }
        return null;
    }

    /**
     * @param array{search?: string, board?: string, subject?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public static function page(array $filters, int $page = 1, int $perPage = 50): array
    {
        $perPage = max(1, min(100, $perPage));
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $board = mb_strtolower(trim((string) ($filters['board'] ?? '')));
        $subject = mb_strtolower(trim((string) ($filters['subject'] ?? '')));

        $matches = array_values(array_filter(self::all(), static function (array $proposal) use ($search, $board, $subject): bool {
            if ($board !== '' && mb_strtolower((string) ($proposal['board_id'] ?? '')) !== $board) {
                return false;
            }
            if ($subject !== '' && mb_strtolower((string) ($proposal['subject_id'] ?? '')) !== $subject) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            $question = is_array($proposal['proposed_question'] ?? null) ? $proposal['proposed_question'] : [];
            $haystack = implode(' ', [
                (string) ($proposal['question_id'] ?? ''),
                (string) ($proposal['board_name'] ?? ''),
                (string) ($proposal['subject_name'] ?? ''),
                (string) ($proposal['topic_name'] ?? ''),
                (string) ($proposal['concept_name'] ?? ''),
                (string) ($question['stem'] ?? ''),
            ]);
            return mb_stripos($haystack, $search) !== false;
        }));

        $total = count($matches);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        return [
            'items' => array_values(array_slice($matches, ($page - 1) * $perPage, $perPage)),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** @return list<array{id: string, name: string}> */
    public static function boardOptions(): array
    {
        $options = [];
        foreach (self::all() as $proposal) {
            $id = trim((string) ($proposal['board_id'] ?? ''));
            if ($id !== '') {
                $options[$id] = (string) ($proposal['board_name'] ?? $id);
            }
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        $result = [];
        foreach ($options as $id => $name) {
            $result[] = ['id' => $id, 'name' => $name];
        }
        return $result;
    }

    /** @return list<array{id: string, name: string}> */
    public static function subjectOptions(): array
    {
        $options = [];
        foreach (self::all() as $proposal) {
            $id = trim((string) ($proposal['subject_id'] ?? ''));
            if ($id !== '') {
                $options[$id] = (string) ($proposal['subject_name'] ?? $id);
            }
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        $result = [];
        foreach ($options as $id => $name) {
            $result[] = ['id' => $id, 'name' => $name];
        }
        return $result;
    }
}
