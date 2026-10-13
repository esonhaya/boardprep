<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('Batch672 count reconciliation failed: ' . $message);
};

$health = RepositoryHealthEngine::analyze(false);
$expected = [
    'architecture' => [484, 484, 484],
    'civil-engineering' => [922, 922, 922],
    'civil-service' => [1553, 1807, 2116],
    'cpa' => [1132, 1132, 1132],
    'criminologist' => [451, 554, 554],
    'let' => [1788, 1871, 1886],
    'mechanical-engineering' => [511, 511, 511],
    'nursing' => [878, 898, 898],
    'psychometrician' => [401, 401, 401],
    'registered-electrical-engineering' => [842, 842, 842],
];
$totals = ['native' => 0, 'effective' => 0, 'runtime' => 0];
foreach ($expected as $boardId => [$native, $effective, $runtime]) {
    $row = $health->boardSummaries[$boardId] ?? [];
    $actual = [(int) ($row['canonical'] ?? -1), (int) ($row['effective_declared'] ?? -1), (int) ($row['runtime'] ?? -1)];
    $assert($actual === [$native, $effective, $runtime], 'expected native/effective/runtime for ' . $boardId . ', got ' . json_encode($actual));
    $totals['native'] += $actual[0];
    $totals['effective'] += $actual[1];
    $totals['runtime'] += $actual[2];
}
$assert($totals === ['native' => 8962, 'effective' => 9422, 'runtime' => 9746], 'portfolio totals reconcile across all ten boards');
$assert((int) ($health->summary['fail_count'] ?? -1) === 0, 'health snapshot has no FAIL findings');
$reviewExpectations = [
    'let' => ['source_scope_fit_unknown' => 35, 'structural_not_actionable' => 3],
    'civil-service' => ['structural_not_actionable' => 3],
    'criminologist' => ['structural_not_actionable' => 4],
    'psychometrician' => ['currentness_unknown' => 1],
];
$reviewTotal = 0;
foreach ($reviewExpectations as $boardId => $fields) {
    $reviews = $health->summary['source_readiness_by_board'][$boardId]['source_study_reviews'] ?? [];
    foreach ($fields as $field => $expectedCount) {
        $actualCount = (int) ($reviews[$field] ?? 0);
        $assert($actualCount === $expectedCount, 'review signal ' . $field . ' for ' . $boardId . ' is classified and stable');
        $reviewTotal += $actualCount;
    }
}
$assert($reviewTotal === 46, 'all 46 existing source review signals remain explicitly classified');
echo "BATCH672_COUNT_RECONCILIATION=PASS\n";
echo 'NATIVE=' . $totals['native'] . ' EFFECTIVE=' . $totals['effective'] . ' RUNTIME=' . $totals['runtime'] . ' SOURCE_REVIEW_SIGNALS=' . $reviewTotal . "\n";
