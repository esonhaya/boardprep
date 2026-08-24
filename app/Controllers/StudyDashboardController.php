<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;

final class StudyDashboardController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        $dashboard = StudyDashboardService::build($attempts);

        View::render(
            "study/index",
            [
                "dashboard" => $dashboard,
                "history" => LearningHistoryService::recent(10),
            ]
        );
    }

    public static function data(): array
    {
        return StudyDashboardService::build(
            LearningHistoryService::all()
        );
    }
}
