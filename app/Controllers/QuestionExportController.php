<?php

class QuestionExportController
{

    public static function export(): void
    {

        $questions =
            QuestionRepository::all();


        header(
            "Content-Type: application/json"
        );


        header(
            "Content-Disposition: attachment; filename=boardprep_questions.json"
        );


        echo json_encode(
            $questions,
            JSON_PRETTY_PRINT
        );


        exit;

    }

}
