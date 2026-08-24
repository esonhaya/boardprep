<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Controllers\StudyDashboardController;

final class StudyDashboardControllerCheck
{
    public static function run(): array
    {
        $data = StudyDashboardController::data();

        return [
            "dashboard" => is_array($data),
            "progress" => isset($data["progress"]),
            "topics" => isset($data["topics"]),
            "weakest_topics" => isset($data["weakestTopics"]),
            "streak" => array_key_exists("streak", $data),
            "insight" => isset($data["insight"]),
            "recommendations" => isset($data["recommendations"]),
            "study_plan" => isset($data["studyPlan"]),
            "bounded_plan" =>
                count($data["studyPlan"] ?? []) <= 5,
        ];
    }
}
