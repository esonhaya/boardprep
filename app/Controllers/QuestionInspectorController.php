<?php

class QuestionInspectorController extends BaseDeveloperController{

    public static function index(): void
    {

        $id =
            (int)(
                $_GET["id"] ?? 0
            );

        if ($id === 0) {

            self::renderDeveloper(
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

        self::renderDeveloper(
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
