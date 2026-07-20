<?php

class QuestionValidationService
{

    public static function validate(
        array $question
    ): array
    {

        $errors = [];


        if (empty($question["id"])) {

            $errors[] =
                "Missing ID";

        }


        if (empty($question["subject"])) {

            $errors[] =
                "Missing subject";

        }


        if (empty($question["topic"])) {

            $errors[] =
                "Missing topic";

        }
if (!isset($question["concept"])) {

    $question["concept"] = "";

}

        if (empty($question["difficulty"])) {

            $errors[] =
                "Missing difficulty";

        }


        if (empty($question["question"])) {

            $errors[] =
                "Missing question";

        }


        if (

            empty($question["choices"])

            ||

            count($question["choices"]) < 2

        ) {

            $errors[] =
                "Invalid choices";

        }


        if (empty($question["answer"])) {

            $errors[] =
                "Missing answer";

        }


        if (empty($question["explanation"])) {

            $errors[] =
                "Missing explanation";

        }


        return $errors;

    }

}
