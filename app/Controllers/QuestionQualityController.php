<?php

class QuestionQualityController
{

    public static function index(): void
    {

        View::render(
            "developer/question-quality",
            array_merge(

                [
                    "pageTitle" =>
                        "Question Quality"
                ],

                QuestionQualityService::analyze()

            )
        );

    }

}
