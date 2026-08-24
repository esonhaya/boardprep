<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\Learning\LearningCoachService;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\LearningStreakService;
use App\Services\Learning\LearningTimelineService;
use App\Services\Learning\PerformanceAnalyticsService;
use App\Services\Learning\RecommendationService;
use App\Services\Learning\TopicPerformanceService;
use App\Services\Learning\WeaknessService;
use App\Services\Profile\LearningProfileService;

final class LearningProfileController
{
    public static function index(): void
    {
        $attempts = LearningHistoryService::all();

        $analytics =
            PerformanceAnalyticsService::summary($attempts);

        $weaknesses = WeaknessService::all();

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

        $timeline =
            LearningTimelineService::build(
                array_slice($attempts, 0, 10)
            );

        View::render(
            "profile/index",
            [
                "profile" => $profile,
                "analytics" => $analytics,
                "weaknesses" => $weaknesses,
                "recommendations" => $recommendations,
                "coach" => $coach,
                "timeline" => $timeline,
                "topics" =>
                    TopicPerformanceService::summarize($attempts),
                "streak" =>
                    LearningStreakService::current($attempts),
            ]
        );
    }
}
