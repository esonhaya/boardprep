<?php
declare(strict_types=1);

$quiz = file_get_contents(__DIR__ . '/QuizTest.php');
$trait = file_get_contents(__DIR__ . '/QuizTestBlueprintCoverageTrait.php');
if ($quiz === false || $trait === false) { exit("[FAIL] Unable to inspect BlueprintCoverage extraction.\n"); }
if (strpos($quiz, 'use QuizTestBlueprintCoverageTrait;') === false) { exit("[FAIL] QuizTest does not compose QuizTestBlueprintCoverageTrait.\n"); }
if (substr_count($quiz, 'function testBlueprintCoverageBehavior') !== 0) { exit("[FAIL] testBlueprintCoverageBehavior still declared in QuizTest.php.\n"); }

$requiredHelpers = [
    'assertCoverageDependencies',
    'coverageQuestions',
    'coverageRequest',
    'assertCoveragePipeline',
    'assertCoverageValidation'
];
foreach ($requiredHelpers as $helper) {
    if (strpos($trait, "function {$helper}") === false) {
        exit("[FAIL] Missing BlueprintCoverage helper: {$helper}\n");
    }
}
echo "[PASS] BlueprintCoverage extraction contract verified. Helpers: " . count($requiredHelpers) . "\n";
