<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\App;
use App\Core\Request;
use App\Services\Shared\ContentPriorityService;
use App\Services\Shared\ContentIntegrityService;
use App\Services\Shared\TaxonomyIntegrityService;
use App\Services\Study\LETContentCoverageService;
class DeveloperToolsController extends BaseDeveloperController
{
    public static function contentIntelligence(): void
    {
        $query = Request::query();
        $filters = [];
        foreach (['board_id', 'subject_id', 'topic_id', 'concept_id', 'severity', 'issue_code', 'detection_type', 'id_from', 'id_to'] as $key) {
            if (is_scalar($query[$key] ?? null) && trim((string) $query[$key]) !== '') {
                $filters[$key] = trim((string) $query[$key]);
            }
        }
        if (is_scalar($query['question_ids'] ?? null)) {
            $filters['question_ids'] = array_values(array_filter(array_map('trim', explode(',', (string) $query['question_ids']))));
        }
        $jsonReport = strtolower(trim((string) ($query['format'] ?? ''))) === 'json';
        $contentPriorities = ContentPriorityService::analyzeRepository($jsonReport ? [] : $filters);
        if ($jsonReport) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($contentPriorities, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            return;
        }
        $requestedPage = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $requestedPageSize = filter_var($query['per_page'] ?? 25, FILTER_VALIDATE_INT);
        $contentPriorities['qa'] = \App\Services\Shared\ContentQaService::paginateReport(
            is_array($contentPriorities['qa'] ?? null) ? $contentPriorities['qa'] : [],
            $requestedPage === false ? 1 : $requestedPage,
            $requestedPageSize === false ? 25 : $requestedPageSize
        );
        self::renderDeveloper(
            'developer/content-intelligence',
            [
                'pageTitle' => 'Content Intelligence',
                'contentPriorities' => $contentPriorities,
            ],
            false
        );
    }

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

        $contentPriorities = ContentPriorityService::analyzeRepository();
        $contentIntegrity = $contentPriorities['integrity'] ?? ContentIntegrityService::analyzeRepository();
        $portfolio = $contentIntegrity['portfolio'];
        $integrityIssueCount = array_sum(array_map('count', array_filter($contentIntegrity['failures'] ?? [], 'is_array')));
        $quality = [
            'healthScore' => $portfolio['status'] === 'FAIL' ? 0 : 100,
            'report' => (object) [
                'statistics' => (object) [
                    'totalQuestions' => $portfolio['total_canonical'],
                    'totalIssues' => $integrityIssueCount,
                    'errors' => $integrityIssueCount,
                    'warnings' => 0,
                    'infos' => 0,
                ],
            ],
            'issues' => [],
        ];
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
                "letCoverage" => LETContentCoverageService::report(),
                "contentIntegrity" => $contentIntegrity,
                "contentPriorities" => $contentPriorities

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
