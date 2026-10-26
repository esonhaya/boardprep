<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\Request;
use App\Services\Question\QuestionQualityService;
use App\Services\Question\Quality\QuestionQualityIssueQueueService;

class QuestionQualityController extends BaseDeveloperController
{
    public static function index(): void
    {
        $analysis = QuestionQualityService::analyze();
        $analysis['qualityQueue'] = QuestionQualityIssueQueueService::paginate(
            (array) ($analysis['issues'] ?? []),
            [
                'queue' => Request::queryString('queue'),
                'status' => Request::queryString('status'),
                'category' => Request::queryString('category'),
                'issue_type' => Request::queryString('issue_type'),
                'entity_type' => Request::queryString('entity_type'),
                'board' => Request::queryString('board'),
                'search' => Request::queryString('search'),
                'page_num' => Request::queryString('page_num'),
                'per_page' => Request::queryString('per_page'),
            ]
        );

        self::renderDeveloper(
            "developer/question-quality",
            array_merge(
                [
                    "pageTitle" =>
                        "Question Quality"
                ],
                $analysis
            )
        );
    }
}
