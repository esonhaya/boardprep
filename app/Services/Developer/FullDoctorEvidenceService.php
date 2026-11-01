<?php

declare(strict_types=1);

namespace App\Services\Developer;

use DateTimeImmutable;
use FilesystemIterator;
use JsonException;

/** Reads bounded, hash-checked Full Doctor evidence; never starts Doctor. */
final class FullDoctorEvidenceService
{
    public const MAX_ARTIFACT_BYTES = 8_388_608;
    public const MAX_DIRECTORY_ENTRIES = 1000;
    public const MAX_AGE_SECONDS = 86_400;

    /** @return array<string,mixed> */
    public function latest(string $repositoryRoot, ?DateTimeImmutable $now = null): array
    {
        $root = realpath($repositoryRoot);
        if (!is_string($root) || !is_dir($root)) return self::unavailable('repository_root_unavailable');
        $reports = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'reports';
        if (is_link($reports) || !is_dir($reports) || !is_readable($reports)) return self::unavailable('reports_directory_unavailable');
        $realReports = realpath($reports);
        if (!is_string($realReports) || !self::inside($realReports, $root)) return self::unavailable('reports_directory_unsafe');

        $candidates = [];
        $entries = 0;
        try {
            foreach (new FilesystemIterator($realReports, FilesystemIterator::SKIP_DOTS) as $entry) {
                if (++$entries > self::MAX_DIRECTORY_ENTRIES) return self::unavailable('reports_directory_entry_limit_exceeded');
                $name = $entry->getFilename();
                if (preg_match('/\Abatch-\d+-full-doctor-(\d{8}T\d{6}Z)\.json\z/', $name, $match) !== 1
                    || $entry->isLink() || !$entry->isFile()) continue;
                $candidates[] = [$match[1], $name];
            }
        } catch (\Throwable) {
            return self::unavailable('reports_directory_read_failed');
        }
        if ($candidates === []) return self::unavailable('full_doctor_artifact_missing');
        usort($candidates, static fn(array $a, array $b): int => strcmp($b[0], $a[0]));
        $name = $candidates[0][1];
        $evidence = $this->read($root, 'docs/reports/' . $name, 'docs/reports/' . substr($name, 0, -5) . '.meta.txt', self::gitHead($root), $now);
        $worktreeClean = self::applicationWorktreeClean($root);
        if (($evidence['state'] ?? '') === 'CURRENT_PASS' && $worktreeClean !== true) {
            $evidence['state'] = 'UNVERIFIED';
            $evidence['reason'] = $worktreeClean === false
                ? 'application_worktree_dirty'
                : 'application_worktree_status_unavailable';
            if (is_array($evidence['functional_tests'] ?? null)) $evidence['functional_tests']['status'] = 'UNVERIFIED';
            foreach (['integration_tests', 'security_tests'] as $domain) {
                if (is_array($evidence[$domain] ?? null)) {
                    $evidence[$domain]['status'] = 'STALE_RECORDED_' . (string) ($evidence[$domain]['status'] ?? 'UNKNOWN');
                }
            }
            if (is_string($evidence['browser_acceptance'] ?? null)) {
                $evidence['browser_acceptance'] = 'STALE_RECORDED_' . $evidence['browser_acceptance'];
            }
        }
        return $evidence;
    }

