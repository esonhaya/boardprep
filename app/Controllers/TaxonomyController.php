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
                    TaxonomyStorageService::domains(),

                "topics" =>
                    TaxonomyStorageService::topics(),

                "concepts" =>
                    TaxonomyStorageService::concepts()

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
