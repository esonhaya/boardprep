<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Controllers\StudyDashboardController;
use App\Services\Learning\StudyPlanService;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class StudyPlanContractCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $failures = [];

        $empty = StudyPlanService::build([]);

        if (count($empty) !== 1) {
            $failures[] = "empty plan must contain exactly one item";
        }

        if (($empty[0]["topic"] ?? "") !== "General") {
            $failures[] = "empty plan fallback topic must be General";
        }

        if (
            !str_starts_with(
                (string) ($empty[0]["action"] ?? ""),
                "/quiz?"
            )
        ) {
            $failures[] = "empty plan fallback must target /quiz?";
        }

        $dashboard = StudyDashboardController::data();
        $plan = $dashboard["studyPlan"] ?? null;

        if (!is_array($plan)) {
            $failures[] = "production dashboard must expose studyPlan";
        } elseif (count($plan) < 1 || count($plan) > 5) {
            $failures[] = "production studyPlan must contain 1-5 items";
        }

        if ($failures !== []) {
            return new CheckResult(
                title: "Study Plan Contract",
                status: "FAIL",
                summary: count($failures) . " study-plan contract issue(s) detected.",
                details: $failures,
                recommendations: [
                    "Fix the StudyPlanService fallback before changing dashboard consumers.",
                    "Keep the production dashboard wired through StudyPlanService.",
                ],
                score: 0,
            );
        }

        return new CheckResult(
            title: "Study Plan Contract",
            status: "PASS",
            summary: "Study-plan fallback and production dashboard contracts are valid.",
            details: [
                "Empty-state fallback: General",
                "Fallback action: /quiz?",
                "Production plan: 1-5 items",
            ],
            recommendations: [],
            score: 100,
        );
    }

    public function category(): string
    {
        return "Learning";
    }

    public function priority(): int
    {
        return 7;
    }
}