    /** @return array<string,mixed> */
    public function read(
        string $repositoryRoot,
        string $artifactRelativePath,
        string $metadataRelativePath,
        ?string $currentHead = null,
        ?DateTimeImmutable $now = null
    ): array {
        $root = realpath($repositoryRoot);
        if (!is_string($root) || !is_dir($root)) return self::unavailable('repository_root_unavailable');
        $artifactName = self::safeReportName($artifactRelativePath);
        if ($artifactName === null) return self::unavailable('artifact_path_invalid');
        $metadataName = self::safeMetadataName($metadataRelativePath, $artifactName);
        if ($metadataName === null) return self::unavailable('artifact_path_invalid');
        $reports = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'reports';
        if (is_link($reports) || !is_dir($reports)) return self::unavailable('reports_directory_unavailable');
        $artifactPath = $reports . DIRECTORY_SEPARATOR . $artifactName;
        $metadataPath = $reports . DIRECTORY_SEPARATOR . $metadataName;
        if (is_link($artifactPath) || !is_file($artifactPath)) {
            return self::unavailable('artifact_missing_or_unsafe', $artifactRelativePath);
        }
        $metadataPresent = !is_link($metadataPath) && is_file($metadataPath);
        $metadataUnsafe = is_link($metadataPath);
        $resolvedArtifact = realpath($artifactPath);
        $resolvedMetadata = $metadataPresent ? realpath($metadataPath) : false;
        $resolvedReports = realpath($reports);
        if (!is_string($resolvedArtifact) || !is_string($resolvedReports)
            || !self::inside($resolvedReports, $root)
            || !self::inside($resolvedArtifact, $resolvedReports)
            || ($metadataPresent && (!is_string($resolvedMetadata) || !self::inside($resolvedMetadata, $resolvedReports)))) {
            return self::unavailable('artifact_path_unsafe', $artifactRelativePath);
        }
        $artifactSize = filesize($resolvedArtifact);
        $metadataSize = $metadataPresent ? filesize((string) $resolvedMetadata) : false;
        if (!is_int($artifactSize) || $artifactSize < 2 || $artifactSize > self::MAX_ARTIFACT_BYTES) {
            return self::unavailable('artifact_size_invalid', $artifactRelativePath);
        }
        $metadataSizeValid = is_int($metadataSize) && $metadataSize >= 1 && $metadataSize <= 8192;
        $json = file_get_contents($resolvedArtifact, false, null, 0, self::MAX_ARTIFACT_BYTES + 1);
        $metadataText = $metadataPresent && $metadataSizeValid
            ? file_get_contents((string) $resolvedMetadata, false, null, 0, 8193)
            : '';
        if (!is_string($json) || !is_string($metadataText)) return self::unavailable('artifact_read_failed', $artifactRelativePath);
        if (strlen($json) > self::MAX_ARTIFACT_BYTES || strlen($metadataText) > 8192) {
            return self::unavailable('artifact_size_invalid', $artifactRelativePath);
        }
        try {
            $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::unavailable('artifact_json_invalid', $artifactRelativePath);
        }
        if (!is_array($report) || !self::validReportSchema($report)) {
            return self::unavailable('artifact_schema_invalid', $artifactRelativePath);
        }
        $metadata = self::parseMetadata($metadataText);
        $sha256 = hash('sha256', $json);
        $startTimestamp = strtotime((string) ($metadata['START_UTC'] ?? ''));
        $endTimestamp = strtotime((string) ($metadata['END_UTC'] ?? ''));
        $metadataClockValid = is_int($startTimestamp) && is_int($endTimestamp)
            && $endTimestamp >= $startTimestamp
            && ($generatedTimestamp = strtotime((string) ($report['generated_at'] ?? ''))) !== false
            && $generatedTimestamp >= $startTimestamp - 60
            && $generatedTimestamp <= $endTimestamp + 60;
        $metadataValid = !$metadataUnsafe
            && $metadataPresent
            && $metadataSizeValid
            && ($metadata['JSON'] ?? null) === $artifactName
            && ($metadata['ARTIFACT_SHA256'] ?? null) === $sha256
            && preg_match('/\A[0-9a-f]{40}\z/', (string) ($metadata['TESTED_SHA'] ?? '')) === 1
            && preg_match('/\A\d+\z/', (string) ($metadata['EXIT_CODE'] ?? '')) === 1
            && self::validTimestamp($metadata['START_UTC'] ?? null)
            && self::validTimestamp($metadata['END_UTC'] ?? null)
            && $metadataClockValid
            && is_string($metadata['COMMAND'] ?? null)
            && preg_match('/(?:^|\s)tools\/doctor\.php\s+--release\s+--json(?:\s|$)/', $metadata['COMMAND']) === 1;
        // Keep a valid report's recorded summary visible when its legacy or
        // incomplete sidecar lacks provenance, but never call it current PASS.
        if (($metadata['JSON'] ?? null) !== $artifactName || ($metadata['ARTIFACT_SHA256'] ?? null) !== $sha256
            || preg_match('/\A[0-9a-f]{40}\z/', (string) ($metadata['TESTED_SHA'] ?? '')) !== 1
            || preg_match('/\A\d+\z/', (string) ($metadata['EXIT_CODE'] ?? '')) !== 1
            || !self::validTimestamp($metadata['START_UTC'] ?? null)
            || !self::validTimestamp($metadata['END_UTC'] ?? null)
            || !$metadataClockValid
            || !is_string($metadata['COMMAND'] ?? null)
            || preg_match('/(?:^|\s)tools\/doctor\.php\s+--release\s+--json(?:\s|$)/', (string) ($metadata['COMMAND'] ?? '')) !== 1) {
            $metadataValid = false;
        }

        $generatedAt = (string) $report['generated_at'];
        $generatedTimestamp = strtotime($generatedAt);
        $currentTime = ($now ?? new DateTimeImmutable('now'))->getTimestamp();
        $exitCode = $metadataValid ? (int) $metadata['EXIT_CODE'] : null;
        $gate = (string) $report['release_readiness']['release_gate']['status'];
        $testedSha = $metadataValid ? (string) $metadata['TESTED_SHA'] : '';
        $state = 'CURRENT_PASS';
        $reason = 'artifact_hash_schema_exit_commit_and_freshness_verified';
        if (!$metadataValid) {
            $state = 'UNVERIFIED';
            $reason = 'artifact_metadata_missing_invalid_or_mismatched';
        } elseif ($exitCode !== 0 || $gate !== 'PASS') {
            $state = 'FAIL';
            $reason = 'doctor_exit_or_release_gate_failed';
        } elseif (!is_string($currentHead) || preg_match('/\A[0-9a-f]{40}\z/', $currentHead) !== 1) {
            $state = 'UNVERIFIED';
            $reason = 'current_commit_unavailable';
        } elseif (!hash_equals($testedSha, $currentHead)) {
            $state = 'STALE';
            $reason = 'tested_commit_differs_from_current_head';
        } elseif ($generatedTimestamp === false || $generatedTimestamp > $currentTime + 60 || $currentTime - $generatedTimestamp > self::MAX_AGE_SECONDS) {
            $state = 'STALE';
            $reason = 'doctor_evidence_outside_freshness_window';
        }

        $readiness = $report['release_readiness'];
        $domains = $readiness['release_domains'];
        $flowDomains = ['LEARNER_CORE_FLOWS', 'ATTEMPT_SAFETY', 'RESULT_REVIEW', 'HISTORY', 'SIMULATOR_RECOVERY'];
        $flowTests = 0;
        $flowPassed = 0;
        foreach ($flowDomains as $domainName) {
            $evidence = is_array($domains[$domainName]['evidence'] ?? null) ? $domains[$domainName]['evidence'] : [];
            $summary = self::testSummary($evidence);
            $flowTests += $summary['total'];
            $flowPassed += $summary['passed'];
        }
        $security = is_array($domains['SECURITY_SANITY'] ?? null) ? $domains['SECURITY_SANITY'] : [];
        $source = is_array($domains['SOURCE_TRACEABILITY'] ?? null) ? $domains['SOURCE_TRACEABILITY'] : [];
        $sourceEvidence = is_array($source['evidence'] ?? null) ? $source['evidence'] : [];
        $studyMapping = is_array($sourceEvidence['study_mapping_inventory'] ?? null) ? $sourceEvidence['study_mapping_inventory'] : [];
        $eligibility = is_array($domains['ELIGIBILITY_INTEGRITY'] ?? null) ? $domains['ELIGIBILITY_INTEGRITY'] : [];
        $browserDomain = is_array($domains['BROWSER_ACCEPTANCE'] ?? null) ? $domains['BROWSER_ACCEPTANCE'] : null;
        $counts = $readiness['priority_counts'];
        $priorityCounts = [];
        foreach (['P0', 'P1', 'P2', 'P3'] as $priority) {
            $priorityCounts[$priority] = max(0, (int) ($counts[$priority] ?? 0));
        }

        $freshnessVerified = $state === 'CURRENT_PASS';
        $browserStatus = $browserDomain === null ? 'NOT_EXECUTED' : (string) ($browserDomain['status'] ?? 'UNVERIFIED');
        $integrationEvidence = is_array($domains['LEARNER_CORE_FLOWS']['evidence'] ?? null) ? $domains['LEARNER_CORE_FLOWS']['evidence'] : [];
        $integrationSummary = self::testSummary($integrationEvidence);
        $integrationStatus = (string) ($domains['LEARNER_CORE_FLOWS']['status'] ?? 'UNKNOWN');
        $securitySummary = self::testSummary(is_array($security['evidence'] ?? null) ? $security['evidence'] : []);
        $securityStatus = (string) ($security['status'] ?? 'UNKNOWN');
        if (!$freshnessVerified) {
            if ($browserDomain !== null) $browserStatus = 'STALE_RECORDED_' . $browserStatus;
            $integrationStatus = 'STALE_RECORDED_' . $integrationStatus;
            $securityStatus = 'STALE_RECORDED_' . $securityStatus;
        }

        return [
            'state' => $state,
            'reason' => $reason,
            'artifact' => 'docs/reports/' . $artifactName,
            'metadata' => 'docs/reports/' . $metadataName,
            'artifact_sha256' => $sha256,
            'generated_at' => $generatedAt,
            'tested_sha' => $testedSha,
            'current_sha' => $currentHead,
            'command' => $metadataValid ? $metadata['COMMAND'] : '',
            'exit_code' => $exitCode,
            'release_gate' => $gate,
            'release_decision' => (string) ($readiness['release_decision'] ?? 'UNKNOWN'),
            'health_status' => (string) ($report['health']['overall_status'] ?? 'UNKNOWN'),
            'priority_counts' => $priorityCounts,
            'functional_tests' => ['status' => $state === 'CURRENT_PASS' ? 'PASS' : $state, 'passed' => $flowPassed, 'total' => $flowTests],
            'integration_tests' => ['status' => $integrationStatus, 'passed' => $integrationSummary['passed'], 'total' => $integrationSummary['total']],
            'security_tests' => ['status' => $securityStatus, 'passed' => $securitySummary['passed'], 'total' => $securitySummary['total']],
            'browser_acceptance' => $browserStatus,
            'source_traceability' => [
                'status' => (string) ($source['status'] ?? 'UNKNOWN'),
                'review_signals' => max(0, (int) ($sourceEvidence['review_signals'] ?? 0)),
                'unreviewed_or_unknown_links' => max(0, (int) ($studyMapping['exact_links_unreviewed_or_status_unknown'] ?? 0)),
            ],
            'eligibility' => ['status' => (string) ($eligibility['status'] ?? 'UNKNOWN'), 'failures' => max(0, (int) ($eligibility['evidence']['failures'] ?? 0))],
            'start_utc' => $metadataValid ? $metadata['START_UTC'] : '',
            'end_utc' => $metadataValid ? $metadata['END_UTC'] : '',
        ];
    }

