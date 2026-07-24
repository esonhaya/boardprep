<?php

class CoverageController
{

    public static function index(): void
    {

        View::render(

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
