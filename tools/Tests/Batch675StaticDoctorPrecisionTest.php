<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__) . '/Doctor/Engine/DoctorRunner.php';

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Project\BoardPrep\Checks\DependencyConsistencyCheck;
use Tools\Doctor\Project\BoardPrep\Checks\DependencyFindingClassifier;
use Tools\Doctor\Project\Shared\Checks\CallSignatureCheck;
use Tools\Doctor\Snapshot\ProjectSnapshotBuilder;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('Batch 675 static Doctor precision failed: ' . $message);
};

$snapshot = (new ProjectSnapshotBuilder())->build();
DoctorContext::setSnapshot($snapshot);
$dependency = (new DependencyConsistencyCheck())->run();
$signature = (new CallSignatureCheck())->run();
$assert($dependency->status === 'PASS', 'dependency findings have no unresolved entries');
$dependencyCounts = [];
foreach ($dependency->details as $detail) {
    if (preg_match('/\A(Raw analyzer findings|FIXED_ANALYZER|JUSTIFIED_DYNAMIC|UNRESOLVED): (\d+)\z/', $detail, $match) === 1) {
        $dependencyCounts[$match[1]] = (int) $match[2];
    }
}
$assert(isset($dependencyCounts['Raw analyzer findings'], $dependencyCounts['FIXED_ANALYZER'], $dependencyCounts['JUSTIFIED_DYNAMIC'], $dependencyCounts['UNRESOLVED'])
    && $dependencyCounts['Raw analyzer findings'] === $dependencyCounts['FIXED_ANALYZER'] + $dependencyCounts['JUSTIFIED_DYNAMIC'] + $dependencyCounts['UNRESOLVED']
    && $dependencyCounts['FIXED_ANALYZER'] > 0
    && $dependencyCounts['JUSTIFIED_DYNAMIC'] > 0
    && $dependencyCounts['UNRESOLVED'] === 0,
    'Every raw dependency finding must remain visible in exactly one classification, with no unresolved entries.');
$assert($signature->status === 'PASS', 'call signatures have no unresolved entries');
$assert(in_array('FIXED_ANALYZER variadic methods: 8', $signature->details, true), 'only eight over-arity calls to true variadic methods are classified as analyzer artifacts');
$assert(in_array('JUSTIFIED_DYNAMIC verified spread forwarding: 1', $signature->details, true), 'the one verified spread-forwarding call is classified separately');
$assert(DependencyFindingClassifier::isVariadicArgumentCountFalsePositive('ContentPriorityService', 'key', 4, $snapshot->classMap), 'a true variadic target with scanner-rejected extra arguments is identified');
$assert(!DependencyFindingClassifier::isVariadicArgumentCountFalsePositive('QuestionCoverageService', 'taxonomyOrphans', 1, $snapshot->classMap), 'a non-variadic target is never excused as variadic');

$aliasFile = tempnam(sys_get_temp_dir(), 'b675-alias-');
$globalFile = tempnam(sys_get_temp_dir(), 'b675-global-');
$falseMatchFile = tempnam(sys_get_temp_dir(), 'b675-near-');
$unknownFile = tempnam(sys_get_temp_dir(), 'b675-unknown-');
if ($aliasFile === false || $globalFile === false || $falseMatchFile === false || $unknownFile === false) {
    throw new RuntimeException('Unable to create bounded analyzer test fixtures.');
}
try {
    file_put_contents($aliasFile, "<?php\nnamespace Batch675Fixture;\nuse App\\Core\\App;\n\\App\\Services\\Shared\\TaxonomyStorageService::label('boards', 'civil-service');\n");
    $alias = DependencyFindingClassifier::classify([
        'file' => $aliasFile,
        'line' => 4,
        'message' => 'Referenced class App\\Core\\App\\Services\\Shared\\TaxonomyStorageService cannot be resolved',
    ], []);
    $assert(($alias['classification'] ?? '') === 'FIXED_ANALYZER', 'an import-alias expansion false positive is recognized from its exact source token');

    file_put_contents($globalFile, "<?php\nnamespace Batch675Fixture;\n\\QuizSpecification::class;\n");
    $global = DependencyFindingClassifier::classify([
        'file' => $globalFile,
        'line' => 3,
        'message' => 'Referenced type Batch675Fixture\\QuizSpecification cannot be resolved',
    ], ['QuizSpecification' => ['file' => $globalFile]]);
    $assert(($global['classification'] ?? '') === 'FIXED_ANALYZER', 'an explicitly root-qualified global type is resolved without the file namespace');

    file_put_contents($falseMatchFile, "<?php\nnamespace Batch675Fixture;\n\\Unrelated\\QuizSpecification::class;\n");
    $nearMiss = DependencyFindingClassifier::classify([
        'file' => $falseMatchFile,
        'line' => 3,
        'message' => 'Referenced type Batch675Fixture\\QuizSpecification cannot be resolved',
    ], ['Unrelated\\QuizSpecification' => ['file' => $falseMatchFile]]);
    $assert($nearMiss === null, 'a different FQCN with the same short name is not silently classified');

    file_put_contents($unknownFile, "<?php\nnamespace Batch675Fixture;\n\\Missing\\UnknownDependency::class;\n");
    $unknown = DependencyFindingClassifier::classify([
        'file' => $unknownFile,
        'line' => 3,
        'message' => 'Referenced class Batch675Fixture\\Missing\\UnknownDependency cannot be resolved',
    ], []);
    $assert($unknown === null, 'a genuinely unresolved root-qualified dependency remains unclassified and therefore fails the Doctor check');
} finally {
    @unlink($aliasFile);
    @unlink($globalFile);
    @unlink($falseMatchFile);
    @unlink($unknownFile);
}

$questionCoverageFile = dirname(__DIR__, 2) . '/app/Services/Shared/QuestionCoverageService.php';
$questionCoverageLines = file($questionCoverageFile, FILE_IGNORE_NEW_LINES);
$forwardingCallLines = [];
foreach ($questionCoverageLines ?: [] as $lineNumber => $sourceLine) {
    if (preg_match('/taxonomy_orphans.*self::taxonomyOrphans\(\.\.\.\$groups\)/', $sourceLine) === 1) {
        $forwardingCallLines[] = $lineNumber + 1;
    }
}
$assert(count($forwardingCallLines) === 1, 'the six-group caller remains the sole spread-forwarding path');
$forwarding = DependencyFindingClassifier::isVerifiedVariadicForwarding(
    './app/Services/Shared/QuestionCoverageService.php',
    $forwardingCallLines[0],
    'App\\Services\\Shared\\QuestionCoverageService',
    'taxonomyOrphans',
    $snapshot->classMap
);
$assert($forwarding, 'the six-group caller proves the sole spread-forwarding path');
echo "BATCH675_STATIC_DOCTOR_PRECISION=PASS\n";
