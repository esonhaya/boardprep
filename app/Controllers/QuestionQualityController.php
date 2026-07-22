<?php

class QuestionQualityController
{

    public static function index(): void
    {

        $questions =
            QuestionRepository::all();


        $drafts = [];

        $missingExplanation = [];

        $invalidAnswers = [];

        $missingChoices = [];

        $duplicateChoices = [];

        $emptyQuestion = [];


        foreach (
            $questions
            as $question
        ) {

            if (
                ($question["status"] ?? "")
                ===
                "draft"
            ) {

                $drafts[] =
                    $question;

            }


            if (

                trim(

                    $question["question"] ?? ""

                )

                ===

                ""

            ) {

                $emptyQuestion[] =
                    $question;

            }


            if (

                trim(

                    $question["explanation"] ?? ""

                )

                ===

                ""

            ) {

                $missingExplanation[] =
                    $question;

            }


            if (

                count(

                    $question["choices"] ?? []

                )

                <

                4

            ) {

                $missingChoices[] =
                    $question;

            }


            if (

                count(

                    array_unique(

                        $question["choices"] ?? []

                    )

                )

                <

                count(

                    $question["choices"] ?? []

                )

            ) {

                $duplicateChoices[] =
                    $question;

            }


            if (

                !in_array(

                    $question["answer"] ?? "",

                    $question["choices"] ?? [],

                    true

                )

            ) {

                $invalidAnswers[] =
                    $question;

            }

        }

$totalQuestions =
    count($questions);

$totalIssues =
    count($drafts)
    +
    count($missingExplanation)
    +
    count($invalidAnswers)
    +
    count($missingChoices)
    +
    count($duplicateChoices)
    +
    count($emptyQuestion);

$healthScore =
    $totalQuestions > 0

    ?

    max(
        0,
        round(
            (
                (
                    $totalQuestions
                    -
                    $totalIssues
                )
                /
                $totalQuestions
            )
            *
            100
        )
    )

    :

    100;


View::render(

    "developer/question-quality",

    [

        "pageTitle" =>
            "Question Quality",

        "healthScore" =>
            $healthScore,

        "drafts" =>
            $drafts,

        "missingExplanation" =>
            $missingExplanation,

        "invalidAnswers" =>
            $invalidAnswers,

        "missingChoices" =>
            $missingChoices,

        "duplicateChoices" =>
            $duplicateChoices,

        "emptyQuestion" =>
            $emptyQuestion

    ]

);

    }

}
