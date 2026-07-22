<?php

class QuestionValidationService
{

    public static function validate(
        array $question
    ): array
    {

        $errors = [];

        if (
            $question["question"] === ""
        ) {

            $errors["question"] =
                "Question cannot be empty.";

        }

        if (
            $question["explanation"] === ""
        ) {

            $errors["explanation"] =
                "Explanation is required.";

        }

        if (

            count(

                array_unique(

                    $question["choices"]

                )

            )

            !==

            4

        ) {

            $errors["choices"] =
                "Choices must all be different.";

        }

        if (

            !in_array(

                $question["answer"],

                $question["choices"],

                true

            )

        ) {

            $errors["answer"] =
                "Correct answer must match one of the choices.";

        }

        return $errors;

    }

}
