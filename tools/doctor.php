<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

// The static Doctor scans the full repository and its JSON collections. Keep
// the normal developer entrypoint usable on PHP installations whose CLI
// default is the small 128M limit; Repository Health has its own bounded path.
if (!in_array('--health', array_slice($argv ?? [], 1), true)) {
    @ini_set('memory_limit', '1024M');
}

$doctorArguments = array_slice($argv ?? [], 1);

if (in_array('--preflight', $doctorArguments, true)) {
    $invalidPreflightArguments = array_values(array_filter(
        $doctorArguments,
        static fn(string $argument): bool => !in_array($argument, ['--preflight', '--json'], true)
            && !(str_starts_with($argument, '--root=') && trim(substr($argument, 7)) !== '')
    ));
    if ($invalidPreflightArguments !== []) {
        fwrite(STDERR, 'Unknown Preflight option: ' . $invalidPreflightArguments[0] . PHP_EOL);
        exit(2);
    }
    $preflightRoot = dirname(__DIR__);
    foreach ($doctorArguments as $argument) {
        if (!str_starts_with($argument, '--root=')) continue;
        $requestedRoot = realpath(substr($argument, 7));
        if ($requestedRoot === false || !is_dir($requestedRoot)) {
            fwrite(STDERR, "Preflight root must be an existing application directory.\n");
            exit(2);
        }
        $preflightRoot = $requestedRoot;
    }
    $preflight = \App\Services\Operations\ProductionReadinessService::preflight($preflightRoot);
    if (in_array('--json', $doctorArguments, true)) {
        echo json_encode($preflight, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    } else {
        printf(
            "PREFLIGHT=%s FAIL=%d WARN=%d PRODUCT_VERSION=%s BUILD_COMMIT=%s\n",
            (string) ($preflight['status'] ?? 'UNKNOWN'),
            count((array) ($preflight['failures'] ?? [])),
            count((array) ($preflight['warnings'] ?? [])),
            (string) ($preflight['product_version'] ?? 'UNKNOWN'),
            (string) ($preflight['build_commit'] ?? 'UNKNOWN')
        );
    }
    exit(($preflight['status'] ?? '') === 'FAIL' ? 1 : 0);
}

if (in_array('--smoke', $doctorArguments, true)) {
    $invalidSmokeArguments = array_values(array_filter(
        $doctorArguments,
        static fn(string $argument): bool => in_array($argument, ['--smoke', '--json'], true) === false
    ));
    if ($invalidSmokeArguments !== []) {
        fwrite(STDERR, 'Unknown Smoke option: ' . $invalidSmokeArguments[0] . PHP_EOL);
        exit(2);
    }
    $smoke = \App\Services\Operations\ProductionReadinessService::smoke(dirname(__DIR__));
    if (in_array('--json', $doctorArguments, true)) {
        echo json_encode($smoke, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    } else {
        printf(
            "SMOKE=%s FAIL=%d RUNTIME_MS=%s\n",
            (string) ($smoke['status'] ?? 'UNKNOWN'),
            count((array) ($smoke['failures'] ?? [])),
            (string) ($smoke['runtime_ms'] ?? '0')
        );
    }
    exit(($smoke['status'] ?? '') === 'FAIL' ? 1 : 0);
}

if (in_array('--release', array_slice($argv ?? [], 1), true)) {
    $releaseArguments = array_slice($argv ?? [], 1);
    $invalidReleaseArguments = array_values(array_filter(
        $releaseArguments,
        static fn(string $argument): bool => !in_array($argument, ['--release', '--json', '--persist', '--no-tests'], true)
    ));
    if ($invalidReleaseArguments !== []) {
        fwrite(STDERR, 'Unknown Release Readiness option: ' . $invalidReleaseArguments[0] . PHP_EOL);
        exit(2);
    }
    putenv('APP_ENV=testing');
    putenv('APP_DEBUG=false');
    \App\Core\App::boot();
    $healthStartedAt = hrtime(true);
    $releaseReport = \App\Services\RepositoryHealth\Engine\RepositoryHealthEngine::analyze(false);
    $healthRuntimeMs = round((hrtime(true) - $healthStartedAt) / 1_000_000, 3);
    $flowEvidence = in_array('--no-tests', $releaseArguments, true)
        ? []
        : \App\Services\RepositoryHealth\Release\ReleaseReadinessService::runFlowChecks(dirname(__DIR__));
    $routeCompleteness = \App\Services\RepositoryHealth\Release\ReleaseReadinessService::routeCompleteness(dirname(__DIR__));
    $productionConfiguration = \App\Services\RepositoryHealth\Release\ReleaseReadinessService::productionConfiguration(dirname(__DIR__));
    $release = \App\Services\RepositoryHealth\Release\ReleaseReadinessService::audit(
        $releaseReport,
        $flowEvidence + ['route_completeness' => $routeCompleteness],
        [
            'status' => 'PASS',
            'default_health_runtime_ms' => $healthRuntimeMs,
            'sample_count' => 1,
        ],
        $productionConfiguration
    );
    $payload = [
        'generated_at' => date(DATE_ATOM),
        'evidence_state' => \App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService::CURRENT,
        'health' => [
            'overall_status' => $releaseReport->overallStatus,
            'summary' => $releaseReport->summary,
            'fail_entity_count' => (int) ($releaseReport->summary['fail_count'] ?? 0),
        ],
        'release_readiness' => $release,
    ];
    $payload['evidence'] = [
        'state' => \App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService::CURRENT,
        'generated_at' => $payload['generated_at'],
    ];
    if (in_array('--persist', $releaseArguments, true)) {
        $database = \App\Core\App::config('database', []);
        $storagePath = is_array($database) && is_string($database['path'] ?? null)
            ? rtrim($database['path'], DIRECTORY_SEPARATOR)
            : dirname(__DIR__) . '/storage';
        $releasePath = $storagePath . '/release-readiness.json';
        $persistedPayload = \App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService::preparePersistedSnapshot($payload, dirname(__DIR__));
        $releaseJson = json_encode($persistedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = $releasePath . '.tmp';
        file_put_contents($temporary, $releaseJson . PHP_EOL, LOCK_EX);
        rename($temporary, $releasePath);
    }
    if (in_array('--json', $releaseArguments, true)) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    } else {
        printf(
            "RELEASE_DECISION=%s GATE=%s P0=%d P1=%d P2=%d P3=%d\n",
            (string) ($release['release_decision'] ?? 'UNKNOWN'),
            (string) ($release['release_gate']['status'] ?? 'UNKNOWN'),
            (int) ($release['priority_counts']['P0'] ?? 0),
            (int) ($release['priority_counts']['P1'] ?? 0),
            (int) ($release['priority_counts']['P2'] ?? 0),
            (int) ($release['priority_counts']['P3'] ?? 0)
        );
    }
    exit(($release['release_gate']['status'] ?? '') === 'PASS' ? 0 : 1);
}

if (in_array('--health', array_slice($argv ?? [], 1), true)) {
    $healthArguments = array_slice($argv ?? [], 1);
    $readinessOptions = array_values(array_filter($healthArguments, static fn(string $argument): bool => str_starts_with($argument, '--readiness=')));
    $portfolioRequested = in_array('--portfolio', $healthArguments, true);
    $invalidHealthArguments = array_values(array_filter(
        $healthArguments,
        static fn(string $argument): bool => !in_array($argument, ['--health', '--json', '--persist', '--profile', '--portfolio'], true) && !str_starts_with($argument, '--readiness=')
    ));
    if (count($readinessOptions) > 1 || ($readinessOptions !== [] && trim(substr($readinessOptions[0], strlen('--readiness='))) === '')) {
        $invalidHealthArguments[] = $readinessOptions[1] ?? $readinessOptions[0] ?? '--readiness=';
    }
    if ($invalidHealthArguments !== []) {
        fwrite(STDERR, 'Unknown Repository Health option: ' . $invalidHealthArguments[0] . PHP_EOL);
        exit(2);
    }

    $healthCommandStartedAt = hrtime(true);
    $profileRequested = in_array('--profile', $healthArguments, true);
    putenv('APP_ENV=testing');
    putenv('APP_DEBUG=false');
    \App\Core\App::boot();
    $healthReport = \App\Services\RepositoryHealth\Engine\RepositoryHealthEngine::analyze($profileRequested);
    $readinessBoard = $readinessOptions === [] ? null : strtolower(trim(substr($readinessOptions[0], strlen('--readiness='))));
    $readinessAudit = $readinessBoard === null ? null : \App\Services\RepositoryHealth\Engine\RepositoryHealthEngine::readinessAudit($readinessBoard, $healthReport);
    $portfolioAudit = $portfolioRequested ? \App\Services\RepositoryHealth\Engine\RepositoryHealthEngine::portfolioReadinessAudit(null, $healthReport) : null;
    $materializationStartedAt = hrtime(true);
    $healthArray = $healthReport->toArray();
    $healthArray['evidence_state'] = \App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService::CURRENT;
    $healthArray['evidence'] = [
        'state' => \App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService::CURRENT,
        'generated_at' => $healthArray['generated_at'] ?? null,
    ];
    $reportMaterializationMs = max(0.0, (hrtime(true) - $materializationStartedAt) / 1_000_000);
    if (in_array('--persist', $healthArguments, true)) {
        $database = \App\Core\App::config('database', []);
        $storagePath = is_array($database) && is_string($database['path'] ?? null)
            ? rtrim($database['path'], DIRECTORY_SEPARATOR)
            : dirname(__DIR__) . '/storage';
        $healthPath = $storagePath . '/repository-health.json';
        $persistedHealth = \App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService::preparePersistedSnapshot($healthArray, dirname(__DIR__));
        $healthJson = json_encode($persistedHealth, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = $healthPath . '.tmp';
        file_put_contents($temporary, $healthJson . PHP_EOL, LOCK_EX);
        rename($temporary, $healthPath);
    }
    if (in_array('--json', $healthArguments, true)) {
        $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        if ($readinessAudit !== null || $portfolioAudit !== null) {
            $payload = [
                'health' => [
                    'overall_status' => $healthReport->overallStatus,
                    'summary' => $healthReport->summary,
                    'fail_entity_count' => (int) ($healthReport->summary['fail_count'] ?? 0),
                ],
                'evidence_state' => \App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService::CURRENT,
            ];
            if ($readinessAudit !== null) $payload['readiness_audit'] = $readinessAudit;
            if ($portfolioAudit !== null) $payload['portfolio_audit'] = $portfolioAudit;
            echo json_encode($payload, $jsonFlags), PHP_EOL;
            exit($healthReport->overallStatus === 'FAIL' ? 1 : 0);
        }
        if ($profileRequested) {
            $serializationStartedAt = hrtime(true);
            json_encode($healthArray, $jsonFlags);
            $serializationMs = max(0.0, (hrtime(true) - $serializationStartedAt) / 1_000_000);
            $profile = $healthReport->profiling ?? [];
            $profile['report_materialization_ms'] = round($reportMaterializationMs, 3);
            $profile['json_serialization_ms'] = round($serializationMs, 3);
            $profile['cli_end_to_end_runtime_ms'] = round((hrtime(true) - $healthCommandStartedAt) / 1_000_000, 3);
            $profile['peak_memory_bytes'] = memory_get_peak_usage(true);
            $profile['end_memory_bytes'] = memory_get_usage(true);
            $profile['operations'][] = [
                'operation' => 'cli.report_materialization_and_json_serialization',
                'duration_ms' => round($reportMaterializationMs + $serializationMs, 3),
                'execution_count' => 1,
                'percent_total' => 0.0,
                'start_ms' => null,
                'end_ms' => null,
                'memory_before_bytes' => null,
                'memory_after_bytes' => memory_get_usage(true),
                'peak_delta_bytes' => 0,
            ];
            usort($profile['operations'], static fn(array $a, array $b): int => ($b['duration_ms'] ?? 0) <=> ($a['duration_ms'] ?? 0));
            $healthArray['profiling'] = $profile;
        }
        echo json_encode($healthArray, $jsonFlags), PHP_EOL;
    } else {
        if ($readinessAudit !== null) {
            $board = (array) ($readinessAudit['board_summary'] ?? []);
            $readiness = (array) ($readinessAudit['readiness'] ?? []);
            $states = (array) ($readiness['readiness_counts'] ?? []);
            printf(
                "READINESS_AUDIT_BOARD=%s NATIVE=%s EFFECTIVE=%s RUNTIME=%s ACTIVE_LEAVES=%s READY=%d RWL=%d REVIEW=%d DEFER=%d\n",
                $readinessBoard,
                $board['canonical'] ?? 'UNKNOWN',
                $board['effective_declared'] ?? 'UNKNOWN',
                $board['runtime'] ?? 'UNKNOWN',
                $board['active_leaves'] ?? 'UNKNOWN',
                (int) ($states['READY'] ?? 0),
                (int) ($states['READY_WITH_LIMITS'] ?? 0),
                (int) ($states['REVIEW'] ?? 0),
                (int) ($states['DEFER'] ?? 0)
            );
            exit($healthReport->overallStatus === 'FAIL' ? 1 : 0);
        }
        if ($portfolioAudit !== null) {
            printf("PORTFOLIO_BOARDS=%d\n", count((array) ($portfolioAudit['boards'] ?? [])));
            foreach ((array) ($portfolioAudit['boards'] ?? []) as $row) {
                printf("BOARD=%s NATIVE=%s ACTIVE_LEAVES=%s READINESS_MODEL=%s\n", (string) ($row['board_id'] ?? ''), (string) ($row['native'] ?? 'UNKNOWN'), (string) ($row['active_leaves'] ?? 'UNKNOWN'), (string) ($row['readiness_model_status'] ?? 'UNKNOWN'));
            }
            exit($healthReport->overallStatus === 'FAIL' ? 1 : 0);
        }
        printf(
            "REPOSITORY_HEALTH_STATUS=%s PASS=%d WARN=%d FAIL=%d REVIEW=%d ISSUES=%d\n",
            $healthReport->overallStatus,
            $healthReport->summary['pass_count'] ?? 0,
            $healthReport->summary['warn_count'] ?? 0,
            $healthReport->summary['fail_count'] ?? 0,
            $healthReport->summary['review_count'] ?? 0,
            count($healthReport->issues)
        );
        foreach ($healthReport->checks as $check) {
            printf("CHECK=%s STATUS=%s ISSUES=%d\n", $check['check_code'], $check['status'], $check['issue_count']);
        }
        foreach (array_slice($healthReport->actionQueue, 0, 20) as $issue) {
            printf("ACTION=%s STATUS=%s ENTITY=%s MESSAGE=%s\n", $issue['code'], $issue['status'], (string) ($issue['entity_id'] ?? ''), $issue['message']);
        }
        if ($profileRequested) {
            $profile = $healthReport->profiling ?? [];
            printf("PROFILE_ENGINE_RUNTIME_MS=%.3f PEAK_MEMORY_BYTES=%d END_MEMORY_BYTES=%d\n", (float) ($profile['total_runtime_ms'] ?? 0), (int) ($profile['peak_memory_bytes'] ?? 0), (int) ($profile['end_memory_bytes'] ?? 0));
            printf("%-34s %12s %9s %10s %s\n", 'CHECK', 'TIME_MS', '% TOTAL', 'FINDINGS', 'SEVERITIES');
            foreach ((array) ($profile['check_timings'] ?? []) as $check) {
                printf("%-34s %12.3f %8.2f%% %10d %s\n", $check['check_id'], (float) $check['duration_ms'], (float) $check['percent_total'], (int) $check['finding_count'], json_encode($check['severity_counts'] ?? []));
            }
            printf("%-48s %12s %9s %10s\n", 'SHARED OPERATION', 'TIME_MS', '% TOTAL', 'CALLS');
            foreach (array_slice((array) ($profile['operations'] ?? []), 0, 15) as $operation) {
                printf("%-48s %12.3f %8.2f%% %10d\n", $operation['operation'], (float) $operation['duration_ms'], (float) ($operation['percent_total'] ?? 0), (int) $operation['execution_count']);
            }
        }
    }
    exit($healthReport->overallStatus === 'FAIL' ? 1 : 0);
}

use Tools\Doctor\Commands\BaselineCommand;
use Tools\Doctor\Engine\DoctorRunner;
use Tools\Doctor\Output\V2ConsoleWriter;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationCommand;

$arguments = array_slice($argv ?? [], 1);
$validArguments = ['--baseline', '--simulate'];
$invalidArguments = array_values(array_diff($arguments, $validArguments));

if ($invalidArguments !== []) {
    fwrite(
        STDERR,
        'Unknown Doctor option: ' . $invalidArguments[0] . PHP_EOL
        . 'Usage: php tools/doctor.php [--baseline|--simulate]' . PHP_EOL
    );
    exit(2);
}

if (in_array('--baseline', $arguments, true)) {
    if (count($arguments) !== 1) {
        fwrite(STDERR, "--baseline cannot be combined with other options.\n");
        exit(2);
    }

    (new BaselineCommand())->run();
    exit(0);
}

$simulate = in_array(
    '--simulate',
    $argv ?? [],
    true
);

if ($simulate) {
    \Tools\Doctor\Project\BoardPrep\Simulation\SimulationStorageFixture::activate(dirname(__DIR__));
}

$result = (new DoctorRunner())->run();
(new V2ConsoleWriter())->write($result);

if ($simulate) {
    $simulationExit = SimulationCommand::run();
    $overallExit = $result->failCount() > 0 ? 1 : $simulationExit;
    if ($overallExit !== 0) {
        $_ENV['BOARDPREP_KEEP_SIMULATION_FIXTURES'] = '1';
        putenv('BOARDPREP_KEEP_SIMULATION_FIXTURES=1');
    }
    exit($overallExit);
}

exit($result->failCount() > 0 ? 1 : 0);
