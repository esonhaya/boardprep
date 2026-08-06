<?php

declare(strict_types=1);

final class BlueprintDifficultyAllocator
{
    public static function allocate(
        array $difficultyDistribution,
        int $questionCount
    ): array {

        if (empty($difficultyDistribution)) {

            return [

                "mixed" => $questionCount,

            ];

        }

        return RuntimeAllocationService::allocate(

            $questionCount,

            $difficultyDistribution

        );

    }
}
