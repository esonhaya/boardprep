<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\Request;
use App\Services\Question\QuestionQualityService;
use App\Services\Question\Quality\QuestionQualityIssueQueueService;
use App\Services\Question\Quality\QuestionQualityReviewSignalService;

class QuestionQualityController extends BaseDeveloperController
{
    public static function index(): void
    {
        $analysis = QuestionQualityService::analyze();
        $proposalSignals = QuestionQualityReviewSignalService::proposalSignals();
        $allSignals = array_merge((array) ($analysis['issues'] ?? []), $proposalSignals);
        $totalQuestions = (int) ($analysis['report']->statistics->totalQuestions ?? 0);
        $technicalQuestionIds = QuestionQualityReviewSignalService::canonicalTechnicalQuestionIds(
            (array) ($analysis['issues'] ?? [])
        );
        $analysis['proposalReviewSignalCount'] = count($proposalSignals);
        $analysis['academicApprovalPendingCount'] = count(array_filter(
            $proposalSignals,
            static fn(array $signal): bool => ($signal['issue_type'] ?? '') === 'ACADEMIC_APPROVAL_PENDING'
        ));
        $analysis['canonicalTechnicalIssueCount'] = count($technicalQuestionIds);
        $analysis['canonicalNoTechnicalIssueCount'] = max(0, $totalQuestions - count($technicalQuestionIds));
        $analysis['qualityQueue'] = QuestionQualityIssueQueueService::paginate(
            $allSignals,
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
