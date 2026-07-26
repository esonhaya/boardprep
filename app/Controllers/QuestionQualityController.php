<?php

class QuestionQualityController extends BaseDeveloperController{

    public static function index(): void
    {

        self::renderDeveloper(
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
