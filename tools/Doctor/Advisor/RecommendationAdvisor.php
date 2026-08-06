<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

use Tools\Doctor\DTO\DoctorResult;

final class RecommendationAdvisor
{
    /**
     * @return string[]
     */
    public function recommendations(
        DoctorResult $report
    ): array {

        $recommendations = [];

        $checks = [];

        foreach ($report->checks as $check) {
            $checks[$check->title] = $check;
        }

        /*
        |--------------------------------------------------------------------------
        | Controller
        |--------------------------------------------------------------------------
        */

        $largestController =
            $checks["Largest Controller"] ?? null;

        $controllerComplexity =
            $checks["Controller Complexity"] ?? null;

        if (
            $largestController !== null
            && $controllerComplexity !== null
        ) {

            if (
                $largestController->status === "WARNING"
                && $controllerComplexity->status === "PASS"
            ) {

                $recommendations[] =
                    "QuestionEditorController is large but well-structured. Prioritize other architectural improvements before splitting it.";

            } elseif (
                $largestController->status === "WARNING"
            ) {

                $recommendations[] =
                    "Review QuestionEditorController for possible refactoring.";

            }
        }

        /*
        |--------------------------------------------------------------------------
        | Services
        |--------------------------------------------------------------------------
        */

        if (
            ($checks["Largest Service"] ?? null)?->status === "WARNING"
        ) {

            $recommendations[] =
                "Split MetadataRepairService into smaller collaborators.";

        }

        /*
        |--------------------------------------------------------------------------
        | Domain
        |--------------------------------------------------------------------------
        */

        $domainMigration =
            $checks["Domain Migration"] ?? null;

        $emptyDirectories =
            $checks["Empty Directories"] ?? null;

        if (
            $domainMigration?->status === "WARNING"
            || $emptyDirectories?->status === "WARNING"
        ) {

            $recommendations[] =
                "Continue implementing the Domain layer and replace placeholder directories as features are added.";

        }

        return array_values(
            array_unique(
                $recommendations
            )
        );

    }
}
