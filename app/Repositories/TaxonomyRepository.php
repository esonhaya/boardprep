<?php

class TaxonomyRepository
{

    private const ROOT =
        __DIR__ . "/../../storage/taxonomy/";


    private static function load(
        string $file
    ): array
    {

        $path =
            self::ROOT . $file;


        if (!file_exists($path)) {

            return [];

        }


        $data =
            json_decode(

                file_get_contents($path),

                true

            );


        return
            is_array($data)
                ? $data
                : [];

    }



    private static function save(
        string $file,
        array $data
    ): void
    {

        file_put_contents(

            self::ROOT . $file,

            json_encode(

                array_values($data),

                JSON_PRETTY_PRINT

            )

        );

    }



    public static function domains(): array
    {

        return self::load(
            "domains.json"
        );

    }



    public static function topics(): array
    {

        return self::load(
            "topics.json"
        );

    }



    public static function concepts(): array
    {

        return self::load(
            "concepts.json"
        );

    }



    public static function saveDomains(
        array $domains
    ): void
    {

        self::save(
            "domains.json",
            $domains
        );

    }



    public static function saveTopics(
        array $topics
    ): void
    {

        self::save(
            "topics.json",
            $topics
        );

    }



    public static function saveConcepts(
        array $concepts
    ): void
    {

        self::save(
            "concepts.json",
            $concepts
        );

    }

}
