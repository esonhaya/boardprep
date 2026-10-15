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
$head = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null'));

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
$routeKeys = array_fill_keys(array_map(
    static fn(array $route): string => $route['method'] . ' ' . $route['path'],
    $routes
), true);
$expectedBoards = [
    'LET', 'CSE', 'NURSING', 'CRIMINOLOGIST', 'CIVIL_ENGINEERING',
    'MECHANICAL_ENGINEERING', 'REE', 'ARCHITECTURE', 'PSYCHOMETRICIAN', 'CPA',
];
$assessedBoards = array_values(array_filter((array) ($register['board_assessment'] ?? []), 'is_array'));
$assessedBoardNames = array_map(static fn(array $board): string => (string) ($board['BOARD'] ?? ''), $assessedBoards);
sort($assessedBoardNames);
$sortedExpectedBoards = $expectedBoards;
sort($sortedExpectedBoards);
$boardMatrixComplete = $assessedBoardNames === $sortedExpectedBoards
    && count(array_filter($assessedBoards, static fn(array $board): bool => ($board['STATUS'] ?? '') === 'IMPLEMENTED_AND_VERIFIED')) === count($expectedBoards);
$privacyRoutesComplete = isset($routeKeys['GET /privacy/export'], $routeKeys['POST /privacy/data/clear']);
$privacyTestRegistered = is_file($root . '/tools/Tests/AnonymousLearnerPrivacyHttpTest.php')
    && is_file($root . '/app/Services/Privacy/AnonymousLearnerDataService.php');
$boardJourneyTestRegistered = is_file($root . '/tools/Tests/TenBoardLearnerJourneyHttpTest.php');
$mobileStructuralTestsRegistered = is_file($root . '/tools/Tests/LearnerMobileAccessibilityViewTest.php')
    && is_file($root . '/tools/Tests/LearnerNavigationUxTest.php');
$quizSettings = (string) @file_get_contents($root . '/app/Views/quiz/settings.php');
$quizView = (string) @file_get_contents($root . '/app/Views/quiz/index.php');
$examTimerService = (string) @file_get_contents($root . '/app/Services/Quiz/ExamTimerService.php');
$examController = (string) @file_get_contents($root . '/app/Controllers/QuizFlowController.php');
$examHttpTest = (string) @file_get_contents($root . '/tools/Tests/ExamSimulationHttpTest.php');
$unsupportedExamModeDisclosed = str_contains($quizSettings, 'separate from the official board examination')
    && str_contains($quizView, 'separate from the official board examination');
$examSimulationLifecycleVerified = is_file($root . '/app/Views/quiz/review.php')
    && is_file($root . '/public/assets/js/exam-timer.js')
    && str_contains($quizSettings, 'name="timer_mode"')
    && str_contains($examTimerService, 'deadline_epoch')
    && str_contains($examTimerService, 'enforceIfExpired')
    && str_contains($examController, 'exam_reviewed_revision')
    && str_contains($examHttpTest, 'time_expired')
    && str_contains($examHttpTest, 'exact-once');

require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
$privacyOwnerTermsConfigured = false;
try {
    \App\Core\Environment::load($root . '/.env');
    $privacyValues = array_map(
        static fn(string $key): string => trim((string) (\App\Core\Environment::get($key, '') ?? '')),
        ['PRIVACY_OPERATOR_NAME', 'PRIVACY_OPERATOR_CONTACT', 'PRIVACY_RETENTION_NOTICE', 'PRIVACY_HOSTING_NOTICE']
    );
    $privacyOwnerApproved = filter_var(
        \App\Core\Environment::get('PRIVACY_TERMS_APPROVED', 'false'),
        FILTER_VALIDATE_BOOLEAN
    );
    $privacyOwnerTermsConfigured = $privacyOwnerApproved && !in_array('', $privacyValues, true);
} catch (Throwable) {
    $privacyOwnerTermsConfigured = false;
}

$humanAcceptancePath = $root . '/docs/HUMAN_TESTER_READINESS.md';
$humanAcceptance = (string) @file_get_contents($humanAcceptancePath);
$humanCases = [];
preg_match_all('/^\| (MVP-\d{2}) \|.*?HUMAN_STATUS=([A-Z_]+).*?\|$/m', $humanAcceptance, $humanCaseMatches, PREG_SET_ORDER);
foreach ($humanCaseMatches as $match) {
    $humanCases[$match[1]] = $match[2];
}
$expectedHumanCases = array_map(static fn(int $number): string => sprintf('MVP-%02d', $number), range(1, 12));
$humanAcceptanceInventoryComplete = array_keys($humanCases) === $expectedHumanCases;
$humanCasesNotPassed = array_values(array_filter(
    $humanCases,
    static fn(string $status): bool => $status !== 'PASS'
));

