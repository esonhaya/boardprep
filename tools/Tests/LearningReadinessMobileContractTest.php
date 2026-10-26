<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$progressView = (string) file_get_contents($root . '/app/Views/progress/index.php');
$dashboardView = (string) file_get_contents($root . '/app/Views/dashboard/index.php');
$styles = (string) file_get_contents($root . '/public/assets/css/style.css');
$checks = [
    'progress section has a semantic heading' => str_contains($progressView, 'aria-labelledby="readiness-overview-heading"')
        && str_contains($progressView, 'id="readiness-overview-heading"'),
    'board-specific caveats remain visible' => str_contains($progressView, 'content_depth_caveat')
        && str_contains($progressView, 'not an official examination pass predictor'),
    'subject states expose evidence and action' => str_contains($progressView, 'data-readiness-status=')
        && str_contains($progressView, 'LIMITED_EVIDENCE')
        && str_contains($progressView, 'UNASSESSED'),
    'dashboard offers a details destination' => str_contains($dashboardView, 'dashboard-readiness')
        && str_contains($dashboardView, 'href="/progress"'),
    'readiness cards use shrink-safe grid columns' => str_contains($styles, '.readiness-subjects{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))'),
    'narrow viewport contract collapses to one column' => str_contains($styles, '@media(max-width:600px){.readiness-subjects{grid-template-columns:1fr}'),
    'action links retain touch target and focus indicator' => str_contains($styles, '.readiness-subject a{display:inline-flex;align-items:center;min-height:44px')
        && str_contains($styles, '.readiness-subject a:focus-visible'),
    'long labels wrap without horizontal layout assumptions' => str_contains($styles, '.readiness-overview')
        && str_contains($styles, '.readiness-blueprint-note,.readiness-caveat,.readiness-explanation,.readiness-mistakes{margin:0;overflow-wrap:anywhere}'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, '[FAIL] ' . $label . PHP_EOL);
        exit(1);
    }
}
echo json_encode([
    'result' => 'PASS',
    'checks' => array_keys($checks),
    'static_narrow_viewport_contracts_px' => [320, 360, 390, 430],
    'real_browser_rendering' => 'NOT_EXECUTED',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
