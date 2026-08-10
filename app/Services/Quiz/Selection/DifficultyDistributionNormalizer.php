<?php

declare(strict_types=1);

final class DifficultyDistributionNormalizer
{
    public static function normalize(
        array $distribution
    ): array {

        $normalized = [];
        $totalWeight = 0.0;

        foreach ($distribution as $difficulty => $weight) {

            $weight = max(
                0.0,
                (float) $weight
            );

            if ($weight <= 0.0) {
                continue;
            }

            $difficulty =
                strtolower(
                    trim(
                        (string) $difficulty
                    )
                );

            if ($difficulty === '') {
                continue;
            }

            $normalized[$difficulty] =
                ($normalized[$difficulty] ?? 0.0)
                + $weight;

            $totalWeight += $weight;
        }

        return [
            'weights' => $normalized,
            'totalWeight' => $totalWeight,
        ];
    }
}
