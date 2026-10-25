<?php

declare(strict_types=1);

namespace App\Services\Question;

use RuntimeException;

/** Read-only access to the curated academic pilot and its prepared evidence. */
final class Batch715AcademicPilotService
{
    private const COHORT_PATH = '/docs/reports/batch-715-pilot-cohort.json';
    private const READINESS_PATH = '/docs/reports/batch-715-review-readiness.json';
    private const B716_SOURCE_MATRIX_PATH = '/docs/reports/batch-716-source-evidence-matrix.json';
    private static ?array $cohort = null;
    private static ?array $readiness = null;
    private static ?array $sourceMatrix = null;

    /** @return array<string,mixed> */
    public static function cohort(): array
    {
        if (self::$cohort !== null) return self::$cohort;
        $decoded = self::read(self::COHORT_PATH, 'boardprep.batch-715-pilot-cohort.v1');
        $items = array_values(array_filter((array) ($decoded['items'] ?? []), 'is_array'));
        if (count($items) !== 20 || (int) ($decoded['selected_count'] ?? 0) !== 20) {
            throw new RuntimeException('BATCH715_PILOT_COHORT_INVALID');
        }
        $seen = [];
        foreach ($items as $item) {
            $questionId = (string) ($item['question_id'] ?? '');
            if (preg_match('/\A9[0-9]{3}\z/', $questionId) !== 1 || isset($seen[$questionId])) {
                throw new RuntimeException('BATCH715_PILOT_COHORT_INVALID');
            }
            $seen[$questionId] = true;
        }
        self::$cohort = $decoded;
        return self::$cohort;
    }

    /** @return array<string,mixed> */
    public static function readiness(): array
    {
        if (self::$readiness !== null) return self::$readiness;
        $decoded = self::read(self::READINESS_PATH, 'boardprep.batch-715-review-readiness.v1');
        $items = array_values(array_filter((array) ($decoded['items'] ?? []), 'is_array'));
        if (count($items) !== 20) throw new RuntimeException('BATCH715_PILOT_EVIDENCE_INVALID');
        self::$readiness = $decoded;
        return self::$readiness;
    }

    /** @return list<string> */
    public static function questionIds(): array
    {
        return array_values(array_map(static fn(array $item): string => (string) $item['question_id'], self::cohort()['items']));
    }

    public static function isSelected(string $questionId): bool
    {
        return isset(array_fill_keys(self::questionIds(), true)[trim($questionId)]);
    }

    /** @return array<string,mixed>|null */
    public static function find(string $questionId): ?array
    {
        $questionId = trim($questionId);
        foreach (self::cohort()['items'] as $item) {
            if ((string) ($item['question_id'] ?? '') === $questionId) return $item;
        }
        return null;
    }

    /** @return array<string,mixed>|null */
    public static function evidence(string $questionId): ?array
    {
        $questionId = trim($questionId);
        foreach (self::readiness()['items'] as $item) {
            if ((string) ($item['question_id'] ?? '') === $questionId) return $item;
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    public static function supplementalSourceRows(string $questionId): array
    {
        if (self::$sourceMatrix === null) {
            $decoded = self::read(self::B716_SOURCE_MATRIX_PATH, 'boardprep.batch-716-source-evidence-matrix.v1');
            $rows = array_values(array_filter((array) ($decoded['citation_rows'] ?? []), 'is_array'));
            if (count($rows) !== 41) throw new RuntimeException('BATCH716_SOURCE_MATRIX_INVALID');
            self::$sourceMatrix = $decoded;
        }
        $questionId = trim($questionId);
        return array_values(array_filter(
            (array) self::$sourceMatrix['citation_rows'],
            static fn(array $row): bool => (string) ($row['question_id'] ?? '') === $questionId
        ));
    }

    private static function read(string $relativePath, string $schema): array
    {
        $path = dirname(__DIR__, 3) . $relativePath;
        $contents = file_get_contents($path);
        if ($contents === false) throw new RuntimeException('BATCH715_PILOT_PACKAGE_UNAVAILABLE');
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || ($decoded['schema'] ?? '') !== $schema) {
            throw new RuntimeException('BATCH715_PILOT_PACKAGE_INVALID');
        }
        return $decoded;
    }
}
