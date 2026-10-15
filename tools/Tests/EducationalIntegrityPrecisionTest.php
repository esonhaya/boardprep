<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

$result = (new \Tools\Doctor\Project\BoardPrep\Checks\EducationalIntegrityCheck())->run();
$details = (array) $result->details;
$has = static fn(string $fragment): bool => count(array_filter(
    $details,
    static fn(string $detail): bool => str_contains($detail, $fragment)
)) > 0;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert($has('HARD_DEFECT count='), 'Hard defects are not distinguished.');
$assert($has('REVIEW_REQUIRED count='), 'Review-required evidence is not distinguished.');
$assert($has('JUSTIFIED_EXCEPTION count='), 'Documented exclusions are not distinguished.');
$assert($has('INSUFFICIENT_EVIDENCE count='), 'Coverage signals are not distinguished from defects.');
$assert($has('source_id=boardprep-blueprint-targets'), 'The explicit internal editorial source exception is not identified.');
$assert($has('question_id=263') && $has('board=let'), 'A justified flashcard exclusion does not expose its exact question and board.');
$assert($has('questions=8962 source_supported=8962 missing=0'), 'Question-to-source-study coverage is not reported with counts.');
$assert($has('repository_record_id=b633-arch-site-capacity') && $has('board=architecture'), 'Coverage evidence does not identify the affected taxonomy leaf and board.');
$assert($has('remote URL reachability is not tested'), 'URL shape validation is overstated as remote availability verification.');
$assert($result->status !== 'FAIL', 'Existing justified exceptions or insufficient evidence became hard defects.');

echo "[PASS] Educational integrity classifies exact defects, review items, exceptions, and insufficient-evidence records.\n";
echo "[PASS] Source-study coverage is board-aware and remote URL availability is not overclaimed.\n";
