<?php

declare(strict_types=1);

final class BlueprintResolverService
{
    public static function resolve(QuizSpecification $specification): array
    {
        $subject = trim((string) $specification->subject);
        if ($subject === '') {
            return ['board' => [], 'subjects' => []];
        }

        if ($specification->mode === 'exam') {
            return self::examBlueprint($specification);
        }

        return [
            'board' => [
                'version' => 1,
                'subjects' => [['subject' => $subject, 'percentage' => 100]],
            ],
            'subjects' => [
                $subject => [
                    'version' => 1,
                    'domains' => $specification->domain === null ? [] : [[
                        'domain' => $specification->domain,
                        'percentage' => 100,
                    ]],
                    'difficulty' => [],
                ],
            ],
        ];
    }

    private static function examBlueprint(QuizSpecification $specification): array
    {
        $storage = \App\Core\App::storage();
        $board = self::canonicalBoardId($specification->board, $storage->all('boards'));
        $subjectsById = [];
        foreach ($storage->all('subjects') as $record) {
            if (is_array($record) && is_scalar($record['id'] ?? null)
                && is_scalar($record['name'] ?? null)
                && strtolower((string) ($record['status'] ?? 'active')) === 'active') {
                $subjectsById[(string) $record['id']] = trim((string) $record['name']);
            }
        }

        $boardSubjects = [];
        $allocations = [];
        foreach ($storage->all('board-subjects') as $record) {
            if (!is_array($record) || strtolower(trim((string) ($record['board_id'] ?? ''))) !== $board) {
                continue;
            }
            $subjectId = trim((string) ($record['subject_id'] ?? ''));
            if ($subjectId !== '' && isset($subjectsById[$subjectId])) {
                $boardSubjects[$subjectId] = true;
            }
            $settings = is_array($record['settings'] ?? null) ? $record['settings'] : [];
            $weight = is_numeric($settings['blueprint_weight'] ?? null)
                ? (float) $settings['blueprint_weight'] : 0.0;
            if ($subjectId !== '' && isset($subjectsById[$subjectId]) && $weight > 0) {
                $allocations[$subjectsById[$subjectId]] = $weight;
            }
        }

        // Keep the established board-subject configuration authoritative where
        // it already defines runtime weights (LET, CSE and existing internal
        // simulations).
        if ($allocations !== [] && array_sum($allocations) > 0.0) {
            $boardBlueprint = ['subjects' => []];
            $subjectBlueprints = [];
            foreach ($allocations as $name => $weight) {
                $boardBlueprint['subjects'][] = ['subject' => $name, 'percentage' => $weight];
                $subjectBlueprints[$name] = ['domains' => [], 'difficulty' => []];
            }
            return ['board' => $boardBlueprint, 'subjects' => $subjectBlueprints];
        }

        // Several boards already register official, sourced area weights in
        // the active blueprint and taxonomy but not in board-subject settings.
        // Resolve that existing crosswalk instead of returning a zero-question
        // exam or making up replacement percentages.
        foreach ($storage->all('blueprints') as $blueprint) {
            if (!is_array($blueprint)
                || strtolower(trim((string) ($blueprint['board_id'] ?? $blueprint['board'] ?? ''))) !== $board
                || strtolower(trim((string) ($blueprint['scope'] ?? 'board'))) !== 'board'
                || strtolower(trim((string) ($blueprint['status'] ?? 'active'))) !== 'active') {
                continue;
            }

            $groups = self::officialAllocationGroups(
                $blueprint,
                $board,
                $subjectsById,
                $boardSubjects,
                \App\Services\Shared\TaxonomyStorageService::domains()
            );
            if ($groups !== []) {
                return self::resolvedOfficialBlueprint($blueprint, $groups);
            }
        }

        // Missing official weighting remains unsupported. In particular, CPA
        // has a registered source/status saying its weighting is unavailable;
        // no percentages are inferred for it here.
        return ['board' => [], 'subjects' => []];
    }

