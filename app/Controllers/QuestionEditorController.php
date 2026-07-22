<?php

class QuestionEditorController
{

    public static function index(): void
{

    $search =
        trim(
            $_GET["search"] ?? ""
        );

    $domain =
        trim(
            $_GET["domain"] ?? ""
        );

    $difficulty =
        trim(
            $_GET["difficulty"] ?? ""
        );

    $topic =
        trim(
            $_GET["topic"] ?? ""
        );


    $questions =
        QuestionRepository::all();


    if (
        $domain !== ""
        ||
        $difficulty !== ""
        ||
        $topic !== ""
    ) {

        $questions =
            QuestionRepository::filter(
                $domain,
                $difficulty,
                $topic
            );

    }


    if ($search !== "") {

        $questions =
            array_filter(
                $questions,

                function ($question)
                use ($search) {

                    $search =
                        strtolower(
                            $search
                        );

                    return
                        str_contains(
                            strtolower(
                                $question["question"] ?? ""
                            ),
                            $search
                        )
                        ||
                        str_contains(
                            strtolower(
                                $question["topic"] ?? ""
                            ),
                            $search
                        )
                        ||
                        str_contains(
                            strtolower(
                                $question["concept"] ?? ""
                            ),
                            $search
                        );

                }
            );

    }


    View::render(
        "developer/question-editor",
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
                $topic,

            "domains" =>
                TaxonomyRepository::domains(),

            "topics" =>
                TaxonomyRepository::topics()

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
    QuestionValidationService::validate(
        $question
    );

$duplicates =
    QuestionDuplicateService::find(
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

"duplicates" =>
    $duplicates,

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
    QuestionValidationService::validate(
        $question
    );

$duplicates =
    QuestionDuplicateService::find(
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

"duplicates" =>
    $duplicates,

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

    $formData = [

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

    return QuestionMetadataService::build(

        $id,

        $formData

    );

}


    
}
