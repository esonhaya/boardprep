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
    private const B717_EVIDENCE_PATH = '/docs/reports/batch-717-evidence-verification.json';
    private static ?array $cohort = null;
    private static ?array $readiness = null;
    private static ?array $sourceMatrix = null;
    private static ?array $b717Evidence = null;

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

    /** @return array<string,mixed> */
    public static function batch717EvidencePackage(): array
    {
        if (self::$b717Evidence !== null) return self::$b717Evidence;
        $decoded = self::read(self::B717_EVIDENCE_PATH, 'boardprep.batch-717-evidence-verification.v1');
        $items = array_values(array_filter((array) ($decoded['items'] ?? []), 'is_array'));
        if (count($items) !== 20 || count((array) ($decoded['citation_rows'] ?? [])) !== 41) {
            throw new RuntimeException('BATCH717_EVIDENCE_PACKAGE_INVALID');
        }
        $expected = array_fill_keys(self::questionIds(), true);
        $seen = [];
        $citationCount = 0;
        $validMachineStates = ['NOT_EXAMINED', 'LOCATOR_CONFIRMED', 'CLAIM_CANDIDATE', 'CONFLICT_DETECTED', 'INSUFFICIENT_EVIDENCE', 'AWAITING_EXPERT_REVIEW'];
        foreach ($items as $item) {
            $questionId = (string) ($item['question_id'] ?? '');
            $version = (string) ($item['question_version_sha256'] ?? '');
            if (!isset($expected[$questionId]) || isset($seen[$questionId]) || preg_match('/\A[0-9a-f]{64}\z/', $version) !== 1
                || !in_array((string) ($item['machine_claim_status'] ?? ''), $validMachineStates, true)
                || !empty($item['machine_claim_verified']) || ($item['academic_decision_status'] ?? '') !== 'PENDING_REVIEW'
                || ($item['source_review_status'] ?? '') !== 'PENDING_QUALIFIED_REVIEWER') {
                throw new RuntimeException('BATCH717_EVIDENCE_PACKAGE_INVALID');
            }
            $seen[$questionId] = true;
            foreach ((array) ($item['citation_rows'] ?? []) as $citation) {
                if (!is_array($citation) || (string) ($citation['question_id'] ?? '') !== $questionId
                    || (string) ($citation['question_version_sha256'] ?? '') !== $version
                    || !in_array((string) ($citation['machine_claim_evidence_status'] ?? ''), $validMachineStates, true)
                    || !empty($citation['machine_claim_verified'])
                    || ($citation['independent_expert_source_review'] ?? '') !== 'PENDING_QUALIFIED_REVIEWER'
                    || ($citation['academic_approval'] ?? '') !== 'NOT_APPROVED') {
                    throw new RuntimeException('BATCH717_EVIDENCE_PACKAGE_INVALID');
                }
                $citationCount++;
            }
        }
        if (count($seen) !== 20 || $citationCount !== 41) throw new RuntimeException('BATCH717_EVIDENCE_PACKAGE_INVALID');
        self::$b717Evidence = $decoded;
        return self::$b717Evidence;
    }

    /** @return array<string,mixed>|null */
    public static function batch717EvidenceItem(string $questionId): ?array
    {
        foreach (self::batch717EvidencePackage()['items'] as $item) {
            if ((string) ($item['question_id'] ?? '') === trim($questionId)) return $item;
        }
        return null;
    }

    public static function batch717SourceSnapshotCurrent(): bool
    {
        $package = self::batch717EvidencePackage();
        $expected = (string) ($package['provenance']['storage/sources.json'] ?? '');
        $path = dirname(__DIR__, 3) . '/storage/sources.json';
        $current = is_file($path) ? hash_file('sha256', $path) : false;
        return preg_match('/\A[0-9a-f]{64}\z/', $expected) === 1 && is_string($current) && hash_equals($expected, $current);
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
