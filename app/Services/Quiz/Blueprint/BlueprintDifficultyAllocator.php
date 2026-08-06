<?php

declare(strict_types=1);

final class BlueprintDifficultyAllocator
{
    public static function allocate(
        array $section,
        int $questionCount
    ): array {

        $distribution =
            $section["difficulty"] ?? [];

        if (empty($distribution)) {

            return [[
                "difficulty" => "mixed",
                "questions" => $questionCount,
            ]];

        }

        $requests = [];

        foreach (
            $distribution as $difficulty => $weight
        ) {

            $requests[] = [

                "difficulty" =>
                    strtolower($difficulty),

                "questions" =>
                    max(
                        1,
                        (int) round(
                            $questionCount *
                            ($weight / 100)
                        )
                    ),

            ];

        }

        return $requests;

    }
}
