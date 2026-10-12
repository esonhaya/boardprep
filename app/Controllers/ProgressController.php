<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\WeaknessService;
use App\Support\Presentation\PreviewCollection;

final class ProgressController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        $dashboard = StudyDashboardService::build($attempts);

        View::render(
            "progress/index",
            [
                "summary" => $dashboard["progress"],
                "history" => PreviewCollection::items($attempts),
                "historyHasMore" => PreviewCollection::hasMore($attempts),
                "topics" => $dashboard["topics"],
                "subjects" => $dashboard["subjects"],
                "weakestTopics" => $dashboard["weakestTopics"],
                "focusTopics" => $dashboard["focusTopics"],
                "strongestSubjects" => $dashboard["strongestSubjects"],
                "streak" => $dashboard["streak"],
                "insight" => $dashboard["insight"],
                "recommendations" => $dashboard["recommendations"],
                "weaknesses" => WeaknessService::all(),
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
