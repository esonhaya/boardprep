<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

/**
 * Optional, single-scan diagnostics for the canonical Repository Health path.
 * Nothing is cached between scans and the profile is not part of the health
 * report's stable/deterministic identity.
 */
final class RepositoryHealthProfiler
{
    private int $startedAt;
    private int $startMemory;
    private array $operations = [];
    private array $checks = [];
    private array $counters = [];

    public function __construct()
    {
        $this->startedAt = hrtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    public function measure(string $operation, callable $callback, array $checkCodes = []): mixed
    {
        $startedAt = hrtime(true);
        $memoryBefore = memory_get_usage(true);
        try {
            return $callback();
        } finally {
            $this->recordDuration($operation, $startedAt, $checkCodes, $memoryBefore);
        }
    }

    public function recordDuration(
        string $operation,
        int $startedAt,
        array $checkCodes = [],
        ?int $memoryBefore = null
    ): void {
        $endedAt = hrtime(true);
        $durationMs = max(0.0, ($endedAt - $startedAt) / 1_000_000);
        $startMs = max(0.0, ($startedAt - $this->startedAt) / 1_000_000);
        $endMs = max($startMs, ($endedAt - $this->startedAt) / 1_000_000);
        $memoryAfter = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $entry = $this->operations[$operation] ?? [
            'operation' => $operation,
            'duration_ms' => 0.0,
            'execution_count' => 0,
            'start_ms' => $startMs,
            'end_ms' => $endMs,
            'memory_before_bytes' => $memoryBefore ?? $memoryAfter,
            'memory_after_bytes' => $memoryAfter,
            'peak_delta_bytes' => 0,
        ];
        $entry['duration_ms'] += $durationMs;
        $entry['execution_count']++;
        $entry['start_ms'] = min((float) $entry['start_ms'], $startMs);
        $entry['end_ms'] = max((float) $entry['end_ms'], $endMs);
        $entry['memory_after_bytes'] = $memoryAfter;
        $entry['peak_delta_bytes'] = max(
            (int) $entry['peak_delta_bytes'],
            max(0, $peakMemory - ($memoryBefore ?? $memoryAfter))
        );
        $this->operations[$operation] = $entry;

        foreach ($checkCodes as $checkCode) {
            $checkCode = (string) $checkCode;
            if ($checkCode === '') {
                continue;
            }
            $this->recordCheckTime($checkCode, $durationMs, $startMs, $endMs, $memoryBefore, $memoryAfter, $peakMemory);
        }
    }

    public function recordCheckExecution(string $checkCode, int $startedAt, array $matchingIssues): void
    {
        $endedAt = hrtime(true);
        $durationMs = max(0.0, ($endedAt - $startedAt) / 1_000_000);
        $startMs = max(0.0, ($startedAt - $this->startedAt) / 1_000_000);
        $endMs = max($startMs, ($endedAt - $this->startedAt) / 1_000_000);
        $this->recordCheckTime($checkCode, $durationMs, $startMs, $endMs, null, null, memory_get_peak_usage(true));
        $entry = $this->checks[$checkCode] ?? [];
        $severities = ['PASS' => 0, 'WARN' => 0, 'FAIL' => 0, 'REVIEW' => 0];
        foreach ($matchingIssues as $issue) {
            $severity = is_object($issue) ? (string) ($issue->status ?? '') : (string) ($issue['status'] ?? '');
            if (isset($severities[$severity])) {
                $severities[$severity]++;
            }
        }
        $entry['execution_count'] = (int) ($entry['execution_count'] ?? 0) + 1;
        $entry['finding_count'] = count($matchingIssues);
        $entry['severity_counts'] = $severities;
        $this->checks[$checkCode] = $entry;
    }

    public function increment(string $counter, int $amount = 1): void
    {
        $this->counters[$counter] = (int) ($this->counters[$counter] ?? 0) + $amount;
    }

    public function report(): array
    {
        $endedAt = hrtime(true);
        $operations = array_values($this->operations);
        usort($operations, static fn(array $a, array $b): int => $b['duration_ms'] <=> $a['duration_ms']);

        $checks = [];
        foreach (RepositoryHealthCheckCatalog::definitions() as $code => $definition) {
            $check = $this->checks[$code] ?? [];
            $checks[] = [
                'check_id' => $code,
                'check_class' => $definition['owner'],
                'start_ms' => round((float) ($check['start_ms'] ?? 0), 3),
                'end_ms' => round((float) ($check['end_ms'] ?? 0), 3),
                'duration_ms' => round((float) ($check['duration_ms'] ?? 0), 3),
                'percent_total' => 0.0,
                'execution_count' => (int) ($check['execution_count'] ?? 0),
                'finding_count' => (int) ($check['finding_count'] ?? 0),
                'severity_counts' => $check['severity_counts'] ?? ['PASS' => 0, 'WARN' => 0, 'FAIL' => 0, 'REVIEW' => 0],
                'memory_before_bytes' => $check['memory_before_bytes'] ?? null,
                'memory_after_bytes' => $check['memory_after_bytes'] ?? null,
                'peak_delta_bytes' => (int) ($check['peak_delta_bytes'] ?? 0),
            ];
        }
        usort($checks, static fn(array $a, array $b): int => $b['duration_ms'] <=> $a['duration_ms']);

        $totalMs = max(0.0, ($endedAt - $this->startedAt) / 1_000_000);
        foreach ($checks as &$check) {
            $check['percent_total'] = $totalMs > 0 ? round((float) $check['duration_ms'] / $totalMs * 100, 2) : 0.0;
        }
        unset($check);

        ksort($this->counters, SORT_STRING);
        return [
            'total_runtime_ms' => round($totalMs, 3),
            'start_memory_bytes' => $this->startMemory,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'end_memory_bytes' => memory_get_usage(true),
            'check_timings' => $checks,
            'operations' => array_map(static function (array $operation) use ($totalMs): array {
                $operation['duration_ms'] = round((float) $operation['duration_ms'], 3);
                $operation['start_ms'] = round((float) $operation['start_ms'], 3);
                $operation['end_ms'] = round((float) $operation['end_ms'], 3);
                $operation['percent_total'] = $totalMs > 0 ? round((float) $operation['duration_ms'] / $totalMs * 100, 2) : 0.0;
                return $operation;
            }, $operations),
            'execution_counts' => $this->counters,
        ];
    }

    private function recordCheckTime(
        string $checkCode,
        float $durationMs,
        float $startMs,
        float $endMs,
        ?int $memoryBefore,
        ?int $memoryAfter,
        int $peakMemory
    ): void {
        $entry = $this->checks[$checkCode] ?? [
            'duration_ms' => 0.0,
            'start_ms' => $startMs,
            'end_ms' => $endMs,
            'execution_count' => 0,
            'peak_delta_bytes' => 0,
        ];
        $entry['duration_ms'] = (float) ($entry['duration_ms'] ?? 0) + $durationMs;
        $entry['start_ms'] = min((float) ($entry['start_ms'] ?? $startMs), $startMs);
        $entry['end_ms'] = max((float) ($entry['end_ms'] ?? $endMs), $endMs);
        if ($memoryBefore !== null) {
            $entry['memory_before_bytes'] ??= $memoryBefore;
            $entry['memory_after_bytes'] = $memoryAfter;
            $entry['peak_delta_bytes'] = max(
                (int) ($entry['peak_delta_bytes'] ?? 0),
                max(0, $peakMemory - $memoryBefore)
            );
        }
        $this->checks[$checkCode] = $entry;
    }
}
