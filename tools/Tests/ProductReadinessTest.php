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
$assert(count((array) ($report['inventory']['routes'] ?? [])) === 62, 'Runtime route inventory count is inconsistent.');
$assert(($report['production_web_route_count'] ?? 0) === 19, 'Production route count is inconsistent.');
$assert(($report['nonproduction_web_route_count'] ?? 0) === 43, 'Non-production route count is inconsistent.');
$assert(($report['board_matrix_complete'] ?? false) === true, 'The evidence-backed automated ten-board matrix was not recognized.');
$assert(($report['critical_capability_checks']['privacy_control_routes'] ?? false) === true, 'Privacy data-control routes were not detected.');
$assert(($report['critical_capability_checks']['unsupported_exam_mode_claim_disclosed'] ?? false) === true, 'The actual learner-facing exam-mode limitations were not recognized.');
$assert(($report['critical_capability_checks']['exam_simulation_timer_review_and_expiry_regression'] ?? false) === true, 'Timed exam simulation and review regression coverage were not recognized.');
$browserExecutionVerified = ($report['browser_validation']['execution_verified_for_current_commit'] ?? false) === true;
$assert(($report['critical_capability_checks']['current_commit_real_browser_acceptance'] ?? false) === $browserExecutionVerified, 'Browser evidence status does not match the current-commit verification.');
$assert(($report['browser_validation']['status'] ?? '') === ($browserExecutionVerified ? 'BROWSER_EXECUTED_PASS' : 'TEST_PREPARED_NOT_EXECUTED'), 'Browser execution state was not reported distinctly.');
$assert(count((array) ($report['browser_validation']['required_viewports'] ?? [])) === 5, 'Required responsive viewport evidence was not inventoried.');
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
$assert(in_array('app/Controllers/DataHandlingController.php', (array) ($report['inventory']['controllers'] ?? []), true), 'Data handling controller is missing from the inventory.');
$assert(in_array('tools/Tests/StudyLibraryNavigationTest.php', (array) ($report['inventory']['tests'] ?? []), true), 'Study navigation integration test is missing from the inventory.');
$assert(count((array) ($report['board_assessment'] ?? [])) === 10, 'Board-by-board evidence rows are incomplete.');
$assert(count((array) ($report['incomplete_boards'] ?? [])) === 0, 'Automated ten-board journey evidence was not recorded.');
$quizSettings = (string) file_get_contents($root . '/app/Views/quiz/settings.php');
$assert(str_contains($quizSettings, 'name="timer_mode"')
    && str_contains($quizSettings, 'separate from the official board examination'), 'Exam simulation timer choices and official-exam distinction are not disclosed at setup.');
$assert(($report['defects_discovered_by_severity']['P0'] ?? -1) === 0, 'Unexpected P0 defect was omitted.');
$assert(($report['defects_discovered_by_severity']['P1'] ?? -1) === 4, 'The two prior and two Batch 665 P1 board journey findings were not preserved in the register.');
$assert(($report['defects_remaining_by_severity']['P0'] ?? -1) === 0, 'Product gate is incorrectly treating a resolved P0 as open.');
$assert(($report['defects_remaining_by_severity']['P1'] ?? -1) === 0, 'A fixed board journey defect remains open.');
$assert(($report['blocking_missing_capabilities'] ?? []) !== [], 'Known missing launch capability was dropped from the gate.');

echo "[PASS] Product readiness inventories the app and remains distinct from the technical release gate.\n";
echo "[PASS] Unverified mandatory evidence and missing launch capabilities fail the product launch gate.\n";
