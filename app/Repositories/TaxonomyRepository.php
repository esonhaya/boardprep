<?php

class TaxonomyRepository
{

    private const ROOT =
        __DIR__ .
        "/../../storage/taxonomy/";


    private static function load(
        string $file
    ): array
    {

        $path =
            self::ROOT .
            $file;


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

}
