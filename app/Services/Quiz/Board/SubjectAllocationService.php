<?php

declare(strict_types=1);

final class SubjectAllocationService
{
    public static function allocate(
        array $subjects,
        int $questionCount
    ): array {

        $allocations = [];

        foreach ($subjects as $subject) {

            $allocations[] = [

                "subject" =>
                    $subject["id"],

                "questions" =>
                    max(
                        1,
                        (int) round(
                            $questionCount *
                            (
                                ($subject["weight"] ?? 0)
                                / 100
                            )
                        )
                    )

            ];

        }

        return $allocations;

    }
}
