<?php

class DashboardController extends BaseDeveloperController
{
    public static function index(): void
    {
        $report = RepositoryHealthEngine::analyze();

        self::renderDeveloper(

            "developer/dashboard",

            [

                "pageTitle" => "Developer Dashboard",

                "report" => $report,

                "statistics" => $report->statistics,

                "healthScore" => $report->healthScore,

                "recentIssues" =>
                    array_slice(
                        $report->issues,
                        0,
                        10
                    )

            ]

        );
    }
}
