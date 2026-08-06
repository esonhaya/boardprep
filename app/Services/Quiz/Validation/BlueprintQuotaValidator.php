<?php

declare(strict_types=1);

final class BlueprintQuotaValidator
{
    public static function validate(
        array $questions,
        SelectionRequest $request
    ): bool {

        $difficultyCounts = [];

        foreach ($questions as $question) {

            $difficulty =
                strtolower(
                    $question["difficulty"] ?? "unknown"
                );

            $difficultyCounts[$difficulty] =
                ($difficultyCounts[$difficulty] ?? 0) + 1;

        }

        foreach (

            $request->difficultyDistribution
            as $difficulty => $weight

        ) {

            if (
                strtolower($difficulty)
                ===
                "mixed"
            ) {
                continue;
            }

            $required =
                max(
                    1,
                    (int) round(
                        $request->questionCount *
                        ($weight / 100)
                    )
                );

            if (
                ($difficultyCounts[strtolower($difficulty)] ?? 0)
                <
                $required
            ) {

                return false;

            }

        }

        return true;

    }
}
