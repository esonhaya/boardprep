<?php

declare(strict_types=1);

final class BlueprintDistributionService
{
    /**
     * @return SelectionRequest[]
     */
    public static function distribution(
        array $blueprint,
        int $questionCount
    ): array {

        $requests = [];

        foreach (
            $blueprint["sections"] ?? []
            as $section
        ) {

            $requests[] =
                new SelectionRequest(

                    domain:
                        $section["domain"] ?? null,

                    topic:
                        $section["topic"] ?? null,

                    concept:
                        $section["concept"] ?? null,

                    difficultyDistribution:
                        $section["difficulty"] ?? [
                            "mixed" => 100
                        ],

                    questionCount:
                        max(
                            1,
                            (int) round(
                                $questionCount *
                                (
                                    ($section["weight"] ?? 0)
                                    / 100
                                )
                            )
                        )

                );

        }

        return BlueprintAllocationReconciler::reconcile(
            $requests,
            $questionCount
        );

    }
}
