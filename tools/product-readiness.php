<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$jsonOutput = in_array('--json', array_slice($argv, 1), true);
$unknownArguments = array_values(array_filter(
    array_slice($argv, 1),
    static fn(string $argument): bool => $argument !== '--json'
));
if ($unknownArguments !== []) {
    fwrite(STDERR, "Usage: php tools/product-readiness.php [--json]\n");
    exit(2);
}

$registerPath = $root . '/config/product-readiness.json';
try {
    $register = json_decode((string) file_get_contents($registerPath), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "Product readiness register is unavailable or invalid.\n");
    exit(2);
}
if (!is_array($register) || (int) ($register['schema_version'] ?? 0) !== 1) {
    fwrite(STDERR, "Product readiness register schema is unsupported.\n");
    exit(2);
}

$files = static function (string $relativeDirectory, ?array $extensions = null) use ($root): array {
    $directory = $root . '/' . $relativeDirectory;
    if (!is_dir($directory)) {
        return [];
    }

    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) {
            continue;
        }
        $extension = strtolower($file->getExtension());
        if ($extensions !== null && !in_array($extension, $extensions, true)) {
            continue;
        }
        $paths[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
    }
    sort($paths);

    return $paths;
};

$routeSource = (string) file_get_contents($root . '/routes/web.php');
$developerBoundary = strpos($routeSource, "if (App::config('environment') !== 'production')");
$routes = [];
preg_match_all(
    '~\$router->(get|post)\s*\(\s*([\'\"])([^\'\"]+)\2~s',
    $routeSource,
    $routeMatches,
    PREG_SET_ORDER | PREG_OFFSET_CAPTURE
);
foreach ($routeMatches as $match) {
    $offset = $match[0][1];
    $routes[] = [
        'method' => strtoupper($match[1][0]),
        'path' => $match[3][0],
        'access' => $developerBoundary !== false && $offset > $developerBoundary
            ? 'NON_PRODUCTION_ONLY'
            : 'PRODUCTION',
    ];
}

$featureStatuses = [
    'IMPLEMENTED_AND_VERIFIED',
    'IMPLEMENTED_UNVERIFIED',
    'PARTIAL',
    'BROKEN',
    'DISCONNECTED',
    'MISSING',
    'INTENTIONALLY_DEFERRED',
];
$boardStatuses = $featureStatuses;
$invalidFeatures = [];
$incompleteFeatures = [];
foreach ((array) ($register['feature_assessment'] ?? []) as $feature) {
    if (!is_array($feature)
        || !in_array((string) ($feature['STATUS'] ?? ''), $featureStatuses, true)
        || !isset($feature['FEATURE'])
        || !is_array($feature['EVIDENCE'] ?? null)
        || $feature['EVIDENCE'] === []
        || !is_array($feature['TEST_COVERAGE'] ?? null)
        || $feature['TEST_COVERAGE'] === []) {
        $invalidFeatures[] = is_array($feature) ? (string) ($feature['FEATURE'] ?? 'unknown') : 'malformed';
        continue;
    }
    if (($feature['SCOPE_INCLUDED'] ?? false) === true
        && ($feature['STATUS'] ?? '') !== 'IMPLEMENTED_AND_VERIFIED') {
        $incompleteFeatures[] = (string) $feature['FEATURE'];
    }
}

$incompleteBoards = [];
foreach ((array) ($register['board_assessment'] ?? []) as $board) {
    if (!is_array($board)
        || trim((string) ($board['BOARD'] ?? '')) === ''
        || !in_array((string) ($board['STATUS'] ?? ''), $boardStatuses, true)
        || trim((string) ($board['EVIDENCE'] ?? '')) === '') {
        $invalidFeatures[] = 'malformed board assessment';
        continue;
    }
    if (($board['STATUS'] ?? '') !== 'IMPLEMENTED_AND_VERIFIED') {
        $incompleteBoards[] = (string) $board['BOARD'];
    }
}

