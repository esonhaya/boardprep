<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\StudyPlanService;

final class StudyDashboardActionabilityCheck
{
    public static function run(): array
    {
        $dashboard = StudyDashboardService::build([]);
        $plan = StudyPlanService::build($dashboard);

        $item = $plan[0] ?? [];

        return [
            "plan_present" => count($plan) >= 1,
            "topic_present" => ($item["topic"] ?? "") !== "",
            "subject_present" => ($item["subject"] ?? "") !== "",
            "action_present" =>
                str_starts_with(
                    (string) ($item["action"] ?? ""),
                    "/quiz?"
                ),
            "label_present" => ($item["label"] ?? "") !== "",
            "bounded_plan" => count($plan) <= 5,
        ];
    }
}
