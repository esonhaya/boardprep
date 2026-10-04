<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$report = RepositoryHealthEngine::analyze(false);
$portfolio = RepositoryHealthEngine::portfolioReadinessAudit(['architecture', 'civil-service'], $report);
$rows = array_column((array) ($portfolio['boards'] ?? []), null, 'board_id');

$assert(($portfolio['audit_level'] ?? '') === 'PORTFOLIO_READINESS_SUMMARY', 'Portfolio audit level is missing.');
$assert(count($rows) === 2, 'Board-scoped portfolio projection returned the wrong row count.');
$assert(is_int($rows['architecture']['native'] ?? null) && ($rows['architecture']['native'] ?? 0) > 0, 'Architecture native count was not reused.');
$assert(($rows['architecture']['active_leaves'] ?? null) === 14, 'Architecture active leaf count was not reused.');
$assert(array_key_exists('readiness_counts', (array) ($rows['architecture']['source_readiness'] ?? [])), 'Architecture readiness state was not reused.');
$assert(($rows['civil-service']['native'] ?? null) === 1553, 'CSE native count was not reused.');
$assert(($rows['civil-service']['source_readiness']['board_counts']['native'] ?? null) === 1553, 'CSE readiness summary was not reusable.');
$assert(!array_key_exists('records', (array) ($rows['architecture']['source_readiness'] ?? [])), 'Portfolio output leaked per-leaf records.');

$unknown = RepositoryHealthEngine::portfolioReadinessAudit(['not-configured-board'], $report);
$unknownRow = $unknown['boards'][0] ?? [];
$assert(($unknownRow['native'] ?? null) === null, 'Unknown board received invented count data.');
$assert(($unknownRow['source_readiness'] ?? null) === null, 'Unknown board received invented readiness data.');

echo "batch655_portfolio_readiness=PASS\n";
