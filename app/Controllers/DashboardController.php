<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\DashboardStudyContinuityService;
use App\Services\Learning\LearningHistoryService;
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
        View::render(
            "dashboard/index",
            [
                "pageTitle" => "Learner Dashboard",
                "dashboard" => StudyDashboardService::build($attempts, $preferences),
                "studyContinuity" => DashboardStudyContinuityService::build($attempts),
                "history" => PreviewCollection::items($attempts),
                "historyHasMore" => PreviewCollection::hasMore($attempts),
                "weaknesses" => WeaknessService::all(),
                "activeExam" => ActiveExamDashboardService::current(),
            ]
        );
    }
}
