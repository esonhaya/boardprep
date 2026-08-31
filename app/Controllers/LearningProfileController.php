<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningTimelineService;
use App\Services\Learning\PerformanceAnalyticsService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\TopicPerformanceService;
use App\Services\Learning\WeaknessService;
use App\Services\Learning\RecommendationService;
use App\Services\Learning\LearningCoachService;
use App\Services\Profile\LearningProfileService;
use App\Support\Presentation\PreviewCollection;

final class LearningProfileController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();
        $analytics = PerformanceAnalyticsService::summary($attempts);
        $weaknesses = WeaknessService::all();
        if ($attempts === []) {
            $weaknesses = [];
        }

        $profile =
            LearningProfileService::build(
                $analytics,
                $weaknesses
            );

        $recommendations =
            RecommendationService::generate(
                $analytics,
                $weaknesses
            );

        $coach =
            LearningCoachService::build(
                $analytics,
                $weaknesses,
                $recommendations
            );

        $dashboard =
            StudyDashboardService::build($attempts);

        View::render(
            "profile/index",
            [
                "profile" => $profile,
                "analytics" => $analytics,
                "weaknesses" => $weaknesses,
                "recommendations" => $recommendations,
                "coach" => $coach,
                "timeline" =>
                    LearningTimelineService::build(
                        PreviewCollection::items($attempts)
                    ),
                "timelineHasMore" => PreviewCollection::hasMore($attempts),
                "topics" =>
                    TopicPerformanceService::summarize($attempts),
                "subjects" => $dashboard["subjects"],
                "streak" =>
                    $dashboard["streak"],
                "insight" =>
                    $dashboard["insight"],
                "studyRecommendations" =>
                    $dashboard["recommendations"],
            ]
        );
    }
}
