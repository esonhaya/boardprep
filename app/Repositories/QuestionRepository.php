<?php

class QuestionRepository
{

    private const ROOT =
        __DIR__ . "/../../storage/";

    private const LEGACY =
        "questions.json";

    private const DIRECTORY =
        "questions";



    public static function all(): array
    {

        $directory =
            self::ROOT .
            self::DIRECTORY;


        if (is_dir($directory)) {

            return self::loadDirectory(
                $directory
            );

        }


        return self::loadFile(
            self::ROOT .
            self::LEGACY
        );

    }



    private static function loadDirectory(
        string $directory
    ): array
    {

        $questions = [];


        foreach (
            glob($directory . "/*.json")
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

        if (!file_exists($file)) {

            return [];

        }


        $questions =
            json_decode(
                file_get_contents($file),
                true
            );


        return is_array($questions)
            ? $questions
            : [];

    }



    public static function bySubject(
        string $subject
    ): array
    {

        return array_values(

            array_filter(

                self::all(),

                fn($question) =>

                    ($question["subject"] ?? "")

                    ===

                    $subject

            )

        );

    }



    public static function byTopic(
        string $topic
    ): array
    {

        return array_values(

            array_filter(

                self::all(),

                fn($question) =>

                    ($question["topic"] ?? "")

                    ===

                    $topic

            )

        );

    }

}
