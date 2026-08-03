<?php

declare(strict_types=1);

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

    public static function analyze(): void
    {

        TaxonomyIntegrityService::analyze();

        header(
            "Location: /taxonomy"
        );

        exit;

    }

}
