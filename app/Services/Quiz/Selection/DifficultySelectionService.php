<?php

declare(strict_types=1);

final class DifficultySelectionService
{
    public static function select(
        array $pool,
        array $distribution,
        int $questionCount
    ): array {

        $selected = [];

        foreach (
            $distribution as $difficulty => $weight
        ) {

            $required =
                max(
                    1,
                    (int) round(
                        $questionCount *
                        ($weight / 100)
                    )
                );

            $matches =
                array_values(

                    array_filter(

                        $pool,

                        static fn(array $question): bool =>

                            strtolower($difficulty)
                            ===
                            "mixed"

                            ||

                            strtolower(
                                $question["difficulty"] ?? ""
                            )

                            ===

                            strtolower($difficulty)

                    )

                );

            shuffle($matches);

            $selected = array_merge(

                $selected,

                array_slice(
                    $matches,
                    0,
                    $required
                )

            );

        }

        return $selected;

    }
}
