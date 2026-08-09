<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

class MetadataRepairController extends BaseDeveloperController
{
    public static function index(): void
    {
        $report = RepositoryHealthEngine::analyze();

        $repairable = [];

        foreach ($report->issues as $issue) {

            if ($issue->repairable) {

                $repairable[] = $issue;

            }

        }

        self::renderDeveloper(

            "developer/metadata-repair",

            [

                "pageTitle" => "Metadata Repair",

                "report" => $report,

                "repairableIssues" => $repairable

            ]

        );
    }
}
