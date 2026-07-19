<?php

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
