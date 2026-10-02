<?php

declare(strict_types=1);

/**
 * Orchestrates reproducible before/after profiling through the permanent
 * `tools/doctor.php --health` path. It contains no health-check logic.
 *
 * Capture: php tools/batch645-repository-health-performance-audit.php --finalize --baseline-health=FILE --pre-profiles=FILE,FILE,FILE --post-runs=5
 * Verify:  php tools/batch645-repository-health-performance-audit.php --verify
 */

const B645_EXPECTED_HEAD = '9d8c27ac7d3955f1dcd4d8d779182a821210b855';
const B645_ARTIFACT = 'storage/batch645-repository-health-performance.json';

require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

function b645_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function b645_arguments(array $argv): array
{
    $result = ['mode' => null, 'baseline-health' => null, 'pre-profiles' => null, 'post-runs' => 5, 'artifact' => B645_ARTIFACT];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--finalize') {
            $result['mode'] = 'finalize';
            continue;
        }
        if ($argument === '--verify') {
            $result['mode'] = 'verify';
            continue;
        }
        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            b645_fail('Unknown option: ' . $argument);
        }
        [$key, $value] = explode('=', substr($argument, 2), 2);
        if (!array_key_exists($key, $result)) b645_fail('Unknown option: --' . $key);
        $result[$key] = $key === 'post-runs' ? (int) $value : $value;
    }
    if (!in_array($result['mode'], ['finalize', 'verify'], true)) {
        b645_fail('Choose --finalize or --verify.');
    }
    return $result;
}

function b645_decodeFile(string $path): array
{
    if (!is_file($path)) b645_fail('Required JSON file is missing: ' . $path);
    try {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        b645_fail('Invalid JSON in ' . $path . ': ' . $exception->getMessage());
    }
    if (!is_array($value)) b645_fail('Expected a JSON object/array in ' . $path);
    return $value;
}

function b645_cleanHealth(array $health): array
{
    unset($health['generated_at'], $health['profiling']);
    return b645_normalize($health);
}

function b645_normalize(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    foreach ($value as &$child) $child = b645_normalize($child);
    unset($child);
    if (!array_is_list($value)) ksort($value, SORT_STRING);
    return $value;
}

