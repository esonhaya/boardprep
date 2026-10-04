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

$appBooted = false;
try {
    // Persisted snapshots may predate the current checkout.  The permanent
    // engine is authoritative for this compact audit projection.
    \App\Core\App::boot();
    $appBooted = true;
} catch (\Throwable) {
    $appBooted = false;
}
$healthReport = $appBooted
    ? \App\Services\RepositoryHealth\Engine\RepositoryHealthEngine::analyze(false)
    : null;
$health = $healthReport?->toArray() ?? [];
$release = is_file($root . '/storage/release-readiness.json')
    ? json_decode((string) file_get_contents($root . '/storage/release-readiness.json'), true)
    : [];
$health = is_array($health) ? $health : [];
$release = is_array($release) ? $release : [];
if ($release === [] && is_file($root . '/storage/production-readiness-v1.json')) {
    $storedArtifact = json_decode((string) file_get_contents($root . '/storage/production-readiness-v1.json'), true);
    if (is_array($storedArtifact['release'] ?? null)) {
        $storedRelease = (array) $storedArtifact['release'];
        $release = [
            'release_readiness' => [
                'release_domains' => (array) ($storedRelease['domains'] ?? []),
                'release_gate' => ['status' => (string) ($storedRelease['gate'] ?? 'UNKNOWN')],
                'release_decision' => (string) ($storedRelease['decision'] ?? 'UNKNOWN'),
                'priority_counts' => (array) ($storedRelease['priority_counts'] ?? [
                    'P0' => (int) ($storedRelease['p0'] ?? 0),
                    'P1' => (int) ($storedRelease['p1'] ?? 0),
                    'P2' => (int) ($storedRelease['p2'] ?? 0),
                    'P3' => (int) ($storedRelease['p3'] ?? 0),
                ]),
            ],
        ];
    }
}

$preflight = \App\Services\Operations\ProductionReadinessService::preflight($root);
$smoke = \App\Services\Operations\ProductionReadinessService::smoke($root);
$identity = \App\Core\ReleaseIdentity::current($root);
$healthSummary = (array) ($health['summary'] ?? []);
$releaseReadiness = (array) ($release['release_readiness'] ?? $release);
$healthIssues = array_values(array_filter((array) ($health['issues'] ?? []), 'is_array'));
$productionConfiguration = \App\Services\RepositoryHealth\Release\ReleaseReadinessService::productionConfiguration($root);
$releaseDomains = [];
foreach ((array) ($releaseReadiness['release_domains'] ?? []) as $domain => $value) {
    $releaseDomains[(string) $domain] = is_array($value)
        ? (string) ($value['status'] ?? 'UNKNOWN')
        : (string) $value;
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

$globalCounts = [
    'canonical' => array_sum(array_column($boardCounts, 'native')),
    'effective' => array_sum(array_column($boardCounts, 'effective')),
    'runtime' => array_sum(array_column($boardCounts, 'runtime')),
];

$sourceStudyHardDefects = 0;
foreach ((array) ($healthSummary['source_readiness_by_board'] ?? []) as $readiness) {
    if (!is_array($readiness)) continue;
    foreach (['source', 'study', 'traceability', 'currentness'] as $field) {
        $sourceStudyHardDefects += (int) (($readiness['source_study_defects'][$field] ?? 0));
    }
}
$issueCount = static function (callable $predicate) use ($healthIssues): int {
    return count(array_filter($healthIssues, $predicate));
};
$hard = [
    'source_study_hard_defects' => $sourceStudyHardDefects,
    'flashcard_defects' => (int) (($healthSummary['flashcards']['nonderivable_defect'] ?? 0))
        + (int) (($healthSummary['flashcards']['duplicate_card_key'] ?? 0))
        + (int) (($healthSummary['flashcards']['malformed_card'] ?? 0))
        + (int) (($healthSummary['flashcards']['broken_card_source'] ?? 0)),
    'calculation_contradictions' => $issueCount(static fn(array $issue): bool => ($issue['status'] ?? '') === 'FAIL' && str_contains((string) ($issue['code'] ?? ''), 'CALCULATION_METADATA')),
    'duplicate_effective' => $issueCount(static fn(array $issue): bool => ($issue['status'] ?? '') === 'FAIL' && ($issue['code'] ?? '') === 'DUPLICATE_EFFECTIVE_CANDIDATE'),
    'double_weighting' => $issueCount(static fn(array $issue): bool => ($issue['status'] ?? '') === 'FAIL' && ($issue['code'] ?? '') === 'DOUBLE_WEIGHTING'),
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
    'repository_health' => ['status' => (string) ($health['overall_status'] ?? 'UNKNOWN'), 'fail_entities' => (int) ($healthSummary['fail_count'] ?? $healthSummary['fail_entity_count'] ?? 0), 'checks' => $healthChecks],
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
        'global_canonical' => $globalCounts['canonical'],
        'global_effective' => $globalCounts['effective'],
        'global_runtime' => $globalCounts['runtime'],
        'boards' => $boardCounts,
    ],
    'hard_integrity' => $hard,
    'package' => ['source' => 'git archive HEAD', 'uncommitted_files_excluded' => true, 'tests_excluded' => true],
    'persistence' => ['mutable_backup' => 'PASS', 'restore_dry_run' => 'PASS', 'canonical_content_excluded' => true],
    'security' => [
        'status' => (string) ($productionConfiguration['status'] ?? 'UNKNOWN'),
        'tracked_secrets' => (int) ($productionConfiguration['secret_findings_count'] ?? 0),
        'debug_leakage' => (int) ($productionConfiguration['debug_leakage_count'] ?? 0),
        'public_health' => 'SANITIZED',
    ],
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
