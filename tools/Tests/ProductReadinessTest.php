<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$command = escapeshellarg(PHP_BINARY)
    . ' ' . escapeshellarg($root . '/tools/product-readiness.php')
    . ' --json';
$lines = [];
exec($command . ' 2>/dev/null', $lines, $status);
$report = json_decode(implode("\n", $lines), true);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert($status === 1, 'Product readiness must fail closed while launch evidence is unresolved.');
$assert(is_array($report) && ($report['report'] ?? '') === 'PRODUCT_READINESS', 'Product readiness JSON report did not render.');
$assert(($report['technical_release_gate'] ?? '') !== 'PASS', 'Product readiness must not impersonate the technical release gate.');
$assert(($report['product_feature_complete'] ?? null) === false, 'Incomplete or unverified features were marked complete.');
$assert(($report['product_launch_ready'] ?? null) === false, 'Missing mandatory launch evidence did not block launch.');
$assert(($report['invalid_register_entries'] ?? null) === [], 'Incomplete defect/capability evidence was accepted by the register.');
$routeCount = count((array) ($report['inventory']['routes'] ?? []));
$productionRouteCount = (int) ($report['production_web_route_count'] ?? -1);
$nonproductionRouteCount = (int) ($report['nonproduction_web_route_count'] ?? -1);
$assert($routeCount > 0, 'Runtime route inventory is empty.');
$assert($productionRouteCount >= 0
    && $nonproductionRouteCount >= 0
    && $productionRouteCount + $nonproductionRouteCount === $routeCount,
    'Production and non-production route counts do not reconcile to the current inventory.');
$assert(($report['board_matrix_complete'] ?? false) === true, 'The evidence-backed automated ten-board matrix was not recognized.');
$assert(($report['critical_capability_checks']['privacy_control_routes'] ?? false) === true, 'Privacy data-control routes were not detected.');
$assert(($report['critical_capability_checks']['unsupported_exam_mode_claim_disclosed'] ?? false) === true, 'The actual learner-facing exam-mode limitations were not recognized.');
$assert(($report['critical_capability_checks']['exam_simulation_timer_review_and_expiry_regression'] ?? false) === true, 'Timed exam simulation and review regression coverage were not recognized.');
$assert(($report['account_preview']['routes_nonproduction_only'] ?? false) === true, 'Account routes were not verified as nonproduction-only.');
$assert(($report['account_preview']['feature_gate_default_off_and_production_closed'] ?? false) === true, 'Public account routes do not fail closed by default and in production.');
$assert(($report['account_preview']['public_accounts_enabled'] ?? true) === false, 'Product Readiness must not infer public account readiness from local tests.');
$assert(($report['critical_capability_checks']['account_security_and_http_test_coverage_registered'] ?? false) === true, 'Account security regression coverage was not inventoried.');
$accountEnablement = array_values(array_filter(
    (array) ($report['missing_capabilities'] ?? []),
    static fn(mixed $capability): bool => is_array($capability) && ($capability['ID'] ?? '') === 'BP692-CAP-001'
));
$assert(count($accountEnablement) === 1
    && ($accountEnablement[0]['STATUS'] ?? '') === 'PARTIAL'
    && ($accountEnablement[0]['RELEASE_BLOCKING'] ?? true) === false,
    'Public account enablement requirements were not distinguished from frozen MVP release blockers.');
$readinessConfig = json_decode((string) file_get_contents($root . '/config/product-readiness.json'), true, 512, JSON_THROW_ON_ERROR);
$mobileFeatures = array_values(array_filter(
    (array) ($readinessConfig['feature_assessment'] ?? []),
    static fn(mixed $feature): bool => is_array($feature)
        && ($feature['FEATURE'] ?? '') === 'Mobile layout and practical accessibility'
));
$assert(count($mobileFeatures) === 1
    && ($mobileFeatures[0]['STATUS'] ?? '') === 'IMPLEMENTED_AND_VERIFIED'
    && str_contains(implode(' ', (array) ($mobileFeatures[0]['EVIDENCE'] ?? [])), 'no screen-reader acceptance'),
    'Automated mobile progress must be recorded without claiming screen-reader acceptance.');
$browserExecutionVerified = ($report['browser_validation']['execution_verified_for_current_commit'] ?? false) === true;
$assert(($report['critical_capability_checks']['current_commit_real_browser_acceptance'] ?? false) === $browserExecutionVerified, 'Browser evidence status does not match the current-commit verification.');
$expectedBrowserStatus = $browserExecutionVerified
    ? 'BROWSER_EXECUTED_PASS'
    : match ((string) ($report['browser_validation']['status'] ?? '')) {
        'STALE_EVIDENCE', 'COMMIT_MISMATCH', 'UNVERIFIED_EVIDENCE', 'UNCOMMITTED_APPLICATION_CHANGES', 'BROWSER_EXECUTED_FAIL' => $report['browser_validation']['status'],
        default => 'NOT_VERIFIED',
    };