    /** @return array<int,array<string,mixed>> */
    private static function officialAllocationGroups(
        array $blueprint,
        string $board,
        array $subjectsById,
        array $boardSubjects,
        array $domains
    ): array {
        $status = strtoupper(trim((string) ($blueprint['official_weighting_status'] ?? '')));
        $hasCurrentWeighting = ($blueprint['official_weighting'] ?? false) === true
            || in_array($status, ['OFFICIAL_CURRENT', 'VERIFIED_CURRENT_PROGRAM'], true);
        if (!$hasCurrentWeighting || trim((string) ($blueprint['source_id'] ?? '')) === '') {
            return [];
        }

        $sections = [];
        foreach (is_array($blueprint['sections'] ?? null) ? $blueprint['sections'] : [] as $section) {
            if (is_array($section) && trim((string) ($section['id'] ?? '')) !== '') {
                $sections[(string) $section['id']] = $section;
            }
        }

        $weightedNodes = [];
        $areas = is_array($blueprint['official_areas'] ?? null) ? $blueprint['official_areas'] : [];
        if ($areas !== []) {
            foreach ($areas as $area) {
                if (!is_array($area)) {
                    continue;
                }
                $weight = $area['weight'] ?? $area['weight_percent'] ?? null;
                if (!is_numeric($weight) || (float) $weight <= 0) {
                    continue;
                }
                $members = [];
                foreach (is_array($area['section_ids'] ?? null) ? $area['section_ids'] : [] as $sectionId) {
                    if (isset($sections[(string) $sectionId])) {
                        $members[] = $sections[(string) $sectionId];
                    }
                }
                $weightedNodes[] = [
                    'id' => (string) ($area['id'] ?? ''),
                    'name' => (string) ($area['name'] ?? $area['id'] ?? 'Official area'),
                    'weight' => (float) $weight,
                    'members' => $members !== [] ? $members : [$area],
                ];
            }
        } else {
            foreach ($sections as $section) {
                $weight = $section['official_weight_percent'] ?? $section['target_percent'] ?? null;
                if (is_numeric($weight) && (float) $weight > 0) {
                    $weightedNodes[] = [
                        'id' => (string) ($section['id'] ?? ''),
                        'name' => (string) ($section['name'] ?? $section['id'] ?? 'Official section'),
                        'weight' => (float) $weight,
                        'members' => [$section],
                    ];
                }
            }
        }

        if ($weightedNodes === []) {
            return [];
        }
        $totalWeight = array_sum(array_column($weightedNodes, 'weight'));
        if (abs($totalWeight - 100.0) > 0.01) {
            return [];
        }

        $domainById = [];
        $domainsBySubject = [];
        foreach ($domains as $domain) {
            if (!is_array($domain) || trim((string) ($domain['id'] ?? '')) === '') {
                continue;
            }
            $domainId = (string) $domain['id'];
            $domainById[$domainId] = $domain;
            $subjectId = trim((string) ($domain['subject_id'] ?? ''));
            if ($subjectId !== '') {
                $domainsBySubject[$subjectId][] = $domain;
            }
        }

        $groups = [];
        foreach ($weightedNodes as $weightedNode) {
            $members = [];
            foreach ($weightedNode['members'] as $node) {
                $member = self::resolveMember(
                    $node,
                    $board,
                    $subjectsById,
                    $boardSubjects,
                    $domainById,
                    $domainsBySubject,
                    $sections
                );
                if ($member === null) {
                    return [];
                }
                $key = $member['subject'] . "\0" . $member['domain'];
                $members[$key] = $member;
            }
            if ($members === []) {
                return [];
            }
            $groups[] = [
                'id' => $weightedNode['id'],
                'name' => $weightedNode['name'],
                'percentage' => $weightedNode['weight'],
                'members' => array_values($members),
            ];
        }

        return $groups;
    }

    private static function resolveMember(
        array $node,
        string $board,
        array $subjectsById,
        array $boardSubjects,
        array $domainById,
        array $domainsBySubject,
        array $sections
    ): ?array {
        $subjectId = trim((string) ($node['subject_id'] ?? ''));
        $domainId = trim((string) ($node['domain_id'] ?? ''));
        $nodeId = trim((string) ($node['id'] ?? ''));

        if ($domainId === '' && $nodeId !== '' && isset($domainById[$nodeId])) {
            $domainId = $nodeId;
        }
        if ($domainId !== '' && isset($domainById[$domainId])) {
            $domain = $domainById[$domainId];
            $domainSubjectId = trim((string) ($domain['subject_id'] ?? ''));
            if ($subjectId !== '' && $domainSubjectId !== '' && $subjectId !== $domainSubjectId) {
                return null;
            }
            $subjectId = $domainSubjectId !== '' ? $domainSubjectId : $subjectId;
            $domainBoard = strtolower(trim((string) ($domain['board_id'] ?? '')));
            if ($domainBoard !== '' && $domainBoard !== $board) {
                return null;
            }
        }

        if ($subjectId === '') {
            $nodeName = self::normalizedName($node['name'] ?? '');
            if ($nodeName === '') {
                return null;
            }
            foreach ($boardSubjects as $candidateId => $_) {
                if (!isset($subjectsById[$candidateId])
                    || self::normalizedName($subjectsById[$candidateId]) !== $nodeName) {
                    continue;
                }
                $subjectId = (string) $candidateId;
                break;
            }
        }

        if ($subjectId === '' || !isset($boardSubjects[$subjectId], $subjectsById[$subjectId])) {
            return null;
        }

        if ($domainId === '') {
            $matchingDomains = $domainsBySubject[$subjectId] ?? [];
            if (count($matchingDomains) !== 1) {
                return null;
            }
            $domainId = (string) ($matchingDomains[0]['id'] ?? '');
        }

        if ($domainId === '' || !isset($domainById[$domainId])) {
            return null;
        }

        $runtimeSubject = self::runtimeSubjectForDomain($subjectId, $domainId, $sections);

        return [
            // QuestionEligibilityService resolves board items through their
            // configured blueprint section ID. Keep that runtime selection
            // key while retaining canonical taxonomy identity separately.
            'subject' => $runtimeSubject,
            'subject_id' => $subjectId,
            'subject_name' => $subjectsById[$subjectId],
            'domain' => $domainId,
            'weight' => is_numeric($node['allocation_weight'] ?? null)
                && (float) $node['allocation_weight'] > 0
                    ? (float) $node['allocation_weight']
                    : 1.0,
        ];
    }

