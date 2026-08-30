<?php

declare(strict_types=1);

namespace App\Services\Question\Builder;

use App\Services\Shared\TaxonomyStorageService;

final class TaxonomyHierarchyIndex
{
    public static function domainSubject(string $domainId): string
    {
        return self::parentId(TaxonomyStorageService::domains(), $domainId, 'subject_id');
    }

    public static function topicDomain(string $topicId): string
    {
        return self::parentId(TaxonomyStorageService::topics(), $topicId, 'domain_id');
    }

    public static function conceptTopic(string $conceptId): string
    {
        return self::parentId(TaxonomyStorageService::concepts(), $conceptId, 'topic_id');
    }

    public static function subjectBoard(string $subjectId): string
    {
        $matches = [];

        foreach (TaxonomyStorageService::boardSubjects() as $relation) {
            if (!is_array($relation) || ($relation['subject_id'] ?? '') !== $subjectId) {
                continue;
            }

            $boardId = trim((string) ($relation['board_id'] ?? ''));
            if ($boardId !== '') {
                $matches[$boardId] = true;
            }
        }

        return count($matches) === 1 ? (string) array_key_first($matches) : '';
    }

    private static function parentId(array $records, string $id, string $parentKey): string
    {
        if ($id === '') {
            return '';
        }

        foreach ($records as $record) {
            if (!is_array($record) || ($record['id'] ?? '') !== $id) {
                continue;
            }

            return trim((string) ($record[$parentKey] ?? ''));
        }

        return '';
    }
}
