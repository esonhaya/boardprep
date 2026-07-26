<?php

class BlueprintRepository
{

    private const ROOT =
        __DIR__ .
        "/../../storage/blueprints/";



    public static function all(): array
    {

        if (
            !is_dir(
                self::ROOT
            )
        ) {

            mkdir(
                self::ROOT,
                0777,
                true
            );

        }


        $blueprints = [];


        foreach (

            glob(
                self::ROOT . "*.json"
            )

            as $file

        ) {

            $data =
                json_decode(
                    file_get_contents($file),
                    true
                );

            if (
                is_array($data)
            ) {

                $blueprints[] =
                    $data;

            }

        }


        return
            $blueprints;

    }



    public static function find(
        string $id
    ): ?array
    {

        foreach (
            self::all()
            as $blueprint
        ) {

            if (
                ($blueprint["id"] ?? "")
                ===
                $id
            ) {

                return
                    $blueprint;

            }

        }


        return null;

    }



    public static function save(
        array $blueprint
    ): void
    {

        if (
            !isset(
                $blueprint["id"]
            )
        ) {

            return;

        }


        file_put_contents(

            self::ROOT .
            $blueprint["id"] .
            ".json",

            json_encode(

                $blueprint,

                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE

            )

        );

    }

}
