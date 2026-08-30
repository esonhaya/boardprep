<?php

declare(strict_types=1);

final class BlueprintResolverService
{
    public static function resolve(
        QuizSpecification $specification
    ): array {

        $subject = trim((string) $specification->subject);

        if ($subject === '') {
            return [
                'board' => [],
                'subjects' => [],
            ];
        }

        if ($specification->mode === 'exam') {
            return self::examBlueprint($specification);
        }

        $boardBlueprint = [
            'version' => 1,
            'subjects' => [
                [
                    'subject' => $subject,
                    'percentage' => 100,
                ],
            ],
        ];

        $subjectBlueprints = [
            $subject => [
                'version' => 1,
                'domains' => $specification->domain === null ? [] : [[
                    'domain' => $specification->domain,
                    'percentage' => 100,
                ]],
                'difficulty' => [],
            ],
        ];

        return [
            'board' => $boardBlueprint,
            'subjects' => $subjectBlueprints,
        ];
    }

    private static function examBlueprint(QuizSpecification $specification): array
    {
        $storage = \App\Core\App::storage();
        $board = strtolower(trim($specification->board));
        $subjectsById = [];
        foreach ($storage->all('subjects') as $record) {
            if (is_array($record) && is_scalar($record['id'] ?? null)
                && is_scalar($record['name'] ?? null)
                && strtolower((string) ($record['status'] ?? 'active')) === 'active') {
                $subjectsById[(string) $record['id']] = trim((string) $record['name']);
            }
        }

        $allocations = [];
        foreach ($storage->all('board-subjects') as $record) {
            if (!is_array($record) || strtolower(trim((string) ($record['board_id'] ?? ''))) !== $board) {
                continue;
            }
            $settings = is_array($record['settings'] ?? null) ? $record['settings'] : [];
            $subjectId = trim((string) ($record['subject_id'] ?? ''));
            $weight = is_numeric($settings['blueprint_weight'] ?? null)
                ? (float) $settings['blueprint_weight'] : 0.0;
            if ($subjectId !== '' && isset($subjectsById[$subjectId]) && $weight > 0) {
                $allocations[$subjectsById[$subjectId]] = $weight;
            }
        }

        if ($allocations === [] || abs(array_sum($allocations) - 100.0) > 0.00001) {
            return ['board' => [], 'subjects' => []];
        }

        $boardBlueprint = ['subjects' => []];
        $subjectBlueprints = [];
        foreach ($allocations as $name => $weight) {
            $boardBlueprint['subjects'][] = ['subject' => $name, 'percentage' => $weight];
            $subjectBlueprints[$name] = ['domains' => [], 'difficulty' => []];
        }

        return ['board' => $boardBlueprint, 'subjects' => $subjectBlueprints];
    }
}
