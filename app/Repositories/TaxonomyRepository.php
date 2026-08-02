<?php

declare(strict_types=1);

class TaxonomyRepository
{
    private const ROOT =
        __DIR__ . "/../../storage/";

    private static function load(
        string $collection
    ): array {

        $path =
            self::ROOT
            . $collection
            . "/"
            . $collection
            . ".json";

        if (!file_exists($path)) {
            return [];
        }

        $data = json_decode(
            file_get_contents($path),
            true
        );

        return is_array($data)
            ? $data
            : [];

    }

    /*
    |--------------------------------------------------------------------------
    | Subjects
    |--------------------------------------------------------------------------
    */

    public static function subjects(): array
    {
        return self::load(
            "subjects"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    */

    public static function domains(): array
    {
        return self::load(
            "domains"
        );
    }

    public static function domainsBySubject(
        string $subjectId
    ): array {

        return array_values(

            array_filter(

                self::domains(),

                static fn(
                    array $domain
                ) =>
                    ($domain["subject_id"] ?? "")
                    ===
                    $subjectId

            )

        );

    }

    /*
    |--------------------------------------------------------------------------
    | Topics
    |--------------------------------------------------------------------------
    */

    public static function topics(): array
    {
        return self::load(
            "topics"
        );
    }

    public static function topicsByDomain(
        string $domainId
    ): array {

        return array_values(

            array_filter(

                self::topics(),

                static fn(
                    array $topic
                ) =>
                    ($topic["domain_id"] ?? "")
                    ===
                    $domainId

            )

        );

    }

    /*
    |--------------------------------------------------------------------------
    | Concepts
    |--------------------------------------------------------------------------
    */

    public static function concepts(): array
    {
        return self::load(
            "concepts"
        );
    }

    public static function conceptsByTopic(
        string $topicId
    ): array {

        return array_values(

            array_filter(

                self::concepts(),

                static fn(
                    array $concept
                ) =>
                    ($concept["topic_id"] ?? "")
                    ===
                    $topicId

            )

        );

    }
}
