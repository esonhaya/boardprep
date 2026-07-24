<?php

class TaxonomyController
{

    public static function index(): void
    {

        View::render(

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


    public static function addDomain(): void
    {

        TaxonomyService::addDomain(

            $_POST["name"] ?? ""

        );

        header(

            "Location: ?page=taxonomy"

        );

        exit;

    }


    public static function addTopic(): void
    {

        TaxonomyService::addTopic(

            $_POST["name"] ?? ""

        );

        header(

            "Location: ?page=taxonomy"

        );

        exit;

    }


    public static function addConcept(): void
    {

        TaxonomyService::addConcept(

            $_POST["name"] ?? ""

        );

        header(

            "Location: ?page=taxonomy"

        );

        exit;

    }

}