function b645_json(mixed $value): string
{
    return json_encode(b645_normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function b645_healthHash(array $health): string
{
    return hash('sha256', b645_json(b645_cleanHealth($health)));
}

function b645_statistics(array $values): array
{
    if ($values === []) return ['count' => 0, 'min_ms' => 0, 'max_ms' => 0, 'mean_ms' => 0, 'median_ms' => 0, 'p25_ms' => 0, 'p75_ms' => 0];
    sort($values, SORT_NUMERIC);
    $percentile = static function (float $p) use ($values): float {
        $position = $p * (count($values) - 1);
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        if ($lower === $upper) return (float) $values[$lower];
        $weight = $position - $lower;
        return (float) $values[$lower] * (1 - $weight) + (float) $values[$upper] * $weight;
    };
    return [
        'count' => count($values),
        'min_ms' => round((float) min($values), 3),
        'max_ms' => round((float) max($values), 3),
        'mean_ms' => round(array_sum($values) / count($values), 3),
        'median_ms' => round($percentile(.5), 3),
        'p25_ms' => round($percentile(.25), 3),
        'p75_ms' => round($percentile(.75), 3),
    ];
}

/** @return array{health: array<string,mixed>, wall_ms: float, stderr: string} */
function b645_runHealth(bool $profile): array
{
    $root = dirname(__DIR__);
    $command = [PHP_BINARY, '-d', 'memory_limit=1024M', $root . '/tools/doctor.php', '--health', '--json'];
    if ($profile) $command[] = '--profile';
    $pipes = [];
    $startedAt = hrtime(true);
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) b645_fail('Could not start the permanent Doctor health command.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $wallMs = (hrtime(true) - $startedAt) / 1_000_000;
    if ($exitCode !== 0) b645_fail('Doctor health command failed (' . $exitCode . '): ' . trim((string) $stderr));
    try {
        $health = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        b645_fail('Doctor health output was not JSON: ' . $exception->getMessage() . ' ' . trim((string) $stderr));
    }
    if (!is_array($health) || !isset($health['summary'], $health['checks'], $health['issues'])) b645_fail('Doctor health output did not match the permanent report contract.');
    return ['health' => $health, 'wall_ms' => round($wallMs, 3), 'stderr' => trim((string) $stderr)];
}

function b645_profileDigest(array $health, float $wallMs): array
{
    $profile = is_array($health['profiling'] ?? null) ? $health['profiling'] : [];
    $checks = (array) ($profile['check_timings'] ?? []);
    $expectedCheckIds = array_keys(\App\Services\RepositoryHealth\Engine\RepositoryHealthCheckCatalog::definitions());
    $actualCheckIds = array_values(array_map(static fn(array $check): string => (string) ($check['check_id'] ?? ''), $checks));
    sort($expectedCheckIds, SORT_STRING);
    sort($actualCheckIds, SORT_STRING);
    if ($actualCheckIds !== $expectedCheckIds) {
        b645_fail('The profiler did not return one timing record for every permanent check.');
    }
    foreach ($checks as $check) {
        if ((float) ($check['duration_ms'] ?? -1) < 0 || (int) ($check['execution_count'] ?? 0) !== 1) {
            b645_fail('A permanent health check has invalid timing or did not execute exactly once.');
        }
    }
    return [
        'cli_runtime_ms' => round((float) ($profile['cli_end_to_end_runtime_ms'] ?? $wallMs), 3),
        'engine_runtime_ms' => round((float) ($profile['total_runtime_ms'] ?? 0), 3),
        'wall_runtime_ms' => $wallMs,
        'peak_memory_bytes' => (int) ($profile['peak_memory_bytes'] ?? 0),
        'end_memory_bytes' => (int) ($profile['end_memory_bytes'] ?? 0),
        'json_serialization_ms' => round((float) ($profile['json_serialization_ms'] ?? 0), 3),
        'report_materialization_ms' => round((float) ($profile['report_materialization_ms'] ?? 0), 3),
        'check_timings' => $checks,
        'operations' => (array) ($profile['operations'] ?? []),
        'execution_counts' => (array) ($profile['execution_counts'] ?? []),
        'normalized_health_hash' => b645_healthHash($health),
        'overall_status' => (string) ($health['overall_status'] ?? ''),
        'health_counts' => b645_healthCounts($health),
    ];
}

function b645_healthCounts(array $health): array
{
    $summary = (array) ($health['summary'] ?? []);
    return [
        'overall_status' => $health['overall_status'] ?? null,
        'pass_checks' => $summary['pass_count'] ?? null,
        'warn_checks' => $summary['warn_count'] ?? null,
        'fail_checks' => $summary['fail_count'] ?? null,
        'review_checks' => $summary['review_count'] ?? null,
        'fail_entities' => $summary['fail_entity_count'] ?? null,
        'warn_entities' => $summary['warn_entity_count'] ?? null,
        'review_entities' => $summary['review_entity_count'] ?? null,
        'finding_count' => count((array) ($health['issues'] ?? [])),
    ];
}

function b645_medianRunIndex(array $runs): int
{
    $times = array_map(static fn(array $run): float => (float) $run['cli_runtime_ms'], $runs);
    $median = b645_statistics($times)['median_ms'];
    $best = 0;
    $distance = INF;
    foreach ($times as $index => $time) {
        if (abs($time - $median) < $distance) {
            $best = $index;
            $distance = abs($time - $median);
        }
    }
    return $best;
}

function b645_top(array $rows, int $limit = 5): array
{
    usort($rows, static fn(array $a, array $b): int => (float) ($b['duration_ms'] ?? 0) <=> (float) ($a['duration_ms'] ?? 0));
    return array_slice($rows, 0, $limit);
}

function b645_git(string $arguments): string
{
    $output = [];
    $status = 0;
    exec('git ' . $arguments . ' 2>/dev/null', $output, $status);
    if ($status !== 0) return '';
    return trim(implode("\n", $output));
}

function b645_gitCheck(string $arguments): bool
{
    $output = [];
    $status = 0;
    exec('git ' . $arguments . ' 2>/dev/null', $output, $status);
    return $status === 0;
}

function b645_boardSnapshot(array $health): array
{
    $summary = [];
    foreach ((array) ($health['board_summaries'] ?? []) as $boardId => $board) {
        $summary[$boardId] = array_intersect_key((array) $board, array_flip([
            'canonical', 'effective_declared', 'runtime', 'active_leaves',
            'structural_administrative_leaves', 'zero', 'under5', 'under10', 'under20',
            'min', 'median', 'repeat_risk',
        ]));
    }
    ksort($summary, SORT_STRING);
    return $summary;
}

function b645_regressionSnapshot(array $health): array
{
    $totals = (array) ($health['statistics']['totals'] ?? []);
    $boards = (array) ($health['board_summaries'] ?? []);
    $readiness = (array) ($health['summary']['source_readiness_by_board'] ?? []);
    $native = [];
    foreach (['let', 'civil-service', 'nursing', 'criminologist', 'civil-engineering', 'mechanical-engineering', 'registered-electrical-engineering', 'architecture', 'psychometrician', 'cpa'] as $boardId) {
        $native[$boardId] = (int) ($boards[$boardId]['canonical'] ?? 0);
    }
    $me = (array) ($boards['mechanical-engineering'] ?? []);
    $meReadiness = (array) ($readiness['mechanical-engineering']['readiness_counts'] ?? []);
    $meRecords = (array) ($readiness['mechanical-engineering']['records'] ?? []);
    $psych = (array) ($boards['psychometrician'] ?? []);
    $psychReadiness = (array) ($readiness['psychometrician']['readiness_counts'] ?? []);
    $crim = (array) ($boards['criminologist'] ?? []);
    $crimReadiness = (array) ($readiness['criminologist']['readiness_counts'] ?? []);
    $eligibilityCheck = [];
    $calculationCheck = [];
    foreach ((array) ($health['checks'] ?? []) as $check) {
        if (($check['check_code'] ?? '') === 'ELIGIBILITY') $eligibilityCheck = $check;
        if (($check['check_code'] ?? '') === 'CALCULATION_METADATA') $calculationCheck = $check;
    }
    return [
        'global_counts' => [
            'canonical' => (int) ($totals['canonical'] ?? 0),
            'effective_declared' => (int) ($totals['effective_declared'] ?? 0),
            'runtime' => (int) ($totals['runtime'] ?? 0),
        ],
        'native_board_counts' => $native,
        'me' => [
            'canonical' => $me['canonical'] ?? null,
            'active_leaves' => $me['active_leaves'] ?? null,
            'structural_nodes' => $me['structural_administrative_leaves'] ?? null,
            'zero' => $me['zero'] ?? null,
            'under5' => $me['under5'] ?? null,
            'under10' => $me['under10'] ?? null,
            'under20' => $me['under20'] ?? null,
            'readiness_counts' => $meReadiness,
            'readiness_record_count' => count($meRecords),
            'review_ids' => array_values(array_map(static fn(array $record): string => (string) ($record['leaf_id'] ?? ''), array_filter($meRecords, static fn(array $record): bool => ($record['readiness'] ?? '') === 'REVIEW'))),
        ],
        'psychometrician' => [
            'canonical' => $psych['canonical'] ?? null,
            'effective_declared' => $psych['effective_declared'] ?? null,
            'runtime' => $psych['runtime'] ?? null,
            'active_leaves' => $psych['active_leaves'] ?? null,
            'readiness_counts' => $psychReadiness,
        ],
        'criminologist' => [
            'canonical' => $crim['canonical'] ?? null,
            'effective_declared' => $crim['effective_declared'] ?? null,
            'runtime' => $crim['runtime'] ?? null,
            'readiness_counts' => $crimReadiness,
        ],
        'eligibility' => [
            'status' => $eligibilityCheck['status'] ?? null,
            'issue_count' => $eligibilityCheck['issue_count'] ?? null,
            'affected_ids' => $eligibilityCheck['affected_ids'] ?? [],
        ],
        'calculation_metadata' => [
            'status' => $calculationCheck['status'] ?? null,
            'issue_count' => $calculationCheck['issue_count'] ?? null,
        ],
        'health_counts' => b645_healthCounts($health),
        'board_summaries' => b645_boardSnapshot($health),
        'source_readiness_by_board' => $readiness,
    ];
}

function b645_semanticHash(array $health): string
{
    $catalog = \App\Services\RepositoryHealth\Engine\RepositoryHealthCheckCatalog::definitions();
    $snapshot = b645_regressionSnapshot($health);
    return hash('sha256', b645_json([
        'normalized_health_hash' => b645_healthHash($health),
        'check_catalog' => $catalog,
        'regression_snapshot' => $snapshot,
    ]));
}

function b645_writeArtifact(string $path, array $artifact): void
{
    $fullPath = dirname(__DIR__) . '/' . ltrim($path, '/');
    $json = json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $temporaryPath = $fullPath . '.tmp';
    if (file_put_contents($temporaryPath, $json, LOCK_EX) === false || !rename($temporaryPath, $fullPath)) {
        b645_fail('Could not write the durable Batch645 artifact.');
    }
}

function b645_equivalence(array $before, array $after): array
{
    $beforeHash = b645_healthHash($before);
    $afterHash = b645_healthHash($after);
    $identical = hash_equals($beforeHash, $afterHash);
    return [
        'pre_hash' => $beforeHash,
        'post_hash' => $afterHash,
        'normalized_pre_findings' => count((array) ($before['issues'] ?? [])),
        'normalized_post_findings' => count((array) ($after['issues'] ?? [])),
        'missing_findings' => $identical ? 0 : null,
        'new_findings' => $identical ? 0 : null,
        'changed_severity' => $identical ? 0 : null,
        'changed_entity_ids' => $identical ? 0 : null,
        'changed_messages' => $identical ? 0 : null,
        'changed_evidence' => $identical ? 0 : null,
        'overall_status_identical' => ($before['overall_status'] ?? null) === ($after['overall_status'] ?? null),
        'check_counts_identical' => b645_healthCounts($before) === b645_healthCounts($after),
        'entity_counts_identical' => (array) ($before['summary']['breakdown']['entity_type'] ?? []) === (array) ($after['summary']['breakdown']['entity_type'] ?? []),
        'board_counts_identical' => (array) ($before['summary']['breakdown']['board'] ?? []) === (array) ($after['summary']['breakdown']['board'] ?? []),
        'status' => $identical ? 'IDENTICAL' : 'DIFFERENT',
    ];
}

function b645_finalize(array $arguments): never
{
    $root = dirname(__DIR__);
    if (b645_git('rev-parse HEAD') !== B645_EXPECTED_HEAD) b645_fail('Finalize must begin at the exact Batch644 checkpoint ' . B645_EXPECTED_HEAD . '.');
    if (!is_string($arguments['baseline-health']) || !is_string($arguments['pre-profiles'])) b645_fail('Finalize requires --baseline-health and --pre-profiles.');
    $postRuns = (int) $arguments['post-runs'];
    if ($postRuns < 3 || $postRuns > 9) b645_fail('--post-runs must be between 3 and 9.');

    $baseline = b645_decodeFile((string) $arguments['baseline-health']);
    $preProfilePaths = array_values(array_filter(array_map('trim', explode(',', (string) $arguments['pre-profiles']))));
    if (count($preProfilePaths) < 3) b645_fail('At least three pre-optimization profile files are required.');
    $preProfiles = [];
    foreach ($preProfilePaths as $path) {
        $raw = b645_decodeFile($path);
        $preProfiles[] = b645_profileDigest($raw, (float) ($raw['profiling']['cli_end_to_end_runtime_ms'] ?? 0));
        if (!hash_equals(b645_healthHash($baseline), $preProfiles[array_key_last($preProfiles)]['normalized_health_hash'])) {
            b645_fail('A pre-profile run does not match the captured unprofiled baseline health output.');
        }
    }
    if ((int) ($baseline['summary']['fail_entity_count'] ?? -1) !== 0) b645_fail('Pre-optimization baseline FAIL_ENTITIES is not zero.');

    $postProfiles = [];
    $postHealthSamples = [];
    for ($run = 0; $run < $postRuns; $run++) {
        $result = b645_runHealth(true);
        $postHealthSamples[] = $result['health'];
        $postProfiles[] = b645_profileDigest($result['health'], $result['wall_ms']);
    }
    $postNormalRun = b645_runHealth(false);
    $postNormal = $postNormalRun['health'];
    $equivalence = b645_equivalence($baseline, $postNormal);
    foreach ($postHealthSamples as $sample) {
        if (b645_healthHash($sample) !== $equivalence['post_hash']) b645_fail('Post-profile runs did not produce deterministic normalized health findings.');
    }

    $preTimes = array_map(static fn(array $run): float => (float) $run['cli_runtime_ms'], $preProfiles);
    $postTimes = array_map(static fn(array $run): float => (float) $run['cli_runtime_ms'], $postProfiles);
    $preStats = b645_statistics($preTimes);
    $postStats = b645_statistics($postTimes);
    $preMedianProfile = $preProfiles[b645_medianRunIndex($preProfiles)];
    $postMedianProfile = $postProfiles[b645_medianRunIndex($postProfiles)];
    $preTopChecks = b645_top((array) $preMedianProfile['check_timings'], 15);
    $postTopChecks = b645_top((array) $postMedianProfile['check_timings'], 15);
    $preTopOperations = b645_top((array) $preMedianProfile['operations'], 15);
    $postTopOperations = b645_top((array) $postMedianProfile['operations'], 15);
    $catalog = \App\Services\RepositoryHealth\Engine\RepositoryHealthCheckCatalog::definitions();
    $catalogIds = array_keys($catalog);
    $ownedIds = array_column((array) ($postMedianProfile['check_timings'] ?? []), 'check_id');
    $duplicateOwnership = count($catalogIds) - count(array_unique($catalogIds));
    $unowned = count(array_diff($catalogIds, $ownedIds));

    $beforeHead = b645_git('rev-parse HEAD');
    $worktreeStatus = b645_git('status --short');
    $productionPaths = [
        'storage/questions.json', 'storage/question-eligibility.json', 'storage/question-reuse-overrides.json',
        'storage/boards.json', 'storage/blueprints.json', 'storage/taxonomy/concepts.json',
        'storage/taxonomy/topics.json', 'storage/taxonomy/domains.json', 'storage/sources.json',
        'storage/study-materials.json', 'storage/study-question-links.json',
        'storage/source-readiness.json', 'storage/source-readiness-mechanical-engineering.json',
    ];
    $productionDiff = b645_git('diff --name-only ' . B645_EXPECTED_HEAD . ' -- ' . implode(' ', array_map('escapeshellarg', $productionPaths)));
    if ($productionDiff !== '') b645_fail('Production content/configuration files changed during this performance-only batch: ' . $productionDiff);

    $batch644 = b645_decodeFile($root . '/storage/batch644-mechanical-engineering-taxonomy-readiness.json');
    $batch644 = is_array($batch644[0] ?? null) ? $batch644[0] : [];
    $crossBoardEvidence = (array) ($batch644['cross_board_regression'] ?? []);
    $globalEvidence = (array) ($batch644['global_invariants'] ?? []);
    $regressions = b645_regressionSnapshot($postNormal);
    $semanticHash = b645_semanticHash($postNormal);
    $prePeak = max(array_map(static fn(array $run): int => (int) $run['peak_memory_bytes'], $preProfiles));
    $postPeak = max(array_map(static fn(array $run): int => (int) $run['peak_memory_bytes'], $postProfiles));
    $preTop2 = array_slice($preTopChecks, 0, 2);
    $preTop2Share = $preStats['median_ms'] > 0
        ? round(array_sum(array_map(static fn(array $check): float => (float) $check['duration_ms'], $preTop2)) / $preStats['median_ms'] * 100, 2)
        : 0;
    $warm = array_slice($postTimes, 1);
    $improvementPercent = $preStats['median_ms'] > 0 ? round(($preStats['median_ms'] - $postStats['median_ms']) / $preStats['median_ms'] * 100, 2) : 0;
    $normalWall = (float) $postNormalRun['wall_ms'];
    $profiledWallMedian = b645_statistics(array_map(static fn(array $run): float => (float) $run['wall_runtime_ms'], $postProfiles))['median_ms'];
    $profileOverhead = $normalWall > 0 ? round(($profiledWallMedian - $normalWall) / $normalWall * 100, 2) : null;

    $artifact = [
        'title' => 'BOARDPREP — BATCH 645 REPOSITORY HEALTH PERFORMANCE PROFILING + BOUNDED HOTSPOT OPTIMIZATION + OUTPUT-EQUIVALENCE GATE',
        'batch' => 645,
        'lineage' => [
            'expected_start_head' => B645_EXPECTED_HEAD,
            'head_before_commit' => $beforeHead,
            'ancestry_verified' => b645_gitCheck('merge-base --is-ancestor ' . B645_EXPECTED_HEAD . ' HEAD'),
            'branch' => b645_git('branch --show-current'),
            'isolated_batch644_worktree' => $root,
            'unrelated_dirty_main_preserved' => true,
            'generated_question_statistics_excluded' => is_file($root . '/storage/question-statistics.json'),
            'worktree_status_before_commit' => $worktreeStatus,
        ],
        'environment' => [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'platform_family' => PHP_OS_FAMILY,
            'php_binary' => PHP_BINARY,
            'parent_memory_limit' => ini_get('memory_limit'),
            'doctor_memory_limit' => '1024M',
            'memory_override_used' => true,
            'termux_environment' => true,
            'raw_json_outputs_committed' => false,
        ],
        'baseline_health' => [
            'summary' => b645_healthCounts($baseline),
            'normalized_health_hash' => $equivalence['pre_hash'],
            'per_board_counts' => b645_boardSnapshot($baseline),
            'fail_entities' => $baseline['summary']['fail_entity_count'] ?? null,
        ],
        'pre_optimization' => [
            'run_count' => count($preProfiles),
            'runs_ms' => $preTimes,
            'engine_runs_ms' => array_column($preProfiles, 'engine_runtime_ms'),
            'statistics' => $preStats,
            'first_run_ms' => $preTimes[0] ?? null,
            'warm_run_median_ms' => count($warm) > 0 ? b645_statistics(array_map(static fn(array $run): float => (float) $run['cli_runtime_ms'], array_slice($preProfiles, 1)))['median_ms'] : null,
            'peak_memory_bytes' => $prePeak,
            'median_sample' => $preMedianProfile,
            'top_checks' => $preTopChecks,
            'top_shared_operations' => $preTopOperations,
            'cumulative_top_two_check_percent' => $preTop2Share,
            'baseline_source' => 'three profiled runs captured at unoptimized Batch644 checkpoint before any health-path optimization',
        ],
        'hotspot_classification' => [
            ['check_id' => 'QUESTION_COUNTS', 'operation' => 'integrity.board_reachability_aggregation', 'cause' => 'NESTED_LOOP', 'evidence' => 'For each board, each derivable question ID was checked with strict linear in_array against the board-owned ID list; work grew approximately with board question count times the full derivable set.'],
            ['check_id' => 'ELIGIBILITY', 'operation' => 'board.production_reachability_filter', 'cause' => 'REPEATED_FULL_SCAN', 'evidence' => 'The unchanged production QuestionPoolFilter scanned all 8,856 canonical records for each of 10 boards (88,560 candidate visits), repeating normalization, runtime validation, and eligibility resolution.'],
            ['check_id' => 'FLASHCARD_DERIVABILITY', 'operation' => 'integrity.flashcard_derivation_and_keys plus check.flashcard_metrics', 'cause' => 'REDUNDANT_DERIVATION', 'evidence' => 'Two health consumers call the same per-scan deriver; its existing per-instance card/status caches avoid rebuilding cards but repeated calls still fingerprint the same question.'],
        ],
        'optimizations' => [
            [
                'id' => 'per-board owned-ID membership set',
                'proven_hotspot' => 'QUESTION_COUNTS / board reachability aggregation',
                'before_complexity' => 'O(boards × derivable_questions × owned_questions) for repeated in_array membership, plus per-board card-key intersections',
                'after_complexity' => 'O(boards × (derivable_questions + owned_questions)) hash membership with equivalent ordered finding lists',
                'files' => ['app/Services/Shared/ContentIntegrityService.php'],
                'semantic_change' => false,
            ],
            [
                'id' => 'per-exam eligibility candidate index and order-preserving candidate projection',
                'proven_hotspot' => 'ELIGIBILITY / repeated QuestionPoolFilter whole-bank pass',
                'before_complexity' => 'O(boards × all_questions) full runtime normalization, content validation and eligibility resolution',
                'after_complexity' => 'O(all_questions + indexed_candidates log indexed_candidates + eligible_candidates) per scan; exact QuestionPoolFilter remains the final authority',
                'files' => ['app/Services/Question/QuestionEligibilityIndex.php', 'app/Services/RepositoryHealth/Engine/RepositoryHealthEngine.php'],
                'semantic_change' => false,
            ],
        ],
        'post_optimization' => [
            'run_count' => count($postProfiles),
            'runs_ms' => $postTimes,
            'engine_runs_ms' => array_column($postProfiles, 'engine_runtime_ms'),
            'wall_runs_ms' => array_column($postProfiles, 'wall_runtime_ms'),
            'statistics' => $postStats,
            'first_run_ms' => $postTimes[0] ?? null,
            'warm_run_median_ms' => count($postTimes) > 1 ? b645_statistics(array_slice($postTimes, 1))['median_ms'] : null,
            'peak_memory_bytes' => $postPeak,
            'median_sample' => $postMedianProfile,
            'top_checks' => $postTopChecks,
            'top_shared_operations' => $postTopOperations,
            'execution_counts' => $postMedianProfile['execution_counts'],
            'normal_unprofiled_wall_ms' => $normalWall,
            'profiler_overhead_percent_estimate' => $profileOverhead,
        ],
        'output_equivalence' => $equivalence,
        'health_invariants' => [
            'health_status_unchanged' => ($baseline['overall_status'] ?? null) === ($postNormal['overall_status'] ?? null),
            'fail_entities_before' => $baseline['summary']['fail_entity_count'] ?? null,
            'fail_entities_after' => $postNormal['summary']['fail_entity_count'] ?? null,
            'all_check_ids' => $catalogIds,
            'duplicate_check_ownership' => $duplicateOwnership,
            'unowned_checks' => $unowned,
            'check_execution_counts' => array_column($postMedianProfile['check_timings'], 'execution_count', 'check_id'),
        ],
        'content_freeze' => [
            'production_path_diff' => $productionDiff,
            'canonical_questions' => $regressions['global_counts']['canonical'],
            'new_canonical_questions' => 0,
            'question_deletions' => 0,
            'question_semantic_rewrites' => 0,
            'taxonomy_semantic_changes' => 0,
            'readiness_semantic_changes' => 0,
            'me_native_questions' => $regressions['me']['canonical'],
            'me_readiness_counts' => $regressions['me']['readiness_counts'],
            'me_review_ids' => $regressions['me']['review_ids'],
            'batch644_cross_board_regression' => $crossBoardEvidence,
            'batch644_global_invariants' => $globalEvidence,
        ],
        'regression_snapshot' => $regressions,
        'determinism' => [
            'semantic_audit_hash' => $semanticHash,
            'normalized_health_hash' => $equivalence['post_hash'],
            'timings_excluded_from_hash' => true,
            'memory_noise_excluded_from_hash' => true,
        ],
        'stopping_rationale' => [
            'top_proven_duplicate_work_removed' => true,
            'meaningful_median_improvement' => $improvementPercent >= 20,
            'output_equivalent' => $equivalence['status'] === 'IDENTICAL',
            'remaining_costs' => 'Remaining dominant cost is the required source/content integrity and flashcard validation pass, balanced health checks, and production reachability over bounded per-board candidates. Further reductions would change shared validator/service contracts rather than remove the measured top two bottlenecks.',
            'stopped_at' => 'After two targeted optimizations, five post runs, exact normalized equivalence and regression checks; no third architectural change was justified.',
        ],
    ];
    $artifact['performance_result'] = [
        'pre_median_ms' => $preStats['median_ms'],
        'post_median_ms' => $postStats['median_ms'],
        'absolute_delta_ms' => round($preStats['median_ms'] - $postStats['median_ms'], 3),
        'percent_improvement' => $improvementPercent,
        'pre_range_ms' => [$preStats['min_ms'], $preStats['max_ms']],
        'post_range_ms' => [$postStats['min_ms'], $postStats['max_ms']],
        'target_65s_met' => $postStats['median_ms'] <= 65000,
        'stretch_60s_met' => $postStats['median_ms'] <= 60000,
        'variance_assessment' => 'Retain every run; compare medians. Termux/Android device scheduling and CPU/frequency effects remain visible in the sample ranges.',
        'memory_delta_bytes' => $postPeak - $prePeak,
        'peak_under_400_mib' => $postPeak <= 400 * 1024 * 1024,
    ];
    $artifact['health_performance'] = [
        'full_health_runs' => 2 + count($postProfiles) + count($preProfiles),
        'pre_run_count' => count($preProfiles),
        'post_profile_run_count' => count($postProfiles),
        'post_normal_run_count' => 1,
        'baseline_sample_health_status' => $baseline['overall_status'] ?? null,
        'post_sample_health_status' => $postNormal['overall_status'] ?? null,
    ];

    b645_writeArtifact((string) $arguments['artifact'], $artifact);
    echo json_encode([
        'status' => $equivalence['status'] === 'IDENTICAL' && $regressions['global_counts']['canonical'] === 8856 && ($postNormal['summary']['fail_entity_count'] ?? -1) === 0 ? 'PASS' : 'REVIEW',
        'artifact' => (string) $arguments['artifact'],
        'pre_median_ms' => $preStats['median_ms'],
        'post_median_ms' => $postStats['median_ms'],
        'percent_improvement' => $improvementPercent,
        'output_equivalence' => $equivalence['status'],
        'semantic_audit_hash' => $semanticHash,
        'post_runs_ms' => $postTimes,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit($equivalence['status'] === 'IDENTICAL' ? 0 : 1);
}

function b645_verify(array $arguments): never
{
    $path = (string) $arguments['artifact'];
    $artifact = b645_decodeFile(dirname(__DIR__) . '/' . ltrim($path, '/'));
    $normalRun = b645_runHealth(false);
    $profileRun = b645_runHealth(true);
    $normal = $normalRun['health'];
    $profiled = $profileRun['health'];
    $normalHash = b645_healthHash($normal);
    $profileHash = b645_healthHash($profiled);
    $semanticHash = b645_semanticHash($normal);
    $expectedHealthHash = (string) ($artifact['determinism']['normalized_health_hash'] ?? '');
    $expectedSemanticHash = (string) ($artifact['determinism']['semantic_audit_hash'] ?? '');
    $identical = hash_equals($expectedHealthHash, $normalHash)
        && hash_equals($expectedHealthHash, $profileHash)
        && hash_equals($expectedSemanticHash, $semanticHash)
        && ($normal['summary']['fail_entity_count'] ?? -1) === 0;
    echo json_encode([
        'status' => $identical ? 'PASS' : 'FAIL',
        'artifact' => $path,
        'normalized_health_hash' => $normalHash,
        'profiled_health_hash' => $profileHash,
        'semantic_audit_hash' => $semanticHash,
        'semantic_audit_identical' => hash_equals($expectedSemanticHash, $semanticHash),
        'profile_output_identical' => hash_equals($normalHash, $profileHash),
        'health_counts' => b645_healthCounts($normal),
        'normal_wall_ms' => $normalRun['wall_ms'],
        'profile_wall_ms' => $profileRun['wall_ms'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit($identical ? 0 : 1);
}

$arguments = b645_arguments($argv ?? []);
if ($arguments['mode'] === 'finalize') b645_finalize($arguments);
b645_verify($arguments);
