<?php

class QuestionValidationService
{

    public static function validate(
        array $question
    ): array
    {

        $errors = [];


        if (
            empty($question["id"])
        ) {

            $errors[] =
                "Missing ID";

        }


        if (
            empty($question["subject"])
        ) {

            $errors[] =
                "Missing subject";

        }


        if (
            empty($question["topic"])
        ) {

            $errors[] =
                "Missing topic";

        }


        if (
            !isset($question["concept"])
        ) {

            $question["concept"] =
                "";

        }


        if (
            empty($question["difficulty"])
        ) {

            $errors[] =
                "Missing difficulty";

        }


        if (
            empty($question["question"])
        ) {

            $errors[] =
                "Missing question";

        }


        if (

            empty($question["choices"])

            ||

            count(
                $question["choices"]
            ) < 2

        ) {

            $errors[] =
                "Invalid choices";

        }
        else {

            if (

                count(
                    array_unique(
                        $question["choices"]
                    )
                )
                !==
                count(
                    $question["choices"]
                )

            ) {

                $errors[] =
                    "Choices must all be different.";

            }

        }


        if (
            empty($question["answer"])
        ) {

            $errors[] =
                "Missing answer";

        }
        else if (

            !empty($question["choices"])

            &&

            !in_array(

                $question["answer"],

                $question["choices"]

            )

        ) {

            $errors[] =
                "Answer must match one of the choices.";

        }


        if (
            empty($question["explanation"])
        ) {

            $errors[] =
                "Missing explanation";

        }


        return [

            "valid" =>
                empty($errors),

            "errors" =>
                $errors

        ];

    }

}