    private static function validReportSchema(array $report): bool
    {
        $readiness = $report['release_readiness'] ?? null;
        $counts = $readiness['priority_counts'] ?? null;
        $validCounts = is_array($counts);
        foreach (['P0', 'P1', 'P2', 'P3'] as $priority) {
            if (!is_array($counts) || !array_key_exists($priority, $counts)
                || !(is_int($counts[$priority]) || (is_string($counts[$priority]) && preg_match('/\A\d+\z/', $counts[$priority]) === 1))
                || (int) $counts[$priority] < 0) {
                $validCounts = false;
                break;
            }
        }
        return is_string($report['generated_at'] ?? null)
            && strtotime($report['generated_at']) !== false
            && in_array(($report['evidence_state'] ?? null), ['CURRENT_EVIDENCE', 'CACHED_EVIDENCE'], true)
            && is_array($readiness)
            && is_array($readiness['release_gate'] ?? null)
            && is_string($readiness['release_gate']['status'] ?? null)
            && $validCounts
            && is_array($readiness['release_domains'] ?? null)
            && is_array($report['health'] ?? null)
            && is_string($report['health']['overall_status'] ?? null);
    }

    private static function safeReportName(string $relative): ?string
    {
        if (preg_match('#\Adocs/reports/(batch-\d+-full-doctor-\d{8}T\d{6}Z\.json)\z#', $relative, $match) !== 1) return null;
        return $match[1];
    }

