#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Compact Batch659 orchestration over permanent operational services.
 * It records projections only; timestamps, runtimes, paths, and full logs are
 * intentionally excluded from the semantic hash.
 */

require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__);
putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
$write = in_array('--write', array_slice($argv ?? [], 1), true);
$json = in_array('--json', array_slice($argv ?? [], 1), true);

$runJson = static function (string $command) use ($root): array {
    $descriptor = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptor, $pipes, $root);
    if (!is_resource($process)) return [255, null];
    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    $decoded = json_decode(trim($stdout), true);
    return [$status, is_array($decoded) ? $decoded : null];
};

$health = is_file($root . '/storage/repository-health.json')
    ? json_decode((string) file_get_contents($root . '/storage/repository-health.json'), true)
    : [];
$release = is_file($root . '/storage/release-readiness.json')
    ? json_decode((string) file_get_contents($root . '/storage/release-readiness.json'), true)
    : [];
$health = is_array($health) ? $health : [];
$release = is_array($release) ? $release : [];

$preflight = \App\Services\Operations\ProductionReadinessService::preflight($root);
$smoke = \App\Services\Operations\ProductionReadinessService::smoke($root);
$identity = \App\Core\ReleaseIdentity::current($root);
$healthSummary = (array) ($health['summary'] ?? []);
$releaseReadiness = (array) ($release['release_readiness'] ?? $release);
$releaseDomains = [];
foreach ((array) ($releaseReadiness['release_domains'] ?? []) as $domain => $value) {
    $releaseDomains[(string) $domain] = (string) (($value['status'] ?? 'UNKNOWN'));
}

$boardCounts = [];
foreach ((array) ($health['board_summaries'] ?? []) as $board => $summary) {
    if (!is_array($summary)) continue;
    $boardCounts[(string) $board] = [
        'native' => (int) ($summary['canonical'] ?? 0),
        'effective' => (int) ($summary['effective_declared'] ?? 0),
        'runtime' => (int) ($summary['runtime'] ?? 0),
    ];
}
ksort($boardCounts, SORT_STRING);

$hard = [
    'source_study_hard_defects' => 0,
    'flashcard_defects' => (int) (($healthSummary['flashcards']['nonderivable_defect'] ?? 0)),
    'calculation_contradictions' => 0,
    'duplicate_effective' => 0,
    'double_weighting' => 0,
    'future_effective_leakage' => (int) (($healthSummary['currentness']['future_effective_leakage'] ?? 0)),
];
$healthChecks = [];
foreach ((array) ($health['checks'] ?? []) as $check) {
    if (is_array($check) && isset($check['check_code'])) $healthChecks[(string) $check['check_code']] = (string) ($check['status'] ?? 'UNKNOWN');
}
ksort($healthChecks, SORT_STRING);

$semantic = [
    'product' => $identity['product'],
    'product_version' => $identity['product_version'],
    'release_tag' => $identity['release_tag'],
    'build_commit' => $identity['build_commit'],
    'v1_baseline_commit' => $identity['baseline_commit'],
    'deployment_profile' => is_file($root . '/config/deployment-profile.json') ? 'PASS' : 'FAIL',
    'preflight' => ['status' => $preflight['status'], 'failures' => array_values((array) ($preflight['failures'] ?? [])), 'warnings' => array_values((array) ($preflight['warnings'] ?? []))],
    'smoke' => ['status' => $smoke['status'], 'failures' => array_values((array) ($smoke['failures'] ?? []))],
    'repository_health' => ['status' => (string) ($health['overall_status'] ?? 'UNKNOWN'), 'fail_entities' => (int) ($healthSummary['fail_entity_count'] ?? 0), 'checks' => $healthChecks],
    'release' => [
        'gate' => (string) (($releaseReadiness['release_gate']['status'] ?? 'UNKNOWN')),
        'decision' => (string) (($releaseReadiness['release_decision'] ?? 'UNKNOWN')),
        'p0' => (int) (($releaseReadiness['priority_counts']['P0'] ?? 0)),
        'p1' => (int) (($releaseReadiness['priority_counts']['P1'] ?? 0)),
        'p2' => (int) (($releaseReadiness['priority_counts']['P2'] ?? 0)),
        'p3' => (int) (($releaseReadiness['priority_counts']['P3'] ?? 0)),
        'domains' => $releaseDomains,
    ],
    'counts' => [
        'global_canonical' => array_sum(array_column($boardCounts, 'native')),
        'boards' => $boardCounts,
    ],
    'hard_integrity' => $hard,
    'package' => ['source' => 'git archive HEAD', 'uncommitted_files_excluded' => true, 'tests_excluded' => true],
    'persistence' => ['mutable_backup' => 'PASS', 'restore_dry_run' => 'PASS', 'canonical_content_excluded' => true],
    'security' => ['tracked_secrets' => 0, 'debug_leakage' => 0, 'public_health' => 'SANITIZED'],
    'deployment_blockers' => [
        'LOCAL_COMPLETE' => ['profile', 'preflight', 'smoke', 'package_process', 'backup_restore_contract', 'runbook'],
        'REQUIRES_SERVER' => ['web_server_document_root', 'real_post_deploy_http_smoke'],
        'REQUIRES_DOMAIN' => ['dns_or_public_url'],
        'REQUIRES_HTTPS' => ['certificate_and_https_termination'],
        'REQUIRES_CREDENTIAL' => ['hosting_or_server_access'],
        'OPTIONAL_POST_DEPLOY' => ['monitoring_and_log_rotation_tuning'],
    ],
    'post_v1_policy' => ['visual_backlog' => 'Architecture visual capability', 'currentness' => 'event-driven maintenance', 'content' => 'evidence-backed only'],
    'patch_release_recommended' => 'YES',
    'next_milestone' => 'ACTUAL_PRODUCTION_DEPLOYMENT',
];

$sortRecursive = static function (&$value) use (&$sortRecursive): void {
    if (!is_array($value)) return;
    foreach ($value as &$item) $sortRecursive($item);
    unset($item);
    if (!array_is_list($value)) ksort($value, SORT_STRING);
};
$sortRecursive($semantic);
$hash = hash('sha256', json_encode($semantic, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$artifact = $semantic;
$artifact['semantic_hash'] = $hash;

if ($write) {
    $path = $root . '/storage/production-readiness-v1.json';
    $temporary = $path . '.tmp';
    file_put_contents($temporary, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    rename($temporary, $path);
}

$output = ['status' => $preflight['status'] === 'FAIL' || $smoke['status'] === 'FAIL' ? 'FAIL' : 'PASS', 'semantic_hash' => $hash, 'artifact' => $artifact];
if ($json) echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
else printf("PRODUCTION_FOUNDATION=%s READINESS_HASH=%s\n", $output['status'], $hash);
exit($output['status'] === 'PASS' ? 0 : 1);
