<?php

class BlueprintHealthController extends BaseDeveloperController
{
    public static function index(): void
    {
        $report = RepositoryHealthEngine::analyze();

        self::renderDeveloper(

            "developer/blueprint-health",

            [

                "pageTitle" => "Blueprint Health",

                "report" => $report,

                "statistics" => $report->statistics,

                "issues" => $report->issues,

                "subjects" =>
                    $report->statistics->questionsPerSubject,

                "domains" =>
                    $report->statistics->questionsPerDomain,

                "topics" =>
                    $report->statistics->questionsPerTopic,

                "concepts" =>
                    $report->statistics->questionsPerConcept

            ]

        );
    }
}
