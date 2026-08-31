<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\Request;
use App\Services\Question\QuestionQualityService;
use App\Services\Shared\TaxonomyIntegrityService;
class DeveloperToolsController extends BaseDeveloperController
{
    public static function index(): void
    {

        $audit =
            \QuestionAuditService::summary();

        $results = [];

        $action = Request::queryString("action");

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
                    \MetadataRepairService::repair();

                break;

            case "repair-taxonomy":

                $results["taxonomy"] =
                    TaxonomyIntegrityService::analyze();

                break;

        }

        $quality = QuestionQualityService::analyze();

        self::renderDeveloper(

            "developer/dashboard",

            [

                "healthScore" =>
                    $quality["healthScore"],

                "statistics" =>
                    $quality["report"]->statistics,

                "recentIssues" =>
                    array_slice($quality["issues"], 0, 10),

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
                \MetadataRepairService::repair(),

            "taxonomy" =>
                TaxonomyIntegrityService::analyze(),

            "audit" =>
                \QuestionAuditService::summary()

        ];

    }

}
