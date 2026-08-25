<?php

declare(strict_types=1);

final class BlueprintDistributionService
{
    /**
     * Preserve the public distribution boundary while delegating the
     * deterministic phases to focused collaborators.
     *
     * @param array<int,array<string,mixed>> $requests
     * @return array<int,array<string,mixed>>
     */
    public static function distribute(array $requests): array
    {
        $normalized = BlueprintDistributionRequestNormalizer::normalize($requests);
        $allocated = BlueprintDistributionAllocator::allocate($normalized);
        return BlueprintDistributionResultFactory::create($allocated);
    }

    /**
     * Backward-compatible production boundary used by blueprint assembly.
     *
     * @param array<string,mixed> $boardBlueprint
     * @param array<string,array<string,mixed>> $subjectBlueprints
     * @return array<int,mixed>
     */
    public static function distribution(
        array $boardBlueprint,
        array $subjectBlueprints,
        int $questionCount
    ): array {
        $requests = [];

        $subjectWeights = [];

        foreach ($boardBlueprint["subjects"] ?? [] as $subject) {
            $subjectWeights[$subject["subject"]] = (float) (
                $subject["percentage"] ?? 0
            );
        }

        $subjectAllocation = RuntimeAllocationService::allocate(
            $questionCount,
            $subjectWeights
        );

        foreach ($subjectAllocation as $subject => $subjectQuestions) {
            $blueprint = $subjectBlueprints[$subject] ?? null;

            if ($blueprint === null) {
                continue;
            }

            $domainWeights = [];

            foreach ($blueprint["domains"] ?? [] as $domain) {
                $domainWeights[$domain["domain"]] = (float) (
                    $domain["percentage"] ?? 0
                );
            }

            $domainAllocation = RuntimeAllocationService::allocate(
                $subjectQuestions,
                $domainWeights
            );

            foreach ($domainAllocation as $domain => $domainQuestions) {
                $requests[] = new SelectionRequest(
                    subject: $subject,
                    domain: $domain,
                    difficultyDistribution: $blueprint["difficulty"] ?? [],
                    questionCount: $domainQuestions
                );
            }
        }

        return $requests;
    }
}
