<?php

class QuestionEditorController
{

    public static function index(): void
    {

        $search =
            trim($_GET["search"] ?? "");

        $domain =
            trim($_GET["domain"] ?? "");

        $difficulty =
            trim($_GET["difficulty"] ?? "");

        $topic =
            trim($_GET["topic"] ?? "");

$questions =
    QuestionEditorQueryService::getQuestions(
        [
            "search" =>
                $search,

            "domain" =>
                $domain,

            "difficulty" =>
                $difficulty,

            "topic" =>
                $topic
        ]
    );

        View::render(
            "developer/question-editor",

            array_merge(

                [

                    "pageTitle" =>
                        "Question Editor",

                    "questions" =>
                        $questions,

                    "search" =>
                        $search,

                    "domain" =>
                        $domain,

                    "difficulty" =>
                        $difficulty,

                    "topic" =>
                        $topic

                ],

                QuestionEditorViewService::taxonomy()

            )

        );

    }



    public static function create(): void
    {

        View::render(
            "developer/question-create",

            array_merge(

                [

                    "pageTitle" =>
                        "Create Question"

                ],

                QuestionEditorViewService::taxonomy()

            )

        );

    }



    public static function edit(): void
    {

        $question =
            QuestionRepository::find(
                (int)($_GET["id"] ?? 0)
            );


        if (!$question) {

            header(
                "Location: ?page=question-editor"
            );

            exit;

        }


        View::render(
            "developer/question-edit",

            array_merge(

                [

                    "pageTitle" =>
                        "Edit Question",

                    "question" =>
                        $question

                ],

                QuestionEditorViewService::taxonomy()

            )

        );

    }



    public static function save(): void
    {

        $question =
            QuestionEditorService::buildQuestion(
                time()
            );


        $check =
            QuestionEditorService::prepareSave(
                $question
            );


        if (!empty($check["errors"])) {

            View::render(
                "developer/question-create",

                array_merge(

                    [

                        "pageTitle" =>
                            "Create Question",

                        "question" =>
                            $question,

                        "errors" =>
                            $check["errors"],

                        "duplicates" =>
                            $check["duplicates"]

                    ],

                    QuestionEditorViewService::taxonomy()

                )

            );

            return;

        }


        QuestionEditorService::save(
            $question
        );


        header(
            "Location: ?page=question-editor"
        );

        exit;

    }



    public static function update(): void
    {

        $id =
            (int)($_GET["id"] ?? 0);


        $question =
            QuestionEditorService::buildQuestion(
                $id
            );


        $check =
            QuestionEditorService::prepareSave(
                $question
            );


        if (!empty($check["errors"])) {

            View::render(
                "developer/question-edit",

                array_merge(

                    [

                        "pageTitle" =>
                            "Edit Question",

                        "question" =>
                            $question,

                        "errors" =>
                            $check["errors"],

                        "duplicates" =>
                            $check["duplicates"]

                    ],

                    QuestionEditorViewService::taxonomy()

                )

            );

            return;

        }


        QuestionEditorService::update(
            $id,
            $question
        );


        header(
            "Location: ?page=question-editor"
        );

        exit;

    }



    public static function archive(): void
    {

        QuestionRepository::archive(
            (int)($_GET["id"] ?? 0)
        );


        header(
            "Location: ?page=question-editor"
        );

        exit;

    }



    public static function restore(): void
    {

        QuestionRepository::restore(
            (int)($_GET["id"] ?? 0)
        );


        header(
            "Location: ?page=question-editor"
        );

        exit;

    }

}
