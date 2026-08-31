<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;
use App\Support\Presentation\PreviewCollection;

final class StudyDashboardController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        \App\Core\View::render(
            "study/index",
            [
                "dashboard" => StudyDashboardService::build($attempts),
                "history" => PreviewCollection::items($attempts),
                "historyHasMore" => PreviewCollection::hasMore($attempts),
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
