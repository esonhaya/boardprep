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


        $result =
            QuestionImportService::importJson(
                $_FILES["file"]["tmp_name"]
            );


        View::render(
            "developer/question-import",
            [
                "pageTitle" =>
                    "Import Questions",

                "result" =>
                    $result
            ]
        );

    }

}
