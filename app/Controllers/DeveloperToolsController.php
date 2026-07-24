<?php

class DeveloperToolsController
{

    public static function index(): void
    {

        $audit =
            QuestionAuditService::summary();


        $taxonomyStatus = null;


        if (
            ($_GET["action"] ?? "")
            ===
            "rebuild-taxonomy"
        ) {

            $taxonomyStatus =
                TaxonomyBuilderService::rebuild();

        }


        View::render(

            "developer/index",

            [

                "audit" =>
                    $audit,

                "taxonomyStatus" =>
                    $taxonomyStatus

            ]

        );

    }

}
