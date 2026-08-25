<?php

declare(strict_types=1);

final class BlueprintRequestPlanBuilder
{
    /**
     * @return SelectionRequest[]
     */
    public static function build(
        array $boardBlueprint,
        array $subjectBlueprints,
        int $questionCount
    ): array {
        $requests = BlueprintDistributionService::distribution(
            $boardBlueprint,
            $subjectBlueprints,
            $questionCount
        );

        return RequestExecutionPlanService::build($requests);
    }
}
