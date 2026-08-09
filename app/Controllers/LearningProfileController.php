<?php

declare(strict_types=1);

namespace App\Controllers;







use App\Core\View;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\PerformanceAnalyticsService;
use App\Services\Learning\WeaknessService;
use App\Services\Learning\RecommendationService;
use App\Services\Learning\LearningCoachService;
use App\Services\Profile\LearningProfileService;

class LearningProfileController
{
    public static function index(): void
    {
        $attempts =
            LearningHistoryService::recent();

        $analytics =
            PerformanceAnalyticsService::summary(
                $attempts
            );

        $weaknesses =
            WeaknessService::all();

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

        View::render(
            "profile/index",
            [
                "profile" =>
                    $profile,

                "analytics" =>
                    $analytics,

                "weaknesses" =>
                    $weaknesses,

                "recommendations" =>
                    $recommendations,

                "coach" =>
                    $coach
            ]
        );
    }
}