    private static function safeMetadataName(string $relative, string $artifactName): ?string
    {
        $expected = substr($artifactName, 0, -5) . '.meta.txt';
        if ($relative !== 'docs/reports/' . $expected) return null;
        return $expected;
    }

    private static function parseMetadata(string $contents): array
    {
        $metadata = [];
        foreach (explode("\n", str_replace("\r", '', $contents)) as $line) {
            $separator = strpos($line, '=');
            if ($separator === false) continue;
            $key = substr($line, 0, $separator);
            if (!preg_match('/\A[A-Z0-9_]+\z/', $key) || array_key_exists($key, $metadata)) continue;
            $metadata[$key] = substr($line, $separator + 1);
        }
        return $metadata;
    }

    private static function validTimestamp(mixed $value): bool
    {
        return is_string($value) && $value !== '' && strtotime($value) !== false;
    }

    /** @return array{passed:int,total:int} */
    private static function testSummary(array $evidence): array
    {
        $tests = $evidence['tests'] ?? null;
        if (is_array($tests) && array_is_list($tests)) {
            $total = count($tests);
            $hasStatuses = false;
            $passed = 0;
            foreach ($tests as $test) {
                if (!is_array($test) || !is_string($test['status'] ?? null)) continue;
                $hasStatuses = true;
                if (strtoupper($test['status']) === 'PASS') $passed++;
            }
            if (!$hasStatuses) $passed = ($evidence['passed'] ?? false) === true ? $total : 0;
            return ['passed' => $passed, 'total' => $total];
        }
        $total = is_int($tests) || (is_string($tests) && preg_match('/\A\d+\z/', $tests) === 1)
            ? max(0, (int) $tests)
            : 0;
        $passedValue = $evidence['passed'] ?? null;
        $passed = is_int($passedValue) || (is_string($passedValue) && preg_match('/\A\d+\z/', $passedValue) === 1)
            ? max(0, min($total, (int) $passedValue))
            : (($passedValue === true) ? $total : 0);
        return ['passed' => $passed, 'total' => $total];
    }

