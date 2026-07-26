<?php

class BlueprintValidator
{

    public static function validate(
        array $blueprint
    ): array
    {

        $errors = [];


        if (empty($blueprint["id"])) {

            $errors[] =
                "Missing blueprint ID.";

        }


        if (empty($blueprint["name"])) {

            $errors[] =
                "Missing blueprint name.";

        }


        if (empty($blueprint["board"])) {

            $errors[] =
                "Missing board.";

        }


        if (empty($blueprint["subject"])) {

            $errors[] =
                "Missing subject.";

        }


        if (!isset($blueprint["version"])) {

            $errors[] =
                "Missing version.";

        }


        if (
            empty($blueprint["questionCount"])
            ||
            $blueprint["questionCount"] <= 0
        ) {

            $errors[] =
                "Question count must be greater than zero.";

        }


        if (!isset($blueprint["difficulty"])) {

            $errors[] =
                "Missing difficulty distribution.";

        } else {

            $easy =
                (int)(
                    $blueprint["difficulty"]["easy"] ?? 0
                );

            $medium =
                (int)(
                    $blueprint["difficulty"]["medium"] ?? 0
                );

            $hard =
                (int)(
                    $blueprint["difficulty"]["hard"] ?? 0
                );


            $totalDifficulty =
                $easy +
                $medium +
                $hard;


            if ($totalDifficulty !== 100) {

                $errors[] =
                    "Difficulty distribution must equal 100%.";

            }

        }


        return [

            "valid" =>
                empty($errors),

            "errors" =>
                $errors

        ];

    }

}
