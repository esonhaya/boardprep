<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('Batch672 count reconciliation failed: ' . $message);
};

$health = RepositoryHealthEngine::analyze(false);
$questionRows = \App\Core\App::storage()->all('questions');
$nativeCounts = [];
foreach ($questionRows as $question) {
    if (!is_array($question)) continue;
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $boardId = strtolower(trim((string) ($taxonomy['board_id'] ?? $question['board'] ?? '')));
    $boardId = ['cse' => 'civil-service', 'cle' => 'criminologist', 'nle' => 'nursing', 'ple' => 'psychometrician'][$boardId] ?? $boardId;
    if ($boardId !== '') $nativeCounts[$boardId] = ($nativeCounts[$boardId] ?? 0) + 1;
}
$assert(count($health->boardSummaries) === 10, 'all ten registered board summaries are present');
$totals = ['canonical' => 0, 'effective_declared' => 0, 'runtime' => 0];
foreach ($health->boardSummaries as $boardId => $row) {
    $canonical = (int) ($row['canonical'] ?? -1);
    $effective = (int) ($row['effective_declared'] ?? -1);
    $runtime = (int) ($row['runtime'] ?? -1);
    $assert($canonical === (int) ($nativeCounts[$boardId] ?? 0), 'native question inventory reconciles for ' . $boardId);
    $assert($effective >= 0 && $runtime >= 0, 'effective and runtime totals are valid for ' . $boardId);
    $totals['canonical'] += $canonical;
    $totals['effective_declared'] += $effective;
    $totals['runtime'] += $runtime;
}
$assert($totals['canonical'] === count($questionRows), 'native per-board inventory reconciles to the current canonical collection');
$assert($totals === $health->statistics->totals, 'portfolio totals reconcile with the independent statistics projection');
$assert((int) ($health->summary['fail_count'] ?? -1) === 0, 'health snapshot has no FAIL findings');
$reviewExpectations = [
    'let' => ['source_scope_fit_unknown' => 0, 'structural_not_actionable' => 3],
    'civil-service' => ['structural_not_actionable' => 0],
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
$assert($reviewTotal === 8, 'three CSE concepts have verified parent-scope crosswalks; eight deferred or justified review signals remain');
echo "BATCH672_COUNT_RECONCILIATION=PASS\n";
echo 'NATIVE=' . $totals['canonical'] . ' EFFECTIVE=' . $totals['effective_declared'] . ' RUNTIME=' . $totals['runtime'] . ' SOURCE_REVIEW_SIGNALS=' . $reviewTotal . "\n";
