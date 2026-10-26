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
        if (is_array($boardBlueprint['allocation_groups'] ?? null)
            && $boardBlueprint['allocation_groups'] !== []) {
            return self::groupDistribution(
                $boardBlueprint['allocation_groups'],
                $subjectBlueprints,
                $questionCount,
                $topic,
                $difficulty
            );
        }

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

    /**
     * Allocate official combined areas first, then split only within each
     * configured area. This keeps area totals exact when an official area
     * contains multiple taxonomy sections (for example Architecture Area A).
     *
     * @param array<int,array<string,mixed>> $groups
     * @return SelectionRequest[]
     */
    private static function groupDistribution(
        array $groups,
        array $subjectBlueprints,
        int $questionCount,
        ?string $topic,
        string $difficulty
    ): array {
        $groupWeights = [];
        foreach ($groups as $index => $group) {
            if (!is_array($group) || !is_numeric($group['percentage'] ?? null)
                || (float) $group['percentage'] <= 0
                || !is_array($group['members'] ?? null) || $group['members'] === []) {
                continue;
            }
            $groupWeights[(string) $index] = (float) $group['percentage'];
        }

        $groupCounts = RuntimeAllocationService::allocate($questionCount, $groupWeights);
        $requests = [];
        foreach ($groupCounts as $groupIndex => $groupCount) {
            if ($groupCount <= 0) {
                continue;
            }
            $group = $groups[(int) $groupIndex] ?? null;
            if (!is_array($group)) {
                continue;
            }

            $members = [];
            $memberWeights = [];
            foreach (array_values($group['members']) as $memberIndex => $member) {
                if (!is_array($member)) {
                    continue;
                }
                $subject = trim((string) ($member['subject'] ?? ''));
                $domain = trim((string) ($member['domain'] ?? ''));
                $weight = is_numeric($member['weight'] ?? null) ? (float) $member['weight'] : 1.0;
                if ($subject === '' || $domain === '' || $weight <= 0) {
                    continue;
                }
                $key = (string) $memberIndex;
                $members[$key] = $member;
                $memberWeights[$key] = $weight;
            }

            $memberCounts = RuntimeAllocationService::allocate($groupCount, $memberWeights);
            foreach ($memberCounts as $memberIndex => $memberCount) {
                if ($memberCount <= 0 || !isset($members[$memberIndex])) {
                    continue;
                }
                $member = $members[$memberIndex];
                $subject = trim((string) $member['subject']);
                $blueprint = $subjectBlueprints[$subject] ?? [];
                $requests[] = new SelectionRequest(
                    subject: $subject,
                    domain: trim((string) $member['domain']),
                    difficultyDistribution: self::difficultyDistribution(
                        $difficulty,
                        is_array($blueprint['difficulty'] ?? null) ? $blueprint['difficulty'] : []
                    ),
                    questionCount: $memberCount,
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
