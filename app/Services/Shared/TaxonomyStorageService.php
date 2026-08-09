<?php

declare(strict_types=1);

namespace App\Services\Shared;

final class TaxonomyStorageService
{
    private const ROOT = __DIR__ . "/../../../storage/";

    private static function load(
        string $collection
    ): array {
        $path = self::ROOT . $collection . ".json";

        if (!is_file($path)) {
            return [];
        }

        $data = json_decode(
            (string) file_get_contents($path),
            true
        );

        return is_array($data) ? $data : [];
    }

    private static function save(
        string $collection,
        array $data
    ): void {
        file_put_contents(
            self::ROOT . $collection . ".json",
            json_encode(
                array_values($data),
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),
            LOCK_EX
        );
    }

    public static function boards(): array
    {
        return self::load("boards");
    }

    public static function subjects(): array
    {
        return self::load("subjects");
    }

    public static function domains(): array
    {
        return self::load("domains");
    }

    public static function topics(): array
    {
        return self::load("topics");
    }

    public static function concepts(): array
    {
        return self::load("concepts");
    }

    public static function boardSubjects(): array
    {
        return self::load("board-subjects");
    }

    public static function domainsBySubject(
        string $subjectId
    ): array {
        return array_values(
            array_filter(
                self::domains(),
                static fn(array $domain): bool =>
                    ($domain["subject_id"] ?? "") === $subjectId
            )
        );
    }

    public static function topicsByDomain(
        string $domainId
    ): array {
        return array_values(
            array_filter(
                self::topics(),
                static fn(array $topic): bool =>
                    ($topic["domain_id"] ?? "") === $domainId
            )
        );
    }

    public static function conceptsByTopic(
        string $topicId
    ): array {
        return array_values(
            array_filter(
                self::concepts(),
                static fn(array $concept): bool =>
                    ($concept["topic_id"] ?? "") === $topicId
            )
        );
    }

    public static function subjectsByBoard(
        string $boardId
    ): array {
        return array_values(
            array_filter(
                self::boardSubjects(),
                static fn(array $relation): bool =>
                    ($relation["board_id"] ?? "") === $boardId
            )
        );
    }

    public static function saveDomains(array $domains): void
    {
        self::save("domains", $domains);
    }

    public static function saveTopics(array $topics): void
    {
        self::save("topics", $topics);
    }

    public static function saveConcepts(array $concepts): void
    {
        self::save("concepts", $concepts);
    }
}
