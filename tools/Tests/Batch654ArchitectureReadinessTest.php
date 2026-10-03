<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;

function b654_test_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$health = RepositoryHealthEngine::analyze(false);
$architecture = $health->boardSummaries['architecture'] ?? [];
$architectureReadiness = $health->summary['source_readiness_by_board']['architecture'] ?? [];
$cse = $health->boardSummaries['civil-service'] ?? [];
$cseReadiness = $health->summary['source_readiness_by_board']['civil-service'] ?? [];

b654_test_assert(($health->summary['fail_count'] ?? 0) === 0, 'Permanent health has FAIL findings.');
b654_test_assert(($architecture['canonical'] ?? 0) === 472, 'Architecture native count changed.');
b654_test_assert(($architecture['effective_declared'] ?? 0) === 472, 'Architecture effective count changed.');
b654_test_assert(($architecture['runtime'] ?? 0) === 472, 'Architecture runtime count changed.');
b654_test_assert(($architecture['active_leaves'] ?? 0) === 14, 'Architecture active leaf count changed.');
b654_test_assert(($architecture['depth_known'] ?? 0) === 472 && ($architecture['depth_unknown'] ?? -1) === 0, 'Reuse metadata depth index was not applied to Architecture.');
b654_test_assert(($architectureReadiness['valid'] ?? false) === true, 'Architecture readiness collection is invalid.');
b654_test_assert(($architectureReadiness['record_count'] ?? 0) === 14, 'Architecture readiness record count is incomplete.');
b654_test_assert(($architectureReadiness['readiness_counts'] ?? []) === ['READY' => 0, 'READY_WITH_LIMITS' => 8, 'REVIEW' => 5, 'DEFER' => 1], 'Architecture readiness states changed.');
b654_test_assert(($architectureReadiness['raw_thinness'] ?? []) === ['zero' => 0, 'under5' => 0, 'under10' => 2, 'under20' => 6], 'Architecture raw thinness changed.');
b654_test_assert(($architectureReadiness['authorable_thinness'] ?? []) === ['zero' => 0, 'under5' => 0, 'under10' => 2, 'under20' => 6], 'Architecture authorable thinness summary changed.');
b654_test_assert(($architectureReadiness['visual_suitability_counts'] ?? []) === ['TEXT_SUITABLE' => 6, 'TEXT_SUITABLE_WITH_LIMITS' => 4, 'VISUAL_PREFERRED' => 3, 'VISUAL_REQUIRED' => 1, 'MIXED' => 0, 'UNKNOWN' => 0], 'Architecture visual suitability metadata changed.');
b654_test_assert(($architectureReadiness['source_study_defects'] ?? []) === ['source' => 0, 'study' => 0, 'traceability' => 0, 'currentness' => 0], 'Architecture source/study defect summary changed.');
b654_test_assert(($architectureReadiness['high_value_headroom'] ?? -1) === 12, 'Architecture high-value headroom summary changed.');

$areas = $architecture['official_area_balance'] ?? [];
$areaShares = [];
foreach ($areas as $area) $areaShares[(string) ($area['area_id'] ?? '')] = (float) ($area['actual_share'] ?? -1);
b654_test_assert($areaShares === ['arch-area-a' => 48.31, 'arch-area-b' => 19.49, 'arch-area-c' => 32.2], 'Architecture official-area mapper did not use granular coverage rows.');

// CSE proves that the same summary service remains reusable outside the new
// Architecture collection; its inherited shared/native distinction is not
// treated as Architecture-specific data.
b654_test_assert(($cse['canonical'] ?? 0) === 1553, 'CSE native count changed.');
b654_test_assert(($cseReadiness['record_count'] ?? 0) > 0, 'CSE readiness summary disappeared.');
b654_test_assert(is_array($cseReadiness['board_counts'] ?? null) && ($cseReadiness['board_counts']['native'] ?? 0) === 1553, 'CSE board-scoped readiness counts were not reused.');
b654_test_assert(array_key_exists('raw_thinness', $cseReadiness) && array_key_exists('authorable_thinness', $cseReadiness), 'CSE reusable thinness fields are missing.');

$deep = RepositoryHealthEngine::readinessAudit('architecture', $health);
b654_test_assert(($deep['audit_level'] ?? '') === 'DEEP_READINESS_AUDIT', 'Board-scoped deep readiness projection is missing.');
b654_test_assert(($deep['board_id'] ?? '') === 'architecture', 'Board-scoped deep readiness selected the wrong board.');
b654_test_assert(($deep['readiness']['record_count'] ?? 0) === 14, 'Board-scoped deep readiness did not reuse the Architecture summary.');

echo "batch654_architecture_readiness=PASS\n";
