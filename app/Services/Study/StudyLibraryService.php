<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Core\App;

final class StudyLibraryService
{
    public static function all(?string $exam = null): array
    {
        $materials = self::publishedMaterials(App::storage()->all('study-materials'));
        $exam = self::normalizeExam($exam);
        if ($exam !== null && trim($exam) !== '') {
            $materials = array_values(array_filter($materials, static function (array $item) use ($exam): bool {
                return in_array(strtolower(trim($exam)), array_map('strtolower', array_map('strval', $item['exams'] ?? [])), true);
            }));
        }

        return self::enrichMaterials($materials);
    }

    public static function find(string $id): ?array
    {
        foreach (self::publishedMaterials(App::storage()->all('study-materials')) as $material) {
            if ((string) ($material['id'] ?? '') === $id) {
                return self::enrichMaterials([$material])[0];
            }
        }
        return null;
    }

    public static function withSources(array $material): array
    {
        return self::enrichMaterials([$material])[0];
    }

    /** @param array<int, mixed> $materials @return array<int, array<string, mixed>> */
    private static function publishedMaterials(array $materials): array
    {
        return array_values(array_filter(
            $materials,
            static fn(mixed $item): bool => is_array($item)
                && ($item['status'] ?? 'published') === 'published'
        ));
    }

    /**
     * Load each shared lookup collection once, then preserve the existing
     * source-id and material-link order while enriching the requested rows.
     *
     * @param array<int, array<string, mixed>> $materials
     * @return array<int, array<string, mixed>>
     */
    private static function enrichMaterials(array $materials): array
    {
        if ($materials === []) {
            return [];
        }

        $sourcesById = [];
        foreach (SourceRegistryService::all() as $source) {
            $id = (string) ($source['id'] ?? '');
            if (!array_key_exists($id, $sourcesById)) {
                $sourcesById[$id] = $source;
            }
        }

        $linksByMaterial = [];
        foreach (App::storage()->all('study-question-links') as $link) {
            if (!is_array($link)) {
                continue;
            }
            $id = $link['material_id'] ?? '';
            $linksByMaterial[self::strictLookupKey($id)][] = $link;
        }

        return array_map(static function (array $material) use ($sourcesById, $linksByMaterial): array {
            $material['sources'] = array_values(array_filter(array_map(
                static fn(mixed $id): ?array => is_scalar($id)
                    ? ($sourcesById[(string) $id] ?? null)
                    : null,
                $material['source_ids'] ?? []
            )));
            $material['question_links'] = $linksByMaterial[
                self::strictLookupKey($material['id'] ?? '')
            ] ?? [];
            return $material;
        }, $materials);
    }

    private static function strictLookupKey(mixed $value): string
    {
        return get_debug_type($value) . ':' . serialize($value);
    }

    public static function questionsFor(string $materialId): array
    {
        return array_values(array_filter(
            App::storage()->all('study-question-links'),
            static fn(mixed $link): bool => is_array($link) && ($link['material_id'] ?? '') === $materialId
        ));
    }

    public static function forQuestion(string $questionId): array
    {
        $questionId = trim($questionId);
        if ($questionId === '') {
            return [];
        }

        return self::forQuestions([$questionId])[$questionId] ?? [];
    }

    public static function forQuestions(array $questionIds): array
    {
        $questionIds = array_fill_keys(array_values(array_filter(array_map(
            static fn(mixed $id): string => is_scalar($id) ? trim((string) $id) : '',
            $questionIds
        ))), true);
        if ($questionIds === []) {
            return [];
        }

        $materials = [];
        foreach (App::storage()->all('study-materials') as $material) {
            if (!is_array($material) || ($material['status'] ?? 'published') !== 'published') {
                continue;
            }
            $materialId = is_scalar($material['id'] ?? null) ? trim((string) $material['id']) : '';
            if ($materialId !== '') {
                $materials[$materialId] = (string) ($material['title'] ?? 'Source-backed study material');
            }
        }

        $references = [];
        foreach (App::storage()->all('study-question-links') as $link) {
            if (!is_array($link)) {
                continue;
            }
            $questionId = is_scalar($link['question_id'] ?? null) ? trim((string) $link['question_id']) : '';
            $materialId = is_scalar($link['material_id'] ?? null) ? trim((string) $link['material_id']) : '';
            if ($questionId === '' || !isset($questionIds[$questionId]) || !isset($materials[$materialId])) {
                continue;
            }
            $reviewGuidance = is_scalar($link['review_guidance'] ?? null)
                ? trim((string) $link['review_guidance'])
                : '';
            $referenceKey = $materialId . "\0" . $reviewGuidance;
            $references[$questionId][$referenceKey] = [
                'material_id' => $materialId,
                'title' => $materials[$materialId],
                'review_guidance' => $reviewGuidance,
            ];
        }

        foreach ($references as $questionId => $items) {
            $references[$questionId] = array_values($items);
        }

        return $references;
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
        $materials = self::publishedMaterials(App::storage()->all('study-materials'));
        $links = App::storage()->all('study-question-links');
        $invalid = [];
        foreach ($materials as $material) {
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
        foreach ($links as $link) {
            if (is_array($link) && (!isset($sourceIds[(string) ($link['source_ids'][0] ?? '')]) || !isset($questionIds[(string) ($link['question_id'] ?? '')]))) {
                $invalid[] = (string) ($link['id'] ?? '<missing>');
            }
        }
        return ['valid' => $invalid === [], 'invalid' => array_values(array_unique($invalid)), 'total' => count($materials)];
    }
}
