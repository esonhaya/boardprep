<?php

declare(strict_types=1);

final class DifficultySelectionService
{
    public static function select(
        array $pool,
        array $distribution,
        int $questionCount
    ): array {

        $questionCount =
            max(
                0,
                $questionCount
            );

        if (
            $questionCount === 0
            || empty($pool)
        ) {
            return [];
        }

        $pool =
            array_values($pool);

        if (empty($distribution)) {

            shuffle($pool);

            return array_slice(
                $pool,
                0,
                $questionCount
            );
        }

        $normalized =
            DifficultyDistributionNormalizer::normalize(
                $distribution
            );

        if (
            $normalized['totalWeight'] <= 0.0
        ) {

            shuffle($pool);

            return array_slice(
                $pool,
                0,
                $questionCount
            );
        }

        $quotas =
            DifficultyQuotaAllocator::allocate(
                $normalized['weights'],
                $normalized['totalWeight'],
                $questionCount
            );

        $selection =
            DifficultyBucketSelector::select(
                $pool,
                $quotas,
                $questionCount
            );

        if (
            count($normalized['weights']) === 1
            && !isset($normalized['weights']['mixed'])
        ) {
            return $selection['questions'];
        }

        return SelectionFallbackService::fill(
            $pool,
            $selection['questions'],
            $selection['usedIds'],
            $questionCount
        );
    }
}
