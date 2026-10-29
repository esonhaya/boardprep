<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\WeaknessService;
use App\Support\Presentation\PreviewCollection;

final class StudyDashboardController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        $preferences = \App\Services\Profile\LearnerPreferenceService::get();
        \App\Core\View::render(
            "study/index",
            [
                "dashboard" => StudyDashboardService::buildFromOrdered($attempts, $preferences, false),
                "history" => PreviewCollection::items($attempts),
                "historyHasMore" => PreviewCollection::hasMore($attempts),
                "weaknesses" => WeaknessService::all(),
                "preferences" => $preferences,
            ]
        );
    }

    public static function data(): array
    {
        return StudyDashboardService::buildFromOrdered(
            LearningHistoryService::all(),
            \App\Services\Profile\LearnerPreferenceService::get(),
            false
        );
    }
}
