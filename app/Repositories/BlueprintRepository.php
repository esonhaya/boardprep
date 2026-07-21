<?php

class BlueprintRepository
{

    private const DIRECTORY =
        __DIR__ .
        "/../../storage/blueprints/";



    public static function all(): array
    {

        $blueprints = [];


        foreach (
            glob(self::DIRECTORY . "*.json")
            as $file
        ) {

            $data =
                json_decode(
                    file_get_contents($file),
                    true
                );


            if (is_array($data)) {

                $blueprints[] =
                    $data;

            }

        }


        return $blueprints;

    }



    public static function find(
        string $name
    ): ?array
    {

        $file =
            self::DIRECTORY .
            strtolower($name) .
            ".json";


        if (!file_exists($file)) {

            return null;

        }


        return json_decode(
            file_get_contents($file),
            true
        );

    }

}
