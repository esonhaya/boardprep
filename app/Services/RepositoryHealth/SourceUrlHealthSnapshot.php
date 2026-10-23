<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Validates persisted URL-check evidence without performing network requests. */
final class SourceUrlHealthSnapshot
{
    public const SCHEMA_VERSION = 'boardprep.source-url-health.v1';
    private const MAX_AGE_DAYS = 30;
    private const ALLOWED_STATUSES = [
        'HTTP_REACHABLE', 'CONFIRMED_404', 'ACCESS_RESTRICTED',
        'REQUEST_ERROR', 'DNS_ERROR', 'HTTP_OTHER', 'REDIRECT_LIMIT',
        'UNSAFE_URL',
    ];

    /**
     * @param array<int,mixed> $snapshots
     * @param array<int,array<string,mixed>> $sources
     * @return array{status:string,summary:array<string,mixed>,findings:array<int,array<string,mixed>>}
     */
    public static function inspect(
        array $snapshots,
        array $sources,
        ?DateTimeImmutable $now = null,
        ?array $affectedQuestionIdsBySource = null
    ): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if (count($snapshots) !== 1 || !is_array($snapshots[0] ?? null)) {
            return self::invalid('UNAVAILABLE', 'SOURCE_URL_AUDIT_UNAVAILABLE', 'No single persisted source URL audit snapshot is available.');
        }

        $snapshot = $snapshots[0];
        if (($snapshot['schema_version'] ?? '') !== self::SCHEMA_VERSION
            || ($snapshot['id'] ?? '') !== 'latest'
            || !is_array($snapshot['records'] ?? null)
            || !array_is_list($snapshot['records'])
            || !is_array($snapshot['coverage'] ?? null)
            || trim((string) ($snapshot['payload_sha256'] ?? '')) === '') {
            return self::invalid('CORRUPT', 'SOURCE_URL_AUDIT_CORRUPT', 'The source URL audit snapshot is malformed or has an unsupported schema.');
        }
        try {
            $payloadDigest = self::payloadDigest($snapshot);
        } catch (Throwable) {
            return self::invalid('CORRUPT', 'SOURCE_URL_AUDIT_CORRUPT', 'The source URL audit snapshot contains data that cannot be encoded safely.');
        }
        if (!hash_equals($payloadDigest, (string) $snapshot['payload_sha256'])) {
            return self::invalid('CORRUPT', 'SOURCE_URL_AUDIT_CORRUPT', 'The source URL audit snapshot checksum does not match its contents.');
        }

        $sourceById = [];
        foreach ($sources as $source) {
            if (is_array($source) && trim((string) ($source['id'] ?? '')) !== '') {
                $sourceById[(string) $source['id']] = $source;
            }
        }
        if (!hash_equals(self::registryFingerprint($sources), (string) ($snapshot['registry_fingerprint'] ?? ''))) {
            return self::invalid('STALE', 'SOURCE_URL_AUDIT_STALE', 'The source registry changed after this URL audit snapshot was generated.');
        }
        $generated = self::parseDate((string) ($snapshot['generated_at_utc'] ?? ''));
        if ($generated === null || $generated > $now->modify('+5 minutes') || $generated < $now->modify('-' . self::MAX_AGE_DAYS . ' days')) {
            return self::invalid('STALE', 'SOURCE_URL_AUDIT_STALE', 'The source URL audit snapshot is missing a valid recent generation time.');
        }

        $records = $snapshot['records'];
        $coverage = $snapshot['coverage'];
        $recordCount = count($records);
        if ((int) ($coverage['checked_source_count'] ?? -1) !== $recordCount
            || (int) ($coverage['total_url_source_count'] ?? -1) !== self::urlSourceCount($sources)) {
            return self::invalid('CORRUPT', 'SOURCE_URL_AUDIT_CORRUPT', 'Snapshot coverage totals do not match the records or current registry.');
        }
        if (($coverage['complete'] ?? null) !== ($recordCount === self::urlSourceCount($sources))) {
            return self::invalid('CORRUPT', 'SOURCE_URL_AUDIT_CORRUPT', 'The snapshot complete/partial flag does not match its recorded coverage.');
        }

