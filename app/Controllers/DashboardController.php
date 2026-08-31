<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;
use App\Support\Presentation\PreviewCollection;

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
                "history" => PreviewCollection::items($attempts),
                "historyHasMore" => PreviewCollection::hasMore($attempts),
            ]
        );
    }
}