$severityCounts = ['P0' => 0, 'P1' => 0, 'P2' => 0, 'P3' => 0];
$openSeverityCounts = $severityCounts;
$openFindings = [];
foreach ((array) ($register['findings'] ?? []) as $finding) {
    $requiredFindingFields = [
        'ID', 'FEATURE', 'USER_IMPACT', 'EVIDENCE', 'REPRODUCTION', 'SEVERITY',
        'ROOT_CAUSE', 'STATUS', 'FIX_COMMIT', 'TEST_COVERAGE', 'RELEASE_BLOCKING', 'DEPENDENCIES',
    ];
    if (!is_array($finding)
        || array_diff($requiredFindingFields, array_keys($finding)) !== []
        || !array_key_exists((string) ($finding['SEVERITY'] ?? ''), $severityCounts)) {
        $invalidFeatures[] = 'malformed finding';
        continue;
    }
    $severity = (string) $finding['SEVERITY'];
    $severityCounts[$severity]++;
    if (!in_array((string) ($finding['STATUS'] ?? ''), ['FIXED', 'FIXED_WITH_LIMITATION', 'CLOSED'], true)) {
        $openSeverityCounts[$severity]++;
        $openFindings[] = (string) ($finding['ID'] ?? 'unknown');
    }
}

$blockingCapabilities = [];
foreach ((array) ($register['missing_capabilities'] ?? []) as $capability) {
    $requiredCapabilityFields = [
        'ID', 'FEATURE', 'USER_IMPACT', 'EVIDENCE', 'REPRODUCTION', 'SEVERITY',
        'ROOT_CAUSE', 'STATUS', 'FIX_COMMIT', 'TEST_COVERAGE', 'RELEASE_BLOCKING', 'DEPENDENCIES',
    ];
    if (!is_array($capability)
        || array_diff($requiredCapabilityFields, array_keys($capability)) !== []
        || !array_key_exists((string) ($capability['SEVERITY'] ?? ''), $severityCounts)) {
        $invalidFeatures[] = 'malformed capability';
        continue;
    }
    if (($capability['RELEASE_BLOCKING'] ?? false) === true
        && ($capability['STATUS'] ?? '') !== 'CLOSED') {
        $blockingCapabilities[] = (string) ($capability['ID'] ?? 'unknown');
    }
}

$missingEvidence = [];
foreach ((array) ($register['mandatory_launch_evidence'] ?? []) as $evidence) {
    if (!is_array($evidence)
        || !isset($evidence['EVIDENCE'], $evidence['STATUS'])
        || !in_array($evidence['STATUS'], ['PASS', 'FAIL', 'BLOCKED', 'NOT_VERIFIED'], true)) {
        $invalidFeatures[] = 'malformed launch evidence';
        continue;
    }
    if (($evidence['REQUIRED'] ?? false) === true && ($evidence['STATUS'] ?? '') !== 'PASS') {
        $missingEvidence[] = (string) ($evidence['EVIDENCE'] ?? 'unknown');
    }
}

$inventory = [
    'routes' => $routes,
    'controllers' => $files('app/Controllers', ['php']),
    'middleware_and_runtime_boundaries' => array_values(array_filter([
        'app/Core/SecurityHeaders.php',
        'app/Core/SessionConfiguration.php',
        'app/Core/ExceptionHandler.php',
        'app/Core/Router.php',
    ], static fn(string $path): bool => is_file($root . '/' . $path))),
    'services' => $files('app/Services', ['php']),
    'repositories' => $files('app/Repositories', ['php']),
    'storage_files' => $files('storage', ['json']),
    'views' => $files('app/Views', ['php']),
    'frontend_assets' => $files('public/assets'),
    'tests' => array_values(array_unique(array_merge(
        $files('tools/Tests', ['php']),
        $files('tests', ['php', 'js', 'cjs', 'mjs'])
    ))),
    'configuration_controls' => [
        'APP_ENV: production/development/testing; non-production enables Developer/Doctor HTTP routes',
        'APP_DEBUG: production requires false',
        'APP_TIMEZONE: validated timezone identifier',
        'APP_MEMORY_LIMIT: runtime limit override',
        'APP_STORAGE_PATH: private JSON storage root',
        'DB_DRIVER: json or sqlite in the production profile',
        'DB_SQLITE_PATH: private SQLite file path when selected',
        'APP_PUBLIC_PATH: CLI preflight document-root override; not an app feature flag',
    ],
    'standalone_public_endpoints' => [
        'public/health.php: limited liveness/readiness and release identity',
        'public/api/doctor.php: non-production only; production responds 404',
    ],
];

