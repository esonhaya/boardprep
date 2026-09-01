<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\App;
use App\Core\Request;
use App\Services\Question\QuestionQualityService;
use App\Services\Shared\TaxonomyIntegrityService;
use App\Services\Study\LETContentCoverageService;
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
        $questions = App::storage()->all('questions');
        $complete = 0;
        $statuses = [];
        foreach ($questions as $question) {
            $statuses[(string) ($question['status'] ?? 'unknown')] = ($statuses[(string) ($question['status'] ?? 'unknown')] ?? 0) + 1;
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            if (count(array_filter(['board_id', 'subject_id', 'domain_id', 'topic_id', 'concept_id'], static fn(string $key): bool => trim((string) ($taxonomy[$key] ?? '')) !== '')) === 5) {
                $complete++;
            }
        }

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
                    $results,
                "repositorySummary" => [
                    "active" => $statuses["active"] ?? 0,
                    "draft" => $statuses["draft"] ?? 0,
                    "archived" => $statuses["archived"] ?? 0,
                    "taxonomyComplete" => $complete,
                ],
                "letCoverage" => LETContentCoverageService::report()

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
