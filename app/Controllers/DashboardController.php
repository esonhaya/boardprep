<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;

final class DashboardController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        View::render(
            "dashboard/index",
            [
                "pageTitle" => "Learner Dashboard",
                "dashboard" => StudyDashboardService::build($attempts),
                "history" => array_slice($attempts, 0, 5),
            ]
        );
    }
}
