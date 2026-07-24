<?php

class CoverageMatrixService
{

    public static function build(): array
    {

        $domains =
            TaxonomyRepository::domains();

        $questions =
            QuestionRepository::all();

        $matrix = [];

        foreach ($domains as $domain) {

            $matrix[$domain] = [];

            foreach (TaxonomyRepository::topics() as $topic) {

                $count = 0;

                foreach ($questions as $question) {

                    if (

                        ($question["domain"] ?? "")
                        ===
                        $domain

                        &&

                        ($question["topic"] ?? "")
                        ===
                        $topic

                    ) {

                        $count++;

                    }

                }

                if ($count > 0) {

                    $matrix[$domain][] = [

                        "topic" => $topic,

                        "count" => $count,

                        "status" =>
                            self::status($count)

                    ];

                }

            }

        }

        return $matrix;

    }


    private static function status(
        int $count
    ): string
    {

        if ($count == 0) {
            return "missing";
        }

        if ($count < 5) {
            return "needs-work";
        }

        return "healthy";

    }

}