    private static function runtimeSubjectForDomain(string $subjectId, string $domainId, array $sections): string
    {
        $matches = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionId = trim((string) ($section['id'] ?? ''));
            if ($sectionId === '') {
                continue;
            }
            $sectionSubject = trim((string) ($section['subject_id'] ?? ''));
            $sectionDomain = trim((string) ($section['domain_id'] ?? ''));
            if ($sectionId === $domainId || $sectionDomain === $domainId || $sectionSubject === $subjectId) {
                $matches[$sectionId] = true;
            }
        }

        return count($matches) === 1 ? (string) array_key_first($matches) : $subjectId;
    }

    private static function resolvedOfficialBlueprint(array $blueprint, array $groups): array
    {
        $subjectWeights = [];
        $domainWeights = [];
        $selectionSubjectAliases = [];
        foreach ($groups as $group) {
            $memberWeight = array_sum(array_column($group['members'], 'weight'));
            if ($memberWeight <= 0) {
                continue;
            }
            foreach ($group['members'] as $member) {
                $share = (float) $group['percentage'] * ((float) $member['weight'] / $memberWeight);
                $subject = (string) $member['subject'];
                $domain = (string) $member['domain'];
                $subjectWeights[$subject] = ($subjectWeights[$subject] ?? 0.0) + $share;
                $domainWeights[$subject][$domain] = ($domainWeights[$subject][$domain] ?? 0.0) + $share;
                $selectionSubjectAliases[$subject][(string) $member['subject_id']] = true;
                $selectionSubjectAliases[$subject][(string) $member['subject_name']] = true;
            }
        }

        $boardBlueprint = [
            'version' => (int) ($blueprint['version'] ?? 1),
            'source_id' => (string) ($blueprint['source_id'] ?? ''),
            'source_version' => (string) ($blueprint['source_version'] ?? ''),
            'official_weighting_status' => (string) ($blueprint['official_weighting_status'] ?? 'CONFIGURED_SOURCE'),
            'subjects' => [],
            'allocation_groups' => $groups,
            'selection_subject_aliases' => array_map('array_keys', $selectionSubjectAliases),
        ];
        $subjectBlueprints = [];
        foreach ($subjectWeights as $subject => $weight) {
            $boardBlueprint['subjects'][] = ['subject' => $subject, 'percentage' => $weight];
            $subjectTotal = array_sum($domainWeights[$subject] ?? []);
            $subjectBlueprints[$subject] = [
                'version' => (int) ($blueprint['version'] ?? 1),
                'domains' => [],
                'difficulty' => [],
            ];
            foreach ($domainWeights[$subject] ?? [] as $domain => $domainWeight) {
                $subjectBlueprints[$subject]['domains'][] = [
                    'domain' => $domain,
                    'percentage' => $subjectTotal > 0 ? 100.0 * $domainWeight / $subjectTotal : 0.0,
                ];
            }
        }

        return ['board' => $boardBlueprint, 'subjects' => $subjectBlueprints];
    }

    private static function normalizedName(mixed $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $value)) ?? '');
    }

    private static function canonicalBoardId(string $board, array $boards): string
    {
        $requested = strtolower(trim($board));
        foreach ($boards as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = strtolower(trim((string) ($record['id'] ?? '')));
            $code = strtolower(trim((string) ($record['code'] ?? '')));
            if ($requested !== '' && ($requested === $id || $requested === $code)) {
                return $id !== '' ? $id : $requested;
            }
        }
        return $requested;
    }
}
