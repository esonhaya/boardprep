<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\Learning\StudyRecommendationService;

final class StudyRecommendationActionabilityCheck
{
    public static function run(): array
    {
        $recommendations = StudyRecommendationService::build([], []);

        $item = $recommendations[0] ?? [];

        return [
            "recommendation_present" =>
                count($recommendations) >= 1,
            "title_present" =>
                ($item["title"] ?? "") !== "",
            "reason_present" =>
                ($item["reason"] ?? "") !== "",
            "action_present" =>
                str_starts_with(
                    (string) ($item["action"] ?? ""),
                    "/quiz?"
                ),
            "label_present" =>
                ($item["label"] ?? "") !== "",
            "bounded_recommendations" =>
                count($recommendations) <= 3,
        ];
    }
}