$browserValidation = is_array($register['browser_validation'] ?? null)
    ? $register['browser_validation']
    : [];
$browserEvidenceRegistryPath = $root . '/docs/release-evidence/browser-workflows.json';
$browserEvidenceRegistry = ['schema_version' => 1, 'records' => []];
try {
    if (is_file($browserEvidenceRegistryPath)) {
        $decodedBrowserRegistry = json_decode(
            (string) file_get_contents($browserEvidenceRegistryPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        if (is_array($decodedBrowserRegistry)
            && (int) ($decodedBrowserRegistry['schema_version'] ?? 0) === 1
            && is_array($decodedBrowserRegistry['records'] ?? null)) {
            $browserEvidenceRegistry = $decodedBrowserRegistry;
        }
    }
} catch (Throwable) {
    $browserEvidenceRegistry = ['schema_version' => 0, 'records' => []];
}
$browserEvidenceResult = \App\Services\Operations\BrowserEvidenceService::evaluate(
    $browserEvidenceRegistry,
    $head,
    $root,
    \App\Services\Operations\BrowserEvidenceService::applicationAffectingWorkingTreePaths($root)
);
$browserExecution = is_array($browserEvidenceResult['record'] ?? null)
    ? $browserEvidenceResult['record']
    : null;
$requiredBrowserViewports = (array) ($browserValidation['REQUIRED_VIEWPORTS'] ?? []);
$browserExecutionVerified = ($browserEvidenceResult['status'] ?? '') === 'PASS';
$browserValidationStatus = $browserExecutionVerified
    ? 'BROWSER_EXECUTED_PASS'
    : match ((string) ($browserEvidenceResult['status'] ?? '')) {
        'STALE' => 'STALE_EVIDENCE',
        'MISMATCH' => 'COMMIT_MISMATCH',
        'UNVERIFIED' => 'UNVERIFIED_EVIDENCE',
        'DIRTY_WORKTREE' => 'UNCOMMITTED_APPLICATION_CHANGES',
        'FAIL' => 'BROWSER_EXECUTED_FAIL',
        default => 'NOT_VERIFIED',
    };

$mandatoryEvidence = (array) ($register['mandatory_launch_evidence'] ?? []);
foreach ($mandatoryEvidence as &$evidence) {
    if (!is_array($evidence)
        || ($evidence['EVIDENCE'] ?? '') !== 'Real-browser mobile visual, overflow, keyboard, and accessibility behavior') {
        continue;
    }

    if ($browserExecutionVerified) {
        $evidence['STATUS'] = 'PASS';
        $evidence['REASON'] = 'GitHub Actions run ' . (string) ($browserEvidenceResult['run_id'] ?? 'unknown')
            . ' completed successfully on application commit ' . (string) ($browserEvidenceResult['application_commit'] ?? 'unknown')
            . '; evidence is accepted because the assessed commit is exact or all descendants change docs/ files only. Automated Chromium does not establish screen-reader behavior or WCAG conformance.';
    } else {
        $evidence['STATUS'] = 'NOT_VERIFIED';
        $evidence['REASON'] = (string) ($browserEvidenceResult['reason'] ?? 'No verifiable Chromium workflow evidence is registered.');
    }
}
unset($evidence);

$missingEvidence = [];
foreach ($mandatoryEvidence as $evidence) {
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

$criticalCapabilityChecks = [
    'privacy_control_routes' => $privacyRoutesComplete,
    'privacy_service_and_http_regression_test' => $privacyTestRegistered,
    'ten_board_http_journey_test' => $boardJourneyTestRegistered,
    'ten_board_assessment_matrix_complete' => $boardMatrixComplete,
    'exam_simulation_timer_review_and_expiry_regression' => $examSimulationLifecycleVerified,
    'unsupported_exam_mode_claim_disclosed' => $unsupportedExamModeDisclosed,
    'human_acceptance_matrix_complete' => $humanAcceptanceInventoryComplete,
    'current_commit_real_browser_acceptance' => $browserExecutionVerified,
    'owner_approved_privacy_terms_configured' => $privacyOwnerTermsConfigured,
];
$failedCapabilityChecks = array_keys(array_filter(
    $criticalCapabilityChecks,
    static fn(bool $passed): bool => !$passed
));

$featureComplete = $invalidFeatures === []
    && $incompleteFeatures === []
    && $incompleteBoards === []
    && $boardMatrixComplete
    && $privacyRoutesComplete
    && $privacyTestRegistered
    && $boardJourneyTestRegistered
    && $unsupportedExamModeDisclosed
    && $browserExecutionVerified
    && $openSeverityCounts['P0'] === 0
    && $openSeverityCounts['P1'] === 0;
$launchReady = $featureComplete
    && $openFindings === []
    && $blockingCapabilities === []
    && $missingEvidence === []
    && $browserExecutionVerified
    && $privacyOwnerTermsConfigured;

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
    'critical_capability_checks' => $criticalCapabilityChecks,
    'failed_critical_capability_checks' => $failedCapabilityChecks,
    'board_matrix_complete' => $boardMatrixComplete,
    'privacy_owner_terms_configured' => $privacyOwnerTermsConfigured,
    'browser_validation' => [
        'status' => $browserValidationStatus,
        'execution_verified_for_current_commit' => $browserExecutionVerified,
        'evidence_reason' => (string) ($browserEvidenceResult['reason'] ?? ''),
        'evidence_relation' => (string) ($browserEvidenceResult['relation'] ?? 'MISSING'),
        'application_commit' => $browserEvidenceResult['application_commit'] ?? null,
        'assessed_commit' => $head !== '' ? $head : 'UNAVAILABLE',
        'run_id' => $browserEvidenceResult['run_id'] ?? null,
        'workflow_conclusion' => $browserEvidenceResult['conclusion'] ?? null,
        'registry_path' => 'docs/release-evidence/browser-workflows.json',
        'registered_run_count' => $browserEvidenceResult['records_count'] ?? 0,
        'uncommitted_application_paths' => $browserEvidenceResult['uncommitted_application_paths'] ?? [],
        'historical_runs' => $browserEvidenceResult['registered_runs'] ?? [],
        'required_projects' => $browserValidation['REQUIRED_PROJECTS'] ?? [],
        'required_viewports' => $requiredBrowserViewports,
        'viewports_executed' => $browserExecutionVerified ? $requiredBrowserViewports : [],
        'accessibility_engine' => $browserValidation['ACCESSIBILITY_ENGINE'] ?? 'NOT_RECORDED',
        'accessibility_scope' => $browserValidation['ACCESSIBILITY_SCOPE'] ?? 'NOT_RECORDED',
        'reproduction_command' => $browserValidation['AUTOMATION_COMMAND'] ?? '',
        'evidence_artifact' => 'GitHub Actions workflow run and completed job conclusions',
        'human_acceptance' => $humanCases,
        'evidence' => $browserExecution,
    ],
    'human_acceptance_matrix' => [
        'case_count' => count($humanCases),
        'inventory_complete' => $humanAcceptanceInventoryComplete,
        'status_by_case' => $humanCases,
        'not_passed' => $humanCasesNotPassed,
    ],
    'automated_capability_tests_registered' => [
        'privacy_and_isolation' => $privacyTestRegistered,
        'ten_board_http_journeys' => $boardJourneyTestRegistered,
        'mobile_accessibility_structural' => $mobileStructuralTestsRegistered,
    ],
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
    'mandatory_launch_evidence' => $mandatoryEvidence,
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
    echo 'BOARD_MATRIX_COMPLETE=' . ($boardMatrixComplete ? 'YES' : 'NO') . PHP_EOL;
    echo 'PRIVACY_CONTROLS=' . ($privacyRoutesComplete && $privacyTestRegistered ? 'YES' : 'NO') . PHP_EOL;
    echo 'REAL_BROWSER_ACCEPTANCE=' . $browserValidationStatus . PHP_EOL;
    echo 'OWNER_PRIVACY_TERMS=' . ($privacyOwnerTermsConfigured ? 'APPROVED' : 'MISSING_OR_UNAPPROVED') . PHP_EOL;
    echo 'HUMAN_ACCEPTANCE_CASES=' . count($humanCases) . '/12; NOT_PASSED=' . count($humanCasesNotPassed) . PHP_EOL;
    echo 'OPEN_P0_P1=' . ($openSeverityCounts['P0'] + $openSeverityCounts['P1']) . PHP_EOL;
    echo 'BLOCKING_CAPABILITIES=' . count($blockingCapabilities) . PHP_EOL;
    echo 'MANDATORY_EVIDENCE_NOT_PASSED=' . count($missingEvidence) . PHP_EOL;
    if ($missingEvidence !== []) {
        echo 'NEXT_EVIDENCE=' . implode(' | ', $missingEvidence) . PHP_EOL;
    }
}

exit($launchReady ? 0 : ($invalidFeatures === [] ? 1 : 2));
