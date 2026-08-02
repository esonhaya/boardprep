<?php

class TaxonomyController extends BaseDeveloperController
{

    public static function index(): void
    {

        self::renderDeveloper(

            "developer/taxonomy",

            [

                "pageTitle" =>
                    "Taxonomy Manager",

                "domains" =>
                    TaxonomyRepository::domains(),

                "topics" =>
                    TaxonomyRepository::topics(),

                "concepts" =>
                    TaxonomyRepository::concepts()

            ]

        );

    }



    public static function rebuild(): void
    {

        TaxonomyBuilderService::rebuild();

        header(
            "Location: /taxonomy"
        );

        exit;

    }

}
