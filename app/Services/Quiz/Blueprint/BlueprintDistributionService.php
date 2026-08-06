<?php

declare(strict_types=1);

final class BlueprintDistributionService
{
    public static function distribution(
        array $boardBlueprint,
        array $subjectBlueprints,
        int $questionCount
    ): array {

        $requests = [];

        $subjectWeights = [];

        foreach (
            $boardBlueprint["subjects"] ?? []
            as $subject
        ) {

            $subjectWeights[
                $subject["subject"]
            ] = (float) (
                $subject["percentage"]
                ?? 0
            );

        }

        $subjectAllocation =
            RuntimeAllocationService::allocate(
                $questionCount,
                $subjectWeights
            );

        foreach (
            $subjectAllocation
            as $subject => $subjectQuestions
        ) {

            $blueprint =
                $subjectBlueprints[$subject]
                ?? null;

            if ($blueprint === null) {
                continue;
            }

            $domainWeights = [];

            foreach (
                $blueprint["domains"] ?? []
                as $domain
            ) {

                $domainWeights[
                    $domain["domain"]
                ] = (float) (
                    $domain["percentage"]
                    ?? 0
                );

            }

            $domainAllocation =
                RuntimeAllocationService::allocate(
                    $subjectQuestions,
                    $domainWeights
                );

            foreach (
                $domainAllocation
                as $domain => $domainQuestions
            ) {

                $requests[] =
                    new SelectionRequest(

                        subject:
                            $subject,

                        domain:
                            $domain,

                        difficultyDistribution:
                            $blueprint["difficulty"] ?? [],

                        questionCount:
                            $domainQuestions

                    );

            }

        }

        return $requests;

    }
}
