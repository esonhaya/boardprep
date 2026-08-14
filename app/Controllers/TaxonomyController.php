<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Services\Shared\TaxonomyStorageService;
use App\Services\Shared\TaxonomyIntegrityService;

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
