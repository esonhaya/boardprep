<?php

declare(strict_types=1);

final class BlueprintFulfillmentService
{
    /**
     * @return SelectionRequest[]
     */
    public static function requests(
        array $blueprint,
        int $questionCount
    ): array {

        return BlueprintDistributionService::distribution(
            $blueprint,
            $questionCount
        );

    }
}
