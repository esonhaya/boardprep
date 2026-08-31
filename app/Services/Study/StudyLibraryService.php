<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Core\App;

final class StudyLibraryService
{
    public static function all(?string $exam = null): array
    {
        $materials = array_values(array_filter(App::storage()->all('study-materials'), 'is_array'));
        $materials = array_values(array_filter($materials, static fn(array $item): bool => ($item['status'] ?? 'published') === 'published'));
        $exam = self::normalizeExam($exam);
        if ($exam === null || trim($exam) === '') {
            return array_map([self::class, 'withSources'], $materials);
        }
        $materials = array_values(array_filter($materials, static function (array $item) use ($exam): bool {
            return in_array(strtolower(trim($exam)), array_map('strtolower', array_map('strval', $item['exams'] ?? [])), true);
        }));
        return array_map([self::class, 'withSources'], $materials);
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $material) {
            if ((string) ($material['id'] ?? '') === $id) {
                return self::withSources($material);
            }
        }
        return null;
    }

    public static function withSources(array $material): array
    {
        $material['sources'] = array_values(array_filter(array_map(
            static fn(mixed $id): ?array => is_scalar($id) ? SourceRegistryService::find((string) $id) : null,
            $material['source_ids'] ?? []
        )));
        $material['question_links'] = array_values(array_filter(
            App::storage()->all('study-question-links'),
            static fn(mixed $link): bool => is_array($link) && ($link['material_id'] ?? '') === ($material['id'] ?? '')
        ));
        return $material;
    }

    public static function questionsFor(string $materialId): array
    {
        return array_values(array_filter(
            App::storage()->all('study-question-links'),
            static fn(mixed $link): bool => is_array($link) && ($link['material_id'] ?? '') === $materialId
        ));
    }

    private static function normalizeExam(?string $exam): ?string
    {
        if ($exam === null) {
            return null;
        }
        $exam = strtolower(trim($exam));
        return ['cse' => 'civil-service', 'cle' => 'criminologist', 'nle' => 'nursing', 'ple' => 'psychometrician'][$exam] ?? $exam;
    }

    public static function validate(): array
    {
        $sourceIds = array_fill_keys(array_map(static fn(array $source): string => (string) ($source['id'] ?? ''), SourceRegistryService::all()), true);
        $invalid = [];
        foreach (self::all() as $material) {
            foreach ($material['source_ids'] ?? [] as $sourceId) {
                if (!isset($sourceIds[(string) $sourceId])) {
                    $invalid[] = (string) ($material['id'] ?? '<missing>');
                }
            }
        }
        $questionIds = array_fill_keys(array_map(
            static fn(array $question): string => (string) ($question['id'] ?? ''),
            App::storage()->all('questions')
        ), true);
        foreach (App::storage()->all('study-question-links') as $link) {
            if (is_array($link) && (!isset($sourceIds[(string) ($link['source_ids'][0] ?? '')]) || !isset($questionIds[(string) ($link['question_id'] ?? '')]))) {
                $invalid[] = (string) ($link['id'] ?? '<missing>');
            }
        }
        return ['valid' => $invalid === [], 'invalid' => array_values(array_unique($invalid)), 'total' => count(self::all())];
    }
}
