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
$assert(count((array) ($report['inventory']['routes'] ?? [])) === 60, 'Runtime route inventory count is inconsistent.');
$assert(($report['production_web_route_count'] ?? 0) === 17, 'Production route count is inconsistent.');
$assert(($report['nonproduction_web_route_count'] ?? 0) === 43, 'Non-production route count is inconsistent.');
$assert(in_array('app/Controllers/DataHandlingController.php', (array) ($report['inventory']['controllers'] ?? []), true), 'Data handling controller is missing from the inventory.');
$assert(in_array('tools/Tests/StudyLibraryNavigationTest.php', (array) ($report['inventory']['tests'] ?? []), true), 'Study navigation integration test is missing from the inventory.');
$assert(count((array) ($report['board_assessment'] ?? [])) === 10, 'Board-by-board evidence rows are incomplete.');
$assert(count((array) ($report['incomplete_boards'] ?? [])) === 10, 'Unverified board journeys were not included in the feature-completeness gate.');
$assert(str_contains((string) file_get_contents($root . '/app/Views/quiz/settings.php'), 'does not enforce a timer'), 'Exam-mode limitations are not disclosed at setup.');
$assert(($report['defects_discovered_by_severity']['P0'] ?? -1) === 0, 'Unexpected P0 defect was omitted.');
$assert(($report['defects_discovered_by_severity']['P1'] ?? -1) === 0, 'Unexpected P1 defect was omitted.');
$assert(($report['defects_remaining_by_severity']['P0'] ?? -1) === 0, 'Product gate is incorrectly treating a resolved P0 as open.');
$assert(($report['blocking_missing_capabilities'] ?? []) !== [], 'Known missing launch capability was dropped from the gate.');

echo "[PASS] Product readiness inventories the app and remains distinct from the technical release gate.\n";
echo "[PASS] Unverified mandatory evidence and missing launch capabilities fail the product launch gate.\n";
