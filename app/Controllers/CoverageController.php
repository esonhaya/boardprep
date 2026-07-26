<?php

class CoverageController extends BaseDeveloperController{

    public static function index(): void
    {

        self::renderDeveloper(

            "developer/coverage",

            [

                "pageTitle" =>
                    "Coverage Matrix",

                "coverage" =>
                    CoverageMatrixService::build()

            ]

        );

    }

}
