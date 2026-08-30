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
        int $questionCount,
        ?string $topic = null,
        string $difficulty = 'mixed'
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

            if ($domainAllocation === []) {
                $requests[] = new SelectionRequest(
                    subject: $subject,
                    domain: null,
                    difficultyDistribution: self::difficultyDistribution(
                        $difficulty,
                        $blueprint["difficulty"] ?? []
                    ),
                    questionCount: $subjectQuestions,
                    topic: $topic !== null && strcasecmp(trim($topic), 'General') !== 0
                        ? trim($topic)
                        : null
                );
                continue;
            }

            foreach ($domainAllocation as $domain => $domainQuestions) {
                $requests[] = new SelectionRequest(
                    subject: $subject,
                    domain: $domain,
                    difficultyDistribution: self::difficultyDistribution(
                        $difficulty,
                        $blueprint["difficulty"] ?? []
                    ),
                    questionCount: $domainQuestions,
                    topic: $topic !== null && strcasecmp(trim($topic), 'General') !== 0
                        ? trim($topic)
                        : null
                );
            }
        }

        return $requests;
    }

    private static function difficultyDistribution(string $difficulty, array $fallback): array
    {
        $difficulty = strtolower(trim($difficulty));
        return in_array($difficulty, ['easy', 'medium', 'hard'], true)
            ? [$difficulty => 100]
            : $fallback;
    }
}
