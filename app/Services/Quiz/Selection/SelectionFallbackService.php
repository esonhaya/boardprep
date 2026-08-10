<?php

declare(strict_types=1);

final class SelectionFallbackService
{
    public static function fill(
        array $pool,
        array $selected,
        array $usedIds,
        int $questionCount
    ): array {

        if (count($selected) >= $questionCount) {
            return $selected;
        }

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

        return array_merge(
            $selected,
            array_slice(
                $remainingPool,
                0,
                $questionCount - count($selected)
            )
        );
    }
}
