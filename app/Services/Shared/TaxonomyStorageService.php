<?php

declare(strict_types=1);

final class TaxonomyStorageService
{
    private const ROOT =
        __DIR__ . "/../../../storage/";

    private static function load(
        string $collection
    ): array {

        $path =
            self::ROOT .
            $collection .
            "/" .
            $collection .
            ".json";

        if (!file_exists($path)) {
            return [];
        }

        $data =
            json_decode(
                file_get_contents($path),
                true
            );

        return is_array($data)
            ? $data
            : [];

    }

    private static function save(
        string $collection,
        array $data
    ): void {

        file_put_contents(

            self::ROOT .
            $collection .
            "/" .
            $collection .
            ".json",

            json_encode(
                array_values($data),
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE
            )

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

    public static function domainsBySubject(
        string $subject
    ): array {

        return array_values(
            array_filter(
                self::domains(),
                static fn(array $d) =>
                    ($d["subject_id"] ?? "")
                    ===
                    $subject
            )
        );

    }

    public static function topicsByDomain(
        string $domain
    ): array {

        return array_values(
            array_filter(
                self::topics(),
                static fn(array $t) =>
                    ($t["domain_id"] ?? "")
                    ===
                    $domain
            )
        );

    }

    public static function conceptsByTopic(
        string $topic
    ): array {

        return array_values(
            array_filter(
                self::concepts(),
                static fn(array $c) =>
                    ($c["topic_id"] ?? "")
                    ===
                    $topic
            )
        );

    }

    public static function saveDomains(
        array $domains
    ): void {

        self::save(
            "domains",
            $domains
        );

    }

    public static function saveTopics(
        array $topics
    ): void {

        self::save(
            "topics",
            $topics
        );

    }

    public static function saveConcepts(
        array $concepts
    ): void {

        self::save(
            "concepts",
            $concepts
        );

    }

}
