<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\DashboardStudyContinuityService;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\SmartStudyHubService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\WeaknessService;
use App\Services\Quiz\ActiveExamDashboardService;
use App\Support\Presentation\PreviewCollection;

final class DashboardController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        $preferences = \App\Services\Profile\LearnerPreferenceService::get();
        $dashboard = StudyDashboardService::buildFromOrdered($attempts, $preferences);
        $studyContinuity = DashboardStudyContinuityService::build($attempts);
        View::render(
            "dashboard/index",
            [
                "pageTitle" => "Learner Dashboard",
                "dashboard" => $dashboard,
                "studyContinuity" => $studyContinuity,
                "smartStudyHub" => SmartStudyHubService::build(
                    is_array($dashboard['readiness'] ?? null) ? $dashboard['readiness'] : [],
                    $studyContinuity
                ),
                "history" => PreviewCollection::items($attempts),
                "historyHasMore" => PreviewCollection::hasMore($attempts),
                "weaknesses" => WeaknessService::all(),
                "activeExam" => ActiveExamDashboardService::current(),
            ]
        );
    }
}
