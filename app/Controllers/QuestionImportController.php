<?php

class QuestionImportController
{

    public static function index(): void
    {

        View::render(
            "developer/question-import",
            [
                "pageTitle" =>
                    "Import Questions"
            ]
        );

    }


    public static function import(): void
    {

        if (
            !isset($_FILES["file"])
        ) {

            header(
                "Location: ?page=question-import"
            );

            exit;

        }


        $content =
            file_get_contents(
                $_FILES["file"]["tmp_name"]
            );


        $questions =
            json_decode(
                $content,
                true
            );


        if (
            !is_array($questions)
        ) {

            die(
                "Invalid JSON file."
            );

        }


        $count = 0;


        foreach (
            $questions
            as $question
        ) {


            $question["id"] =
                time() + $count;


            $question["status"] =
                "draft";


            QuestionRepository::save(
                $question
            );


            $count++;

        }


        echo
            $count .
            " questions imported.";

    }

}
