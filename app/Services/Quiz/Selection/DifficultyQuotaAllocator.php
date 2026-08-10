<?php

declare(strict_types=1);

final class DifficultyQuotaAllocator
{
    public static function allocate(
        array $weights,
        float $totalWeight,
        int $questionCount
    ): array {

        if (
            $questionCount <= 0
            || $totalWeight <= 0.0
            || empty($weights)
        ) {
            return [];
        }

        $quotas = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $difficulty => $weight) {

            $exact =
                $questionCount
                * ($weight / $totalWeight);

            $quota =
                (int) floor($exact);

            $quotas[$difficulty] = $quota;

            $remainders[$difficulty] =
                $exact - $quota;

            $allocated += $quota;
        }

        $remaining =
            $questionCount
            - $allocated;

        arsort(
            $remainders,
            SORT_NUMERIC
        );

        foreach (array_keys($remainders) as $difficulty) {

            if ($remaining <= 0) {
                break;
            }

            $quotas[$difficulty]++;
            $remaining--;
        }

        return $quotas;
    }
}