        $ids = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                return self::invalid('CORRUPT', 'SOURCE_URL_AUDIT_CORRUPT', 'The source URL audit contains a non-record entry.');
            }
            $sourceId = trim((string) ($record['source_id'] ?? ''));
            $source = $sourceById[$sourceId] ?? null;
            $status = strtoupper(trim((string) ($record['status'] ?? '')));
            $checkedAt = self::parseDate((string) ($record['checked_at_utc'] ?? ''));
            if ($sourceId === '' || isset($ids[$sourceId]) || !is_array($source)
                || !in_array($status, self::ALLOWED_STATUSES, true)
                || !is_string($record['url'] ?? null)
                || (string) ($source['url'] ?? '') !== (string) $record['url']
                || $checkedAt === null || $checkedAt > $now->modify('+5 minutes')
                || $checkedAt < $now->modify('-' . self::MAX_AGE_DAYS . ' days')) {
                return self::invalid('STALE', 'SOURCE_URL_AUDIT_STALE', 'A source URL result is missing, stale, duplicated, or no longer matches the registered URL.', [
                    'source_id' => $sourceId,
                ]);
            }
            $ids[$sourceId] = true;
        }

        $findings = [];
        if (($coverage['complete'] ?? false) !== true) {
            $findings[] = [
                'status' => 'REVIEW',
                'code' => 'SOURCE_URL_AUDIT_PARTIAL',
                'entity_id' => 'latest',
                'message' => 'The persisted URL audit is partial; unchecked source records have unknown reachability.',
                'evidence' => [
                    'entity_type' => 'repository_record',
                    'checked_source_count' => $recordCount,
                    'total_url_source_count' => (int) $coverage['total_url_source_count'],
                    'unchecked_source_count' => max(0, (int) $coverage['total_url_source_count'] - $recordCount),
                    'scope' => (string) ($coverage['scope'] ?? ''),
                ],
            ];
        }

        foreach ($records as $record) {
            $status = strtoupper((string) $record['status']);
            if ($status === 'HTTP_REACHABLE') {
                continue;
            }
            $questionIds = array_values(array_unique(array_map('strval', (array) (
                $affectedQuestionIdsBySource[$record['source_id']] ?? $record['affected_question_ids'] ?? []
            ))));
            sort($questionIds, SORT_STRING);
            if ($status === 'CONFIRMED_404') {
                $referenced = $questionIds !== [];
                $findings[] = [
                    'status' => $referenced ? 'FAIL' : 'REVIEW',
                    'code' => 'SOURCE_URL_CONFIRMED_404',
                    'entity_id' => (string) $record['source_id'],
                    'message' => $referenced
                        ? 'A registered source URL used by current question or study relationships returned confirmed HTTP 404/410 evidence.'
                        : 'An unreferenced registered source URL returned confirmed HTTP 404/410 evidence; no current question or study relationship points to this record.',
                    'evidence' => self::recordEvidence($record, $questionIds),
                ];
            } elseif ($status === 'ACCESS_RESTRICTED') {
                $findings[] = [
                    'status' => 'WARN',
                    'code' => 'SOURCE_URL_ACCESS_RESTRICTED',
                    'entity_id' => (string) $record['source_id'],
                    'message' => 'A registered source URL is access-restricted; this does not establish that the URL is dead or that its claim is supported.',
                    'evidence' => self::recordEvidence($record, $questionIds),
                ];
            } else {
                $findings[] = [
                    'status' => 'REVIEW',
                    'code' => 'SOURCE_URL_CHECK_INCONCLUSIVE',
                    'entity_id' => (string) $record['source_id'],
                    'message' => 'The source URL check was inconclusive and is not classified as a confirmed dead link.',
                    'evidence' => self::recordEvidence($record, $questionIds),
                ];
            }
        }

        return [
            'status' => ($coverage['complete'] ?? false) === true ? 'CURRENT_COMPLETE' : 'CURRENT_PARTIAL',
            'summary' => [
                'checked_source_count' => $recordCount,
                'total_url_source_count' => (int) $coverage['total_url_source_count'],
                'unchecked_source_count' => max(0, (int) $coverage['total_url_source_count'] - $recordCount),
                'complete' => ($coverage['complete'] ?? false) === true,
                'generated_at_utc' => (string) $snapshot['generated_at_utc'],
            ],
            'findings' => $findings,
        ];
    }

    /** @param array<string,mixed> $snapshot */
    public static function payloadDigest(array $snapshot): string
    {
        unset($snapshot['payload_sha256']);
        return hash('sha256', self::canonicalJson($snapshot));
    }

    /** @param array<int,array<string,mixed>> $sources */
    public static function registryFingerprint(array $sources): string
    {
        $rows = [];
        foreach ($sources as $source) {
            if (!is_array($source) || !is_string($source['url'] ?? null) || trim($source['url']) === '') {
                continue;
            }
            $rows[] = ['id' => (string) ($source['id'] ?? ''), 'url' => (string) $source['url']];
        }
        usort($rows, static fn(array $a, array $b): int => [$a['id'], $a['url']] <=> [$b['id'], $b['url']]);
        return hash('sha256', self::canonicalJson($rows));
    }

    /** Derive current source-to-question drill-downs without persisting a second question inventory. */
    public static function affectedQuestionsBySource(array $questions, array $links, array $materials): array
    {
        $idsBySource = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $questionId = trim((string) ($question['id'] ?? ''));
            if ($questionId === '') continue;
            foreach ((array) ($question['source_ids'] ?? []) as $sourceId) {
                if (is_scalar($sourceId) && trim((string) $sourceId) !== '') {
                    $idsBySource[trim((string) $sourceId)][$questionId] = true;
                }
            }
        }
        $materialSources = [];
        foreach ($materials as $material) {
            if (!is_array($material)) continue;
            $materialId = trim((string) ($material['id'] ?? ''));
            if ($materialId === '') continue;
            $materialSources[$materialId] = array_values(array_filter(
                (array) ($material['source_ids'] ?? []),
                static fn(mixed $sourceId): bool => is_scalar($sourceId) && trim((string) $sourceId) !== ''
            ));
        }
        foreach ($links as $link) {
            if (!is_array($link)) continue;
            $questionId = trim((string) ($link['question_id'] ?? ''));
            if ($questionId === '') continue;
            $sourceIds = array_merge(
                (array) ($link['source_ids'] ?? []),
                $materialSources[trim((string) ($link['material_id'] ?? ''))] ?? []
            );
            foreach ($sourceIds as $sourceId) {
                if (is_scalar($sourceId) && trim((string) $sourceId) !== '') {
                    $idsBySource[trim((string) $sourceId)][$questionId] = true;
                }
            }
        }
        $result = [];
        foreach ($idsBySource as $sourceId => $questionIds) {
            $result[$sourceId] = array_keys($questionIds);
            sort($result[$sourceId], SORT_STRING);
        }
        return $result;
    }

    /** @param array<string,mixed> $source */
    private static function recordEvidence(array $source, array $questionIds): array
    {
        return [
            'entity_type' => 'source',
            'source_id' => (string) ($source['source_id'] ?? ''),
            'url' => (string) ($source['url'] ?? ''),
            'http_status' => $source['http_status'] ?? null,
            'final_url' => $source['final_url'] ?? ($source['url'] ?? null),
            'checked_at_utc' => (string) ($source['checked_at_utc'] ?? ''),
            'evidence_ref' => (string) ($source['evidence_ref'] ?? 'storage/source-url-health.json'),
            'affected_question_count' => count($questionIds),
            'affected_question_ids' => $questionIds,
        ];
    }

    private static function urlSourceCount(array $sources): int
    {
        $count = 0;
        foreach ($sources as $source) {
            if (is_array($source) && is_string($source['url'] ?? null) && trim($source['url']) !== '') {
                $count++;
            }
        }
        return $count;
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }

    private static function canonicalJson(mixed $value): string
    {
        $normalized = self::sortKeys($value);
        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::sortKeys($item);
        }
        return $value;
    }

    private static function invalid(string $status, string $code, string $message, array $evidence = []): array
    {
        return [
            'status' => $status,
            'summary' => ['checked_source_count' => 0, 'total_url_source_count' => null, 'unchecked_source_count' => null, 'complete' => false],
            'findings' => [[
                'status' => 'REVIEW',
                'code' => $code,
                'entity_id' => 'latest',
                'message' => $message,
                'evidence' => ['entity_type' => 'repository_record'] + $evidence,
            ]],
        ];
    }
}
