<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

class CoverageController extends BaseDeveloperController
{
    public static function index(): void
    {
        $report = RepositoryHealthEngine::analyze();

        self::renderDeveloper(

            "developer/coverage",

            [

                "pageTitle" => "Coverage Matrix",

                "report" => $report,

                "statistics" => $report->statistics,

                "subjects" =>
                    $report->statistics->questionsPerSubject,

                "domains" =>
                    $report->statistics->questionsPerDomain,

                "topics" =>
                    $report->statistics->questionsPerTopic,

                "concepts" =>
                    $report->statistics->questionsPerConcept,

                "difficulty" =>
                    $report->statistics->questionsPerDifficulty

            ]

        );
    }
}
