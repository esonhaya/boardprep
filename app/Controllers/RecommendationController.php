<?php

declare(strict_types=1);

use App\Services\Learning\LearningStatisticsService;
use App\Services\Learning\RecommendationService;
use App\Services\Learning\WeaknessService;

class RecommendationController
{
    public static function getRecommendation(): array
    {
        $recommendations =
            RecommendationService::generate(
                LearningStatisticsService::summary(),
                WeaknessService::all()
            );

        return [
            "title" => "Recommended Next Step",
            "description" =>
                $recommendations[0]
                ?? "Keep studying."
        ];
    }
}