$productionRouteCount = count(array_filter($routes, static fn(array $route): bool => $route['access'] === 'PRODUCTION'));
$developerRouteCount = count($routes) - $productionRouteCount;
$featureComplete = $invalidFeatures === []
    && $incompleteFeatures === []
    && $incompleteBoards === []
    && $openSeverityCounts['P0'] === 0
    && $openSeverityCounts['P1'] === 0;
$launchReady = $featureComplete
    && $openFindings === []
    && $blockingCapabilities === []
    && $missingEvidence === [];
$head = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null'));

$report = [
    'report' => 'PRODUCT_READINESS',
    'assessment_id' => (string) ($register['assessment_id'] ?? ''),
    'baseline_commit' => (string) ($register['baseline_commit'] ?? ''),
    'head' => $head !== '' ? $head : 'UNAVAILABLE',
    'release' => (string) ($register['release'] ?? ''),
    'technical_release_gate' => 'SEPARATE; run the Doctor release gate independently.',
    'product_feature_complete' => $featureComplete,
    'product_launch_ready' => $launchReady,
    'production_web_route_count' => $productionRouteCount,
    'nonproduction_web_route_count' => $developerRouteCount,
    'feature_status_counts' => array_count_values(array_map(
        static fn(array $feature): string => (string) ($feature['STATUS'] ?? 'INVALID'),
        array_filter((array) ($register['feature_assessment'] ?? []), 'is_array')
    )),
    'incomplete_boards' => $incompleteBoards,
    'feature_count' => count((array) ($register['feature_assessment'] ?? [])),
    'board_count' => count((array) ($register['board_assessment'] ?? [])),
    'defects_discovered_by_severity' => $severityCounts,
    'defects_remaining_by_severity' => $openSeverityCounts,
    'incomplete_in_scope_features' => $incompleteFeatures,
    'open_findings' => $openFindings,
    'blocking_missing_capabilities' => $blockingCapabilities,
    'mandatory_evidence_not_passed' => $missingEvidence,
    'invalid_register_entries' => $invalidFeatures,
    'feature_assessment' => $register['feature_assessment'] ?? [],
    'board_assessment' => $register['board_assessment'] ?? [],
    'findings' => $register['findings'] ?? [],
    'missing_capabilities' => $register['missing_capabilities'] ?? [],
    'mandatory_launch_evidence' => $register['mandatory_launch_evidence'] ?? [],
    'inventory' => $inventory,
];

if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
} else {
    echo "BOARDPREP PRODUCT READINESS\n";
    echo 'TECHNICAL_RELEASE_READY=RUN_SEPARATELY' . PHP_EOL;
    echo 'PRODUCT_FEATURE_COMPLETE=' . ($featureComplete ? 'PASS' : 'NO') . PHP_EOL;
    echo 'PRODUCT_LAUNCH_READY=' . ($launchReady ? 'PASS' : 'NO') . PHP_EOL;
    echo 'PRODUCTION_WEB_ROUTES=' . $productionRouteCount . PHP_EOL;
    echo 'NONPRODUCTION_WEB_ROUTES=' . $developerRouteCount . PHP_EOL;
    echo 'OPEN_P0_P1=' . ($openSeverityCounts['P0'] + $openSeverityCounts['P1']) . PHP_EOL;
    echo 'BLOCKING_CAPABILITIES=' . count($blockingCapabilities) . PHP_EOL;
    echo 'MANDATORY_EVIDENCE_NOT_PASSED=' . count($missingEvidence) . PHP_EOL;
    if ($missingEvidence !== []) {
        echo 'NEXT_EVIDENCE=' . implode(' | ', $missingEvidence) . PHP_EOL;
    }
}

exit($launchReady ? 0 : ($invalidFeatures === [] ? 1 : 2));
