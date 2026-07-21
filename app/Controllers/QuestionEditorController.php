<?php

class QuestionEditorController
{

    public static function index(): void
    {

        $questions =
            QuestionRepository::all();

        View::render(
            "developer/question-editor",
            [
                "pageTitle" =>
                    "Question Editor",

                "questions" =>
                    $questions
            ]
        );

    }



    public static function create(): void
    {

        View::render(
            "developer/question-create",
            [
                "pageTitle" =>
                    "Create Question",

                "domains" =>
                    TaxonomyRepository::domains(),

                "topics" =>
                    TaxonomyRepository::topics(),

                "concepts" =>
                    TaxonomyRepository::concepts()
            ]
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
            [
                "pageTitle" =>
                    "Edit Question",

                "question" =>
                    $question,

                "domains" =>
                    TaxonomyRepository::domains(),

                "topics" =>
                    TaxonomyRepository::topics(),

                "concepts" =>
                    TaxonomyRepository::concepts()
            ]
        );

    }



    public static function save(): void
    {

        $question =
            self::buildQuestion(
                time()
            );

        $errors =
    self::validate(
        $question
    );

if (!empty($errors)) {

    View::render(
        "developer/question-create",
        [
            "pageTitle" =>
                "Create Question",

            "question" =>
                $question,

            "errors" =>
                $errors,

            "domains" =>
                TaxonomyRepository::domains(),

            "topics" =>
                TaxonomyRepository::topics(),

            "concepts" =>
                TaxonomyRepository::concepts()
        ]
    );

    return;

}

        QuestionRepository::save(
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
            self::buildQuestion($id);

        $errors =
            self::validate(
                $question
            );

        if (!empty($errors)) {

    View::render(
        "developer/question-edit",
        [
            "pageTitle" =>
                "Edit Question",

            "question" =>
                $question,

            "errors" =>
                $errors,

            "domains" =>
                TaxonomyRepository::domains(),

            "topics" =>
                TaxonomyRepository::topics(),

            "concepts" =>
                TaxonomyRepository::concepts()
        ]
    );

    return;

}

        QuestionRepository::update(
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

    $id =
        (int)(
            $_GET["id"] ?? 0
        );

    QuestionRepository::archive(
        $id
    );

    header(
        "Location: ?page=question-editor"
    );

    exit;

}

public static function restore(): void
{

    $id =
        (int)(
            $_GET["id"] ?? 0
        );


    QuestionRepository::restore(
        $id
    );


    header(
        "Location: ?page=question-editor"
    );

    exit;

}


    private static function buildQuestion(
        int $id
    ): array
    {

        return [

            "id" =>
                $id,

            "status" =>
                "active",

            "domain" =>
                trim($_POST["domain"] ?? ""),

            "topic" =>
                trim($_POST["topic"] ?? ""),

            "concept" =>
                trim($_POST["concept"] ?? ""),

            "difficulty" =>
                trim($_POST["difficulty"] ?? ""),

            "question" =>
                trim($_POST["question"] ?? ""),

            "choices" => [

                trim($_POST["choice_a"] ?? ""),

                trim($_POST["choice_b"] ?? ""),

                trim($_POST["choice_c"] ?? ""),

                trim($_POST["choice_d"] ?? "")

            ],

            "answer" =>
                trim($_POST["answer"] ?? ""),

            "explanation" =>
                trim($_POST["explanation"] ?? "")

        ];

    }



    private static function validate(
    array $question
): array
{

    $errors = [];


    if ($question["question"] === "") {

        $errors["question"] =
            "Question cannot be empty.";

    }


    if ($question["explanation"] === "") {

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