$assert(($report['browser_validation']['status'] ?? '') === $expectedBrowserStatus, 'Browser evidence freshness and conclusion were not reported distinctly.');
$assert(count((array) ($report['browser_validation']['required_viewports'] ?? [])) === 5, 'Required responsive viewport evidence was not inventoried.');
$assert(($report['browser_validation']['registered_run_count'] ?? 0) >= 1, 'GitHub browser workflow evidence was not registered.');
if ($browserExecutionVerified) {
    $assert(in_array(($report['browser_validation']['evidence_relation'] ?? ''), ['EXACT', 'DOCUMENTATION_ONLY_DESCENDANT'], true), 'Browser evidence passed without an exact or documentation-only commit relationship.');
    $assert(($report['browser_validation']['workflow_conclusion'] ?? '') === 'success', 'Browser evidence passed without a successful GitHub workflow conclusion.');
}
$dirtyBrowserPaths = (array) ($report['browser_validation']['uncommitted_application_paths'] ?? []);
if (($report['browser_validation']['status'] ?? '') === 'UNCOMMITTED_APPLICATION_CHANGES') {
    $assert($dirtyBrowserPaths !== [], 'Product readiness reported uncommitted changes without listing their paths.');
    $assert(str_contains((string) ($report['browser_validation']['evidence_reason'] ?? ''), 'uncommitted application-affecting changes'), 'Product readiness did not explain why dirty-checkout browser evidence is unverified.');
}
$browserEvidence = array_values(array_filter(
    (array) ($report['mandatory_launch_evidence'] ?? []),
    static fn(mixed $evidence): bool => is_array($evidence)
        && ($evidence['EVIDENCE'] ?? '') === 'Real-browser mobile visual, overflow, keyboard, and accessibility behavior'
));
$assert(count($browserEvidence) === 1, 'Mandatory current-commit browser evidence was not inventoried exactly once.');
$assert(($browserEvidence[0]['STATUS'] ?? '') === ($browserExecutionVerified ? 'PASS' : 'NOT_VERIFIED'), 'Mandatory browser evidence status does not follow current-commit execution.');
if ($browserExecutionVerified) {
    $assert(str_contains((string) ($browserEvidence[0]['REASON'] ?? ''), 'does not establish screen-reader behavior'), 'Browser evidence overclaims assistive-technology coverage.');
}
$humanGuide = (string) file_get_contents($root . '/docs/HUMAN_TESTER_READINESS.md');
$assert(str_contains($humanGuide, 'MVP-06 uses five') && !str_contains($humanGuide, 'Start a three-question exam'), 'Human acceptance instructions request a quiz count the learner UI does not offer.');
$assert(($report['critical_capability_checks']['owner_approved_privacy_terms_configured'] ?? true) === false, 'Missing owner privacy approval did not fail closed.');
$assert(($report['human_acceptance_matrix']['inventory_complete'] ?? false) === true, 'The canonical twelve-case human matrix was not inventoried.');
$assert(count((array) ($report['human_acceptance_matrix']['not_passed'] ?? [])) === 12, 'Human acceptance was inferred from automation.');
$humanStatuses = (array) ($report['human_acceptance_matrix']['status_by_case'] ?? []);
$assert(count($humanStatuses) === 12
    && count(array_filter($humanStatuses, static fn(mixed $status): bool => $status === 'NOT_RUN')) === 12,
    'Automated evidence must preserve all twelve human statuses as NOT_RUN until testers record outcomes.');
$assert(in_array('app/Controllers/DataHandlingController.php', (array) ($report['inventory']['controllers'] ?? []), true), 'Data handling controller is missing from the inventory.');
$assert(in_array('tools/Tests/StudyLibraryNavigationTest.php', (array) ($report['inventory']['tests'] ?? []), true), 'Study navigation integration test is missing from the inventory.');
$assert(count((array) ($report['board_assessment'] ?? [])) === 10, 'Board-by-board evidence rows are incomplete.');
$assert(count((array) ($report['incomplete_boards'] ?? [])) === 0, 'Automated ten-board journey evidence was not recorded.');
$quizSettings = (string) file_get_contents($root . '/app/Views/quiz/settings.php');
$assert(str_contains($quizSettings, 'name="timer_mode"')
    && str_contains($quizSettings, 'separate from the official board examination'), 'Exam simulation timer choices and official-exam distinction are not disclosed at setup.');
$discoveredDefects = (array) ($report['defects_discovered_by_severity'] ?? []);
$remainingDefects = (array) ($report['defects_remaining_by_severity'] ?? []);
foreach (['P0', 'P1', 'P2', 'P3'] as $severity) {
    $discovered = $discoveredDefects[$severity] ?? null;
    $remaining = $remainingDefects[$severity] ?? null;
    $assert(is_int($discovered) && $discovered >= 0
        && is_int($remaining) && $remaining >= 0 && $remaining <= $discovered,
        "Product defect inventory does not reconcile for {$severity}.");
}
$assert(($remainingDefects['P0'] ?? -1) === 0, 'A P0 product defect remains open.');
$assert(($remainingDefects['P1'] ?? -1) === 0, 'A P1 product defect remains open.');
$assert(($report['blocking_missing_capabilities'] ?? []) !== [], 'Known missing launch capability was dropped from the gate.');

echo "[PASS] Product readiness inventories the app and remains distinct from the technical release gate.\n";
echo "[PASS] Unverified mandatory evidence and missing launch capabilities fail the product launch gate.\n";
