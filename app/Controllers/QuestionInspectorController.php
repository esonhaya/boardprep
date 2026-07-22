<?php

class QuestionInspectorController
{

    public static function index(): void
    {

        $id =
            (int)(
                $_GET["id"] ?? 0
            );

        if ($id === 0) {

            View::render(

                "developer/question-inspector-list",

                [

                    "pageTitle" =>
                        "Question Inspector",

                    "questions" =>
                        QuestionRepository::all()

                ]

            );

            return;

        }

        $question =
            QuestionRepository::find($id);

        if (!$question) {

            header(
                "Location: ?page=question-inspector"
            );

            exit;

        }

        View::render(

            "developer/question-inspector",

            [

                "pageTitle" =>
                    "Question Inspector",

                "question" =>
                    $question

            ]

        );

    }

}
