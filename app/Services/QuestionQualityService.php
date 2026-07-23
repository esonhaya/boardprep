<?php

class QuestionQualityService
{

    public static function analyze(): array
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



            $choices =
                $question["choices"] ?? [];


            if (
                count(
                    array_unique($choices)
                )
                <
                count($choices)
            ) {

                $duplicateChoices[] =
                    $question;

            }



            if (
                !in_array(
                    $question["answer"] ?? "",
                    $choices,
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



        return [

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

        ];

    }

}
