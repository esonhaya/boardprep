<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\Learning\StudyActionService;
use App\Services\Learning\StudyPlanService;
use App\Services\Learning\StudySessionService;

final class StudyWorkflowContractCheck
{
    public static function run(): array
    {
        $spec = StudyActionService::quizForTopic("Grammar");
        $plan = StudyPlanService::build([]);

        return [
            "action_spec" =>
                ($spec["action"] ?? "") === "start",
            "session_url" =>
                str_starts_with(
                    StudyActionService::url($spec),
                    "/quiz?"
                ),
            "session_normalization" =>
                StudySessionService::normalize([
                    "count" => 999,
                ])["count"] === 20,
            "empty_plan_fallback" =>
                count($plan) === 1
                && ($plan[0]["topic"] ?? "") === "General",
            "fallback_targets_quiz" =>
                str_starts_with(
                    (string) ($plan[0]["action"] ?? ""),
                    "/quiz?"
                ),
        ];
    }
}
