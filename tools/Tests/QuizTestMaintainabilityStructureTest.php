<?php
declare(strict_types=1);

$quiz = file_get_contents(__DIR__ . '/QuizTest.php');
if ($quiz === false) { exit("[FAIL] Cannot read QuizTest.php.\n"); }

$traits = [
    'QuizTestNavigationTrait',
    'QuizTestSubmissionTrait',
    'QuizTestGenerationTrait',
    'QuizTestBlueprintDistributionTrait',
    'QuizTestBlueprintCoverageTrait',
];

foreach ($traits as $trait) {
    if (strpos($quiz, "use {$trait};") === false) {
        exit("[FAIL] Missing trait composition: {$trait}\n");
    }
    $source = file_get_contents(__DIR__ . "/{$trait}.php");
    if ($source === false) { exit("[FAIL] Cannot read {$trait}.php.\n"); }
    if (largestMethodSpan($source) > 80) {
        exit("[FAIL] {$trait} still contains a method larger than 80 lines.\n");
    }
}
echo "[PASS] QuizTest maintainability structure verified. Traits: 5; max method <= 80 lines\n";

function largestMethodSpan(string $source): int
{
    $lines = preg_split('/\R/', $source);
    $largest = 0;
    $start = null;
    $depth = 0;
    foreach ($lines as $index => $line) {
        if ($start === null && preg_match('/function\s+\w+\s*\(/', $line)) {
            $start = $index;
            $depth = 0;
        }
        if ($start !== null) {
            $depth += substr_count($line, '{');
            $depth -= substr_count($line, '}');
            if ($depth === 0 && strpos($line, '}') !== false) {
                $largest = max($largest, $index - $start + 1);
                $start = null;
            }
        }
    }
    return $largest;
}
