<?php
declare(strict_types=1);

$quiz = file_get_contents(__DIR__ . '/QuizTest.php');
$trait = file_get_contents(__DIR__ . '/QuizTestBlueprintDistributionTrait.php');
if ($quiz === false || $trait === false) { exit("[FAIL] Unable to inspect BlueprintDistribution extraction.\n"); }
if (strpos($quiz, 'use QuizTestBlueprintDistributionTrait;') === false) { exit("[FAIL] QuizTest does not compose QuizTestBlueprintDistributionTrait.\n"); }
if (substr_count($quiz, 'function testBlueprintDistributionBehavior') !== 0) { exit("[FAIL] testBlueprintDistributionBehavior still declared in QuizTest.php.\n"); }

$requiredHelpers = [
    'assertDistributionDependency',
    'distributionBoardBlueprint',
    'distributionSubjectBlueprints',
    'assertDistributionPipeline',
    'assertDistributionRequests'
];
foreach ($requiredHelpers as $helper) {
    if (strpos($trait, "function {$helper}") === false) {
        exit("[FAIL] Missing BlueprintDistribution helper: {$helper}\n");
    }
}
echo "[PASS] BlueprintDistribution extraction contract verified. Helpers: " . count($requiredHelpers) . "\n";
