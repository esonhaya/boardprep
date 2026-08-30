<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;

final class StudyDashboardController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        \App\Core\View::render(
            "study/index",
            [
                "dashboard" => StudyDashboardService::build($attempts),
                "history" => array_slice($attempts, 0, 5),
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
