<?php
declare(strict_types=1);

$quiz = file_get_contents(__DIR__ . '/QuizTest.php');
$trait = file_get_contents(__DIR__ . '/QuizTestNavigationTrait.php');
if ($quiz === false || $trait === false) { exit("[FAIL] Unable to inspect Navigation extraction.\n"); }
if (strpos($quiz, 'use QuizTestNavigationTrait;') === false) { exit("[FAIL] QuizTest does not compose QuizTestNavigationTrait.\n"); }
if (substr_count($quiz, 'function testNavigationBehavior') !== 0) { exit("[FAIL] testNavigationBehavior still declared in QuizTest.php.\n"); }

$requiredHelpers = [
    'assertNavigationDependency',
    'resetNavigationSession',
    'assertEmptyNavigationState',
    'seedNavigationQuestions',
    'assertNavigationProgression'
];
foreach ($requiredHelpers as $helper) {
    if (strpos($trait, "function {$helper}") === false) {
        exit("[FAIL] Missing Navigation helper: {$helper}\n");
    }
}
echo "[PASS] Navigation extraction contract verified. Helpers: " . count($requiredHelpers) . "\n";
