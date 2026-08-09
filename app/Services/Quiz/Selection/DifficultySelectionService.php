<?php

declare(strict_types=1);

final class DifficultySelectionService
{
    public static function select(
        array $pool,
        array $distribution,
        int $questionCount
    ): array {

        $questionCount = max(
            0,
            $questionCount
        );

        if (
            $questionCount === 0
            || empty($pool)
        ) {
            return [];
        }

        $pool = array_values($pool);

        if (empty($distribution)) {

            shuffle($pool);

            return array_slice(
                $pool,
                0,
                $questionCount
            );

        }

        $normalized = [];
        $totalWeight = 0.0;

        foreach (
            $distribution as $difficulty => $weight
        ) {

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

        if ($totalWeight <= 0.0) {

            shuffle($pool);

            return array_slice(
                $pool,
                0,
                $questionCount
            );

        }

        /*
         * Convert percentages into integer quotas
         * using largest-remainder allocation.
         */
        $quotas = [];
        $remainders = [];
        $allocated = 0;

        foreach (
            $normalized as $difficulty => $weight
        ) {

            $exact =
                $questionCount
                * ($weight / $totalWeight);

            $quota =
                (int) floor($exact);

            $quotas[$difficulty] =
                $quota;

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

        foreach (
            array_keys($remainders)
            as $difficulty
        ) {

            if ($remaining <= 0) {
                break;
            }

            $quotas[$difficulty]++;
            $remaining--;

        }

        $selected = [];
        $usedIds = [];

        foreach (
            $quotas as $difficulty => $quota
        ) {

            if ($quota <= 0) {
                continue;
            }

            $matches =
                array_values(
                    array_filter(

                        $pool,

                        static function (
                            array $question
                        ) use (
                            $difficulty
                        ): bool {

                            if (
                                $difficulty ===
                                'mixed'
                            ) {
                                return true;
                            }

                            return strtolower(
                                (string) (
                                    $question[
                                        'difficulty'
                                    ] ?? ''
                                )
                            ) === $difficulty;

                        }

                    )
                );

            shuffle($matches);

            foreach (
                $matches as $question
            ) {

                if (
                    count($selected)
                    >= $questionCount
                ) {
                    break 2;
                }

                $id =
                    (string) (
                        $question['id'] ?? ''
                    );

                if (
                    $id !== ''
                    && isset($usedIds[$id])
                ) {
                    continue;
                }

                $selected[] =
                    $question;

                if ($id !== '') {
                    $usedIds[$id] = true;
                }

                $quota--;

                if ($quota <= 0) {
                    break;
                }

            }

        }

        /*
         * If a difficulty bucket is short,
         * recover from the remaining pool.
         */
        if (
            count($selected)
            < $questionCount
        ) {

            $remainingPool =
                array_values(
                    array_filter(

                        $pool,

                        static function (
                            array $question
                        ) use (
                            $usedIds
                        ): bool {

                            $id =
                                (string) (
                                    $question['id']
                                    ?? ''
                                );

                            return
                                $id === ''
                                || !isset(
                                    $usedIds[$id]
                                );

                        }

                    )
                );

            shuffle($remainingPool);

            $selected =
                array_merge(

                    $selected,

                    array_slice(

                        $remainingPool,

                        0,

                        $questionCount
                        - count($selected)

                    )

                );

        }

        return $selected;
    }
}
