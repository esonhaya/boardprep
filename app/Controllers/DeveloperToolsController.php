<?php

declare(strict_types=1);

class DeveloperToolsController extends BaseDeveloperController
{
    public static function index(): void
    {

        $audit =
            QuestionAuditService::summary();

        $results = [];

        $action =
            trim(
                $_GET["action"] ?? ""
            );

        switch (
            $action
        ) {

            case "analyze":

                $results["analysis"] =
                    $audit;

                break;

            case "fix-all":

                $results =
                    self::fixEverything();

                break;

            case "repair-metadata":

                $results["metadata"] =
                    MetadataRepairService::repair();

                break;

            case "repair-taxonomy":

                $results["taxonomy"] =
                    TaxonomyIntegrityService::analyze();

                break;

        }

        self::renderDeveloper(

            "developer/index",

            [

                "audit" =>
                    $audit,

                "results" =>
                    $results

            ],

            false

        );

    }

    private static function fixEverything(): array
    {

        return [

            "metadata" =>
                MetadataRepairService::repair(),

            "taxonomy" =>
                TaxonomyIntegrityService::analyze(),

            "audit" =>
                QuestionAuditService::summary()

        ];

    }

}
