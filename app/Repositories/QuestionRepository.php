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



    public static function save(
        array $question
    ): void
    {

        $questions =
            self::all();

        $questions[] =
            $question;


        file_put_contents(

            self::ROOT .
            self::LEGACY,

            json_encode(

                $questions,

                JSON_PRETTY_PRINT

            )

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


        file_put_contents(

            self::ROOT .
            self::LEGACY,

            json_encode(

                $questions,

                JSON_PRETTY_PRINT

            )

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


    file_put_contents(

        self::ROOT .
        self::LEGACY,

        json_encode(
            $questions,
            JSON_PRETTY_PRINT
        )

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


    file_put_contents(

        self::ROOT .
        self::LEGACY,

        json_encode(
            $questions,
            JSON_PRETTY_PRINT
        )

    );

}

public static function search(
    string $keyword
): array
{

    $keyword =
        strtolower(
            trim($keyword)
        );

    if ($keyword === "") {

        return self::all();

    }

    return array_values(

        array_filter(

            self::all(),

            function ($question) use ($keyword) {

                return
                    str_contains(
                        strtolower(
                            $question["question"] ?? ""
                        ),
                        $keyword
                    )
                    ||
                    str_contains(
                        strtolower(
                            $question["topic"] ?? ""
                        ),
                        $keyword
                    )
                    ||
                    str_contains(
                        strtolower(
                            $question["concept"] ?? ""
                        ),
                        $keyword
                    );

            }

        )

    );

}

public static function filter(
    string $domain,
    string $difficulty,
    string $topic
): array
{

    return array_values(

        array_filter(

            self::all(),

            function ($question)
            use (
                $domain,
                $difficulty,
                $topic
            ) {

                if (
                    $domain !== ""
                    &&
                    ($question["domain"] ?? "")
                    !==
                    $domain
                ) {

                    return false;

                }

                if (
                    $difficulty !== ""
                    &&
                    ($question["difficulty"] ?? "")
                    !==
                    $difficulty
                ) {

                    return false;

                }

                if (
                    $topic !== ""
                    &&
                    ($question["topic"] ?? "")
                    !==
                    $topic
                ) {

                    return false;

                }

                return true;

            }

        )

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

public static function findDuplicates(
    array $question
): array
{

    $duplicates = [];


    $target =
        strtolower(
            trim(
                $question["question"] ?? ""
            )
        );


    foreach (
        self::all()
        as $existing
    ) {


        if (
            ($existing["id"] ?? 0)
            ==
            ($question["id"] ?? 0)
        ) {

            continue;

        }


        $current =
            strtolower(
                trim(
                    $existing["question"] ?? ""
                )
            );


        similar_text(
            $target,
            $current,
            $percent
        );


        if (
            $percent >= 85
        ) {

            $duplicates[] =
                [
                    "question" =>
                        $existing,

                    "score" =>
                        round(
                            $percent
                        )
                ];

        }

    }


    return $duplicates;

}x



}
