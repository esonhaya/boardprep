<?php

class QuestionSearchRepository
{

    public static function search(
        string $keyword
    ): array
    {

        $keyword =
            strtolower(
                trim($keyword)
            );

        if ($keyword === "") {

            return QuestionRepository::all();

        }

        return array_values(

            array_filter(

                QuestionRepository::all(),

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

                QuestionRepository::all(),

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



    public static function bySubject(
        string $subject
    ): array
    {

        return array_values(

            array_filter(

                QuestionRepository::all(),

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

                QuestionRepository::all(),

                fn($question) =>

                    ($question["topic"] ?? "")

                    ===

                    $topic

            )

        );

    }

}
