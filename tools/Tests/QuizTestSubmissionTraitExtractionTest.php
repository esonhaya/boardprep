<?php
declare(strict_types=1);

$quiz = file_get_contents(__DIR__ . '/QuizTest.php');
$trait = file_get_contents(__DIR__ . '/QuizTestSubmissionTrait.php');
if ($quiz === false || $trait === false) { exit("[FAIL] Unable to inspect Submission extraction.\n"); }
if (strpos($quiz, 'use QuizTestSubmissionTrait;') === false) { exit("[FAIL] QuizTest does not compose QuizTestSubmissionTrait.\n"); }
if (substr_count($quiz, 'function testSubmissionBehavior') !== 0) { exit("[FAIL] testSubmissionBehavior still declared in QuizTest.php.\n"); }

$requiredHelpers = [
    'assertSubmissionDependencies',
    'seedSubmissionSession',
    'storeSubmissionAnswer',
    'assertStoredSubmissionAnswer',
    'clearSubmissionSession'
];
foreach ($requiredHelpers as $helper) {
    if (strpos($trait, "function {$helper}") === false) {
        exit("[FAIL] Missing Submission helper: {$helper}\n");
    }
}
echo "[PASS] Submission extraction contract verified. Helpers: " . count($requiredHelpers) . "\n";
