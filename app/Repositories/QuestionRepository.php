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

        return QuestionStorage::load(

            self::ROOT .
            self::DIRECTORY,

            self::ROOT .
            self::LEGACY

        );

    }



    public static function save(
        array $question
    ): void
    {

        $questions =
            self::all();


        $questions[] =
            $question;


        self::saveAll(
            $questions
        );

    }



    public static function find(
        int $id
    ): ?array
    {

        foreach (
            self::all()
            as $question
        ) {

            if (
                ($question["id"] ?? 0)
                ===
                $id
            ) {

                return $question;

            }

        }


        return null;

    }



    public static function update(
        int $id,
        array $updatedQuestion
    ): void
    {

        $questions =
            self::all();


        foreach (
            $questions
            as $index => $question
        ) {

            if (
                ($question["id"] ?? 0)
                ===
                $id
            ) {

                $questions[$index] =
                    $updatedQuestion;

                break;

            }

        }


        self::saveAll(
            $questions
        );

    }



    public static function archive(
        int $id
    ): void
    {

        $questions =
            self::all();


        foreach (
            $questions
            as $index => $question
        ) {

            if (
                ($question["id"] ?? 0)
                ===
                $id
            ) {

                $questions[$index]["status"] =
                    "archived";

                break;

            }

        }


        self::saveAll(
            $questions
        );

    }



    public static function restore(
        int $id
    ): void
    {

        $questions =
            self::allIncludingArchived();


        foreach (
            $questions
            as $index => $question
        ) {

            if (
                ($question["id"] ?? 0)
                ===
                $id
            ) {

                $questions[$index]["status"] =
                    "active";

                break;

            }

        }


        self::saveAll(
            $questions
        );

    }



    public static function saveAll(
        array $questions
    ): void
    {

        QuestionStorage::write(
            $questions
        );

    }



    public static function allIncludingArchived(): array
    {

        return self::all();

    }

}
