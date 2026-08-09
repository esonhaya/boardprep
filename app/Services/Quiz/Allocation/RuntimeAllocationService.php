<?php

declare(strict_types=1);

final class RuntimeAllocationService
{
    public static function allocate(
        int $total,
        array $distribution
    ): array {

        if ($total <= 0 || empty($distribution)) {
            return [];
        }

        $result = [];

        $allocated = 0;

        foreach ($distribution as $key => $percentage) {

            $exact =
                ($total * ((float) $percentage)) / 100;

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

        while ($remaining > 0) {

            $largest = null;

            foreach ($result as $key => $row) {

                if (
                    $largest === null ||
                    $row["remainder"] >
                    $result[$largest]["remainder"]
                ) {

                    $largest = $key;

                }

            }

            if ($largest === null) {
                break;
            }

            $result[$largest]["count"]++;
            $result[$largest]["remainder"] = -1;

            $remaining--;

        }

        return array_map(
            static fn(array $row): int => $row["count"],
            $result
        );

    }
}
