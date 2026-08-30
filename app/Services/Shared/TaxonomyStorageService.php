<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Storage\JsonStorage;

final class TaxonomyStorageService
{
    private const ROOT = __DIR__ . '/../../../storage';

    private static function storage(): JsonStorage
    {
        return new JsonStorage(self::ROOT);
    }

    private static function load(string $collection): array
    {
        return array_values(array_filter(
            self::storage()->all($collection),
            'is_array'
        ));
    }

    private static function save(string $collection, array $data): void
    {
        $records = array_values(array_filter($data, 'is_array'));
        self::storage()->replace($collection, $records);
    }

    public static function boards(): array
    {
        return self::load('boards');
    }

    public static function subjects(): array
    {
        return self::load('subjects');
    }

    public static function domains(): array
    {
        return self::load('taxonomy/domains');
    }

    public static function topics(): array
    {
        return self::load('taxonomy/topics');
    }

    public static function concepts(): array
    {
        return self::load('taxonomy/concepts');
    }

    public static function boardSubjects(): array
    {
        return self::load('board-subjects');
    }

    public static function domainsBySubject(string $subjectId): array
    {
        return array_values(array_filter(
            self::domains(),
            static fn(array $domain): bool =>
                ($domain['subject_id'] ?? '') === $subjectId
        ));
    }

    public static function topicsByDomain(string $domainId): array
    {
        return array_values(array_filter(
            self::topics(),
            static fn(array $topic): bool =>
                ($topic['domain_id'] ?? '') === $domainId
        ));
    }

    public static function conceptsByTopic(string $topicId): array
    {
        return array_values(array_filter(
            self::concepts(),
            static fn(array $concept): bool =>
                ($concept['topic_id'] ?? '') === $topicId
        ));
    }

    public static function subjectsByBoard(string $boardId): array
    {
        return array_values(array_filter(
            self::boardSubjects(),
            static fn(array $relation): bool =>
                ($relation['board_id'] ?? '') === $boardId
        ));
    }

    public static function saveDomains(array $domains): void
    {
        self::save('taxonomy/domains', $domains);
    }

    public static function saveTopics(array $topics): void
    {
        self::save('taxonomy/topics', $topics);
    }

    public static function saveConcepts(array $concepts): void
    {
        self::save('taxonomy/concepts', $concepts);
    }
}
