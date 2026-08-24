<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\StudyPlanService;

final class StudyDashboardController
{
    public static function index(): void
    {
        \App\Core\View::render(
            "study/index",
            [
                "dashboard" => self::data(),
                "history" => LearningHistoryService::recent(5),
            ]
        );
    }

    public static function data(): array
    {
        $attempts = LearningHistoryService::all();
        $dashboard = StudyDashboardService::build($attempts);

        $dashboard["studyPlan"] =
            StudyPlanService::build($dashboard);

        return $dashboard;
    }
}
