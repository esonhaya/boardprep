<?php

declare(strict_types=1);

final class RuntimeAllocationService
{
    public static function allocate(
        int $total,
        array $distribution
    ): array {

        $distribution = array_filter(
            $distribution,
            static fn(mixed $weight): bool => is_numeric($weight) && (float) $weight > 0
        );
        $weightTotal = array_sum(array_map('floatval', $distribution));

        if ($total <= 0 || empty($distribution) || $weightTotal <= 0) {
            return [];
        }

        $result = [];
        $originalOrder = array_keys($distribution);

        $allocated = 0;

        foreach ($distribution as $key => $percentage) {

            $exact =
                ($total * ((float) $percentage)) / $weightTotal;

            $whole =
                (int) floor($exact);

            $result[$key] = [

                "count" => $whole,

                "remainder" => $exact - $whole,

            ];

            $allocated += $whole;

        }

        $remaining =
            $total - $allocated;

        uasort($result, static fn(array $left, array $right): int =>
            $right['remainder'] <=> $left['remainder']
        );
        foreach (array_keys($result) as $key) {
            if ($remaining-- <= 0) {
                break;
            }
            $result[$key]['count']++;
        }

        $counts = [];
        foreach ($originalOrder as $key) {
            $counts[$key] = $result[$key]['count'];
        }
        return $counts;

    }
}
