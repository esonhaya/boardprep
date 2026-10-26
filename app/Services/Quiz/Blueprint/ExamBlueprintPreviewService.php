<?php

declare(strict_types=1);

final class ExamBlueprintPreviewService
{
    public static function forSetup(string $board, string $subject, int $questionCount): array
    {
        $board = self::canonicalBoardId($board);
        $subject = trim($subject);
        if ($subject !== '' && $subject !== '*') {
            return [
                'status' => 'FOCUSED_SUBJECT',
                'status_label' => 'Focused subject session',
                'lines' => [],
                'note' => 'A focused subject session does not represent the full-board subject distribution.',
                'source_id' => '',
            ];
        }

        $specification = new QuizSpecification(
            board: $board,
            subject: '*',
            domain: null,
            topics: [],
            concepts: [],
            difficulty: 'mixed',
            questionCount: max(0, $questionCount),
            mode: 'exam',
            adaptive: false,
            shuffle: true,
            boardBlueprintVersion: null,
            subjectBlueprintVersion: null
        );
        $resolved = BlueprintResolverService::resolve($specification);
        $boardBlueprint = is_array($resolved['board'] ?? null) ? $resolved['board'] : [];
        $subjectBlueprints = is_array($resolved['subjects'] ?? null) ? $resolved['subjects'] : [];
        $configuredBlueprint = self::activeBlueprint($board);
        $weightStatus = strtoupper(trim((string) ($configuredBlueprint['official_weighting_status'] ?? '')));
        $sourceId = trim((string) ($configuredBlueprint['source_id'] ?? $boardBlueprint['source_id'] ?? ''));

        if ($boardBlueprint === [] || !is_array($boardBlueprint['subjects'] ?? null)
            || $boardBlueprint['subjects'] === []) {
            $unavailable = in_array($weightStatus, ['UNAVAILABLE', 'NOT_REGISTERED'], true)
                || ($configuredBlueprint !== null && ($configuredBlueprint['official_weighting'] ?? null) === false);
            return [
                'status' => $unavailable ? 'WEIGHTING_UNAVAILABLE' : 'CONFIGURATION_GAP',
                'status_label' => $unavailable ? 'No verified full-board weighting' : 'Blueprint mapping needs attention',
                'lines' => [],
                'note' => $unavailable
                    ? 'No official weighting is registered for this board. BoardPrep does not infer one, so a weighted full-board simulation is unavailable.'
                    : 'The registered blueprint could not be mapped to an eligible question pool. No weighted simulation was generated.',
                'source_id' => $sourceId,
            ];
        }

        $groups = is_array($boardBlueprint['allocation_groups'] ?? null)
            ? $boardBlueprint['allocation_groups'] : [];
        if ($groups !== []) {
            $official = in_array($weightStatus, ['OFFICIAL_CURRENT', 'VERIFIED_CURRENT_PROGRAM'], true)
                || ($configuredBlueprint['official_weighting'] ?? false) === true;
            $counts = RuntimeAllocationService::allocate(
                max(0, $questionCount),
                array_column($groups, 'percentage', 'id')
            );
            $sectionNames = self::sectionNames($configuredBlueprint ?? []);
            $lines = [];
            foreach ($groups as $group) {
                if (!is_array($group)) {
                    continue;
                }
                $groupId = (string) ($group['id'] ?? '');
                $groupCount = (int) ($counts[$groupId] ?? 0);
                $members = is_array($group['members'] ?? null) ? $group['members'] : [];
                $memberWeights = [];
                foreach ($members as $index => $member) {
                    if (is_array($member)) {
                        $memberWeights[(string) $index] = is_numeric($member['weight'] ?? null)
                            ? (float) $member['weight'] : 1.0;
                    }
                }
                $memberCounts = RuntimeAllocationService::allocate($groupCount, $memberWeights);
                $subareas = [];
                foreach ($memberCounts as $index => $memberCount) {
                    $member = $members[(int) $index] ?? null;
                    if (!is_array($member)) {
                        continue;
                    }
                    $selectionKey = (string) ($member['subject'] ?? '');
                    $subareas[] = [
                        'name' => $sectionNames[$selectionKey] ?? $selectionKey,
                        'weight' => is_numeric($member['weight'] ?? null) ? (float) $member['weight'] : 1.0,
                        'question_count' => $memberCount,
                    ];
                }
                $lines[] = [
                    'name' => (string) ($group['name'] ?? $groupId),
                    'percentage' => (float) ($group['percentage'] ?? 0),
                    'question_count' => $groupCount,
                    'subareas' => count($subareas) > 1 ? $subareas : [],
                ];
            }

            return [
                'status' => $official ? 'REGISTERED_OFFICIAL' : 'INTERNAL_TARGETS',
                'status_label' => $official ? 'Registered source weighting' : 'BoardPrep internal targets',
                'lines' => $lines,
                'note' => $official
                    ? 'Counts follow the active registered weighting. A combined area can split across mapped pools without implying official subarea weights. This simulation is separate from the official examination.'
                    : 'These counts use BoardPrep internal targets, not a verified official distribution. This simulation is separate from the official examination.',
                'source_id' => $sourceId,
            ];
        }

        $weights = [];
        foreach ($boardBlueprint['subjects'] as $entry) {
            if (is_array($entry) && trim((string) ($entry['subject'] ?? '')) !== '') {
                $weights[(string) $entry['subject']] = $entry['percentage'] ?? 0;
            }
        }
        $counts = RuntimeAllocationService::allocate(max(0, $questionCount), $weights);
        $lines = [];
        foreach ($counts as $name => $count) {
            $lines[] = [
                'name' => (string) $name,
                'percentage' => (float) ($weights[$name] ?? 0),
                'question_count' => (int) $count,
                'subareas' => [],
            ];
        }
        $official = in_array($weightStatus, ['OFFICIAL_CURRENT', 'VERIFIED_CURRENT_PROGRAM'], true)
            || ($configuredBlueprint['official_weighting'] ?? false) === true;

        return [
            'status' => $official ? 'REGISTERED_OFFICIAL' : 'INTERNAL_TARGETS',
            'status_label' => $official ? 'Registered source weighting' : 'BoardPrep internal targets',
            'lines' => $lines,
            'note' => $official
                ? 'Counts use the registered sourced weighting. This simulation is separate from the official examination.'
                : 'These counts use BoardPrep internal targets, not a verified official distribution. This simulation is separate from the official examination.',
            'source_id' => $sourceId,
        ];
    }

    private static function activeBlueprint(string $board): ?array
    {
        foreach (\App\Core\App::storage()->all('blueprints') as $blueprint) {
            if (is_array($blueprint)
                && strtolower(trim((string) ($blueprint['board_id'] ?? $blueprint['board'] ?? ''))) === $board
                && strtolower(trim((string) ($blueprint['scope'] ?? 'board'))) === 'board'
                && strtolower(trim((string) ($blueprint['status'] ?? 'active'))) === 'active') {
                return $blueprint;
            }
        }
        return null;
    }

    private static function canonicalBoardId(string $board): string
    {
        $requested = strtolower(trim($board));
        foreach (\App\Core\App::storage()->all('boards') as $record) {
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

    /** @return array<string,string> */
    private static function sectionNames(array $blueprint): array
    {
        $names = [];
        foreach (is_array($blueprint['sections'] ?? null) ? $blueprint['sections'] : [] as $section) {
            if (is_array($section)) {
                $id = trim((string) ($section['id'] ?? ''));
                if ($id !== '') {
                    $names[$id] = trim((string) ($section['name'] ?? $id));
                }
            }
        }
        return $names;
    }
}