    private static function inside(string $path, string $directory): bool
    {
        $prefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $prefix);
    }

    private static function gitHead(string $root): ?string
    {
        $process = @proc_open(['git', '-C', $root, 'rev-parse', 'HEAD'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) return null;
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $head = is_string($output) ? trim($output) : '';
        return $status === 0 && preg_match('/\A[0-9a-f]{40}\z/', $head) === 1 ? $head : null;
    }

    private static function applicationWorktreeClean(string $root): ?bool
    {
        $process = @proc_open([
            'git', '-C', $root, 'status', '--porcelain=v1', '--untracked-files=normal', '--', '.',
            ':(exclude)docs/reports/**', ':(exclude)storage/**',
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) return null;
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || !is_string($output)) return null;
        return trim($output) === '';
    }

    private static function unavailable(string $reason, string $artifact = ''): array
    {
        return [
            'state' => 'UNVERIFIED',
            'reason' => $reason,
            'artifact' => $artifact,
            'artifact_sha256' => '',
            'generated_at' => '',
            'tested_sha' => '',
            'current_sha' => null,
            'exit_code' => null,
            'release_gate' => 'UNKNOWN',
            'health_status' => 'UNKNOWN',
            'priority_counts' => ['P0' => 0, 'P1' => 0, 'P2' => 0, 'P3' => 0],
            'functional_tests' => ['status' => 'UNVERIFIED', 'passed' => 0, 'total' => 0],
            'integration_tests' => ['status' => 'UNVERIFIED', 'passed' => 0, 'total' => 0],
            'security_tests' => ['status' => 'UNVERIFIED', 'passed' => 0, 'total' => 0],
            'browser_acceptance' => 'NOT_EXECUTED',
            'source_traceability' => ['status' => 'UNKNOWN', 'review_signals' => null, 'unreviewed_or_unknown_links' => null],
            'eligibility' => ['status' => 'UNKNOWN', 'failures' => null],
        ];
    }
}
