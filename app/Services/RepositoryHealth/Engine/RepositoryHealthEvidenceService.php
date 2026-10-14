<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use JsonException;

/**
 * Adds provenance to saved Doctor snapshots and keeps saved data out of
 * current health and release decisions.
 */
final class RepositoryHealthEvidenceService
{
    public const CURRENT = 'CURRENT_EVIDENCE';
    public const CACHED = 'CACHED_EVIDENCE';
    public const STALE = 'STALE_EVIDENCE';
    public const UNAVAILABLE = 'UNAVAILABLE_EVIDENCE';

    private const MAX_CACHE_AGE_SECONDS = 86400;

    /** @return array<string, mixed> */
    public static function currentReport(): array
    {
        $report = RepositoryHealthEngine::analyze()->toArray();
        $report['evidence_state'] = self::CURRENT;
        $report['evidence'] = [
            'state' => self::CURRENT,
            'generated_at' => $report['generated_at'] ?? null,
        ];
        return $report;
    }

    /**
     * Mark a newly generated payload as cacheable for display. Release gates
     * still use a fresh in-process report and never consume this saved copy.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function preparePersistedSnapshot(array $payload, string $repositoryRoot): array
    {
        unset($payload['evidence_state'], $payload['cached_evidence'], $payload['evidence']);
        if (!is_string($payload['generated_at'] ?? null) || strtotime($payload['generated_at']) === false) {
            $payload['generated_at'] = date(DATE_ATOM);
        }

        $provenance = self::repositoryProvenance($repositoryRoot);
        $canonicalReport = self::canonicalJson($payload);
        $payload['evidence'] = [
            'schema_version' => 1,
            'generated_at' => $payload['generated_at'],
            'repository_head' => $provenance['head'],
            'worktree_sha256' => $provenance['fingerprint'],
            'report_sha256' => hash('sha256', $canonicalReport),
        ];
        return $payload;
    }

    /** @return array{state:string,reason:string,generated_at?:string,repository_head?:string} */
    public static function classifyPersistedSnapshot(string $path, string $repositoryRoot): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return ['state' => self::UNAVAILABLE, 'reason' => 'snapshot_missing_or_unreadable'];
        }

        $contents = file_get_contents($path);
        $snapshot = $contents === false ? null : json_decode($contents, true);
        if (!is_array($snapshot)) {
            return ['state' => self::UNAVAILABLE, 'reason' => 'snapshot_malformed'];
        }

        $evidence = is_array($snapshot['evidence'] ?? null) ? $snapshot['evidence'] : null;
        if ($evidence === null) {
            return ['state' => self::STALE, 'reason' => 'snapshot_has_no_provenance'];
        }

        $generatedAt = is_string($snapshot['generated_at'] ?? null)
            ? $snapshot['generated_at']
            : '';
        $timestamp = $generatedAt === '' ? false : strtotime($generatedAt);
        if (
            ($evidence['schema_version'] ?? null) !== 1
            || $timestamp === false
            || ($evidence['generated_at'] ?? null) !== $generatedAt
        ) {
            return ['state' => self::STALE, 'reason' => 'snapshot_timestamp_or_schema_mismatch'];
        }

        if ($timestamp > time() + 60 || time() - $timestamp > self::MAX_CACHE_AGE_SECONDS) {
            return ['state' => self::STALE, 'reason' => 'snapshot_outside_freshness_window', 'generated_at' => $generatedAt];
        }

        unset($snapshot['evidence']);
        try {
            $expectedReportHash = hash('sha256', self::canonicalJson($snapshot));
        } catch (JsonException) {
            return ['state' => self::UNAVAILABLE, 'reason' => 'snapshot_not_hashable'];
        }
        if (!hash_equals((string) ($evidence['report_sha256'] ?? ''), $expectedReportHash)) {
            return ['state' => self::STALE, 'reason' => 'snapshot_hash_mismatch', 'generated_at' => $generatedAt];
        }

        $current = self::repositoryProvenance($repositoryRoot);
        if ($current['head'] === null || $current['fingerprint'] === null) {
            return ['state' => self::STALE, 'reason' => 'current_checkout_provenance_unavailable', 'generated_at' => $generatedAt];
        }
        if (
            !hash_equals((string) ($evidence['repository_head'] ?? ''), $current['head'])
            || !hash_equals((string) ($evidence['worktree_sha256'] ?? ''), $current['fingerprint'])
        ) {
            return ['state' => self::STALE, 'reason' => 'snapshot_from_different_checkout_state', 'generated_at' => $generatedAt];
        }

        return [
            'state' => self::CACHED,
            'reason' => 'snapshot_matches_current_checkout',
            'generated_at' => $generatedAt,
            'repository_head' => $current['head'],
        ];
    }

    /** @return array{head:?string,fingerprint:?string} */
    private static function repositoryProvenance(string $repositoryRoot): array
    {
        $resolvedRoot = realpath($repositoryRoot);
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            return ['head' => null, 'fingerprint' => null];
        }

        $context = hash_init('sha256');
        $head = self::appendRepositoryState($context, $resolvedRoot, '');
        if ($head === null) {
            return ['head' => null, 'fingerprint' => null];
        }

        return ['head' => $head, 'fingerprint' => hash_final($context)];
    }

    private static function appendRepositoryState(
        mixed $context,
        string $repositoryPath,
        string $relativePrefix
    ): ?string {
        $head = self::runGit($repositoryPath, ['rev-parse', 'HEAD']);
        $diff = self::runGit($repositoryPath, ['diff', '--binary', 'HEAD']);
        $untracked = self::runGit($repositoryPath, ['ls-files', '--others', '--exclude-standard', '-z']);
        $indexEntries = self::runGit($repositoryPath, ['ls-files', '--stage', '-z']);
        if ($head === null || $diff === null || $untracked === null || $indexEntries === null) {
            return null;
        }

        hash_update($context, $relativePrefix . "\0" . $head . "\0" . hash('sha256', $diff) . "\0");
        $paths = array_values(array_filter(explode("\0", $untracked), static fn(string $path): bool => $path !== ''));
        sort($paths, SORT_STRING);
        foreach ($paths as $relativePath) {
            $absolutePath = rtrim($repositoryPath, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
            hash_update($context, $relativePath . "\0");
            if (is_link($absolutePath)) {
                hash_update($context, 'link:' . (string) readlink($absolutePath) . "\0");
            } elseif (is_file($absolutePath)) {
                $fileHash = hash_file('sha256', $absolutePath);
                if ($fileHash === false) {
                    return null;
                }
                hash_update($context, $fileHash . "\0");
            } else {
                hash_update($context, 'unreadable\0');
            }
        }

        foreach (array_filter(explode("\0", $indexEntries), static fn(string $entry): bool => $entry !== '') as $entry) {
            $separator = strpos($entry, "\t");
            if ($separator === false || !str_starts_with(substr($entry, 0, $separator), '160000 ')) {
                continue;
            }
            $submoduleRelativePath = substr($entry, $separator + 1);
            $submodulePath = $repositoryPath
                . DIRECTORY_SEPARATOR
                . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $submoduleRelativePath);
            $resolvedSubmodule = realpath($submodulePath);
            $resolvedRepository = realpath($repositoryPath);
            if (
                $resolvedSubmodule === false
                || !is_dir($resolvedSubmodule)
                || $resolvedRepository === false
                || !str_starts_with($resolvedSubmodule, rtrim($resolvedRepository, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            ) {
                return null;
            }
            $nestedPrefix = $relativePrefix === ''
                ? $submoduleRelativePath
                : $relativePrefix . '/' . $submoduleRelativePath;
            if (self::appendRepositoryState($context, $resolvedSubmodule, $nestedPrefix) === null) {
                return null;
            }
        }

        return $head;
    }

    /** @param list<string> $arguments */
    private static function runGit(string $repositoryRoot, array $arguments): ?string
    {
        $command = array_merge(['git', '-C', $repositoryRoot], $arguments);
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || $output === false) {
            return null;
        }
        return trim($output, "\r\n");
    }

    /** @param array<string, mixed> $value */
    private static function canonicalJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
