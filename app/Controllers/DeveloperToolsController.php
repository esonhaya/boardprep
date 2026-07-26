<?php

class DeveloperToolsController extends BaseDeveloperController
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

        self::renderDeveloper(

            "developer/index",

            [

                "audit" =>
                    $audit,

                "taxonomyStatus" =>
                    $taxonomyStatus

            ],

            false

        );

    }

}
