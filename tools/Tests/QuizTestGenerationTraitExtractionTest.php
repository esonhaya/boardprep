<?php
declare(strict_types=1);

$quiz = file_get_contents(__DIR__ . '/QuizTest.php');
$trait = file_get_contents(__DIR__ . '/QuizTestGenerationTrait.php');
if ($quiz === false || $trait === false) { exit("[FAIL] Unable to inspect Generation extraction.\n"); }
if (strpos($quiz, 'use QuizTestGenerationTrait;') === false) { exit("[FAIL] QuizTest does not compose QuizTestGenerationTrait.\n"); }
if (substr_count($quiz, 'function testGenerationBehavior') !== 0) { exit("[FAIL] testGenerationBehavior still declared in QuizTest.php.\n"); }

$requiredHelpers = [
    'assertGenerationDependencies',
    'generationSpecification',
    'generationQuestions',
    'assertGenerationPipeline',
    'assertGenerationResult'
];
foreach ($requiredHelpers as $helper) {
    if (strpos($trait, "function {$helper}") === false) {
        exit("[FAIL] Missing Generation helper: {$helper}\n");
    }
}
echo "[PASS] Generation extraction contract verified. Helpers: " . count($requiredHelpers) . "\n";
