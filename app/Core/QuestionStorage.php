<?php

class QuestionStorage
{

    private const ROOT =
        __DIR__ . "/../../storage/";

    public static function load(
        string $directory,
        string $legacy
    ): array
    {

        if (
            is_dir($directory)
        ) {

            return self::loadDirectory(
                $directory
            );

        }


        return self::loadFile(
            $legacy
        );

    }



    public static function write(
        array $questions
    ): void
    {

        file_put_contents(

            self::ROOT . "questions.json",

            json_encode(
                $questions,
                JSON_PRETTY_PRINT
            )

        );

    }



    private static function loadDirectory(
        string $directory
    ): array
    {

        $questions = [];


        foreach (
            glob(
                $directory . "/*.json"
            )
            as $file
        ) {

            $questions =
                array_merge(
                    $questions,
                    self::loadFile($file)
                );

        }


        return $questions;

    }



    private static function loadFile(
        string $file
    ): array
    {

        if (
            !file_exists($file)
        ) {

            return [];

        }


        $questions =
            json_decode(
                file_get_contents($file),
                true
            );


        return is_array($questions)
            ?
            $questions
            :
            [];

    }

}
