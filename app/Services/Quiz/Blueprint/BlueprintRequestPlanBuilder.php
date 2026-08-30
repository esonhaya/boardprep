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
        int $questionCount,
        ?string $topic = null,
        string $difficulty = 'mixed'
    ): array {
        $requests = BlueprintDistributionService::distribution(
            $boardBlueprint,
            $subjectBlueprints,
            $questionCount,
            $topic,
            $difficulty
        );

        return RequestExecutionPlanService::build($requests);
    }
}
