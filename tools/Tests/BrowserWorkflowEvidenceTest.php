<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Operations\BrowserEvidenceService;

$root = dirname(__DIR__, 2);
$registry = json_decode((string) file_get_contents($root . '/docs/release-evidence/browser-workflows.json'), true, 512, JSON_THROW_ON_ERROR);
$verifiedBaseline = 'deed26cdb202e496cb84aab66310fbd256a5e1b4';
$actual = BrowserEvidenceService::evaluate($registry, $verifiedBaseline, $root);
$batch677Records = array_values(array_filter(
    $registry['records'] ?? [],
    static fn(array $record): bool => (int) ($record['run_id'] ?? 0) === 38641793846
));
$batch677 = BrowserEvidenceService::evaluate(['schema_version' => 1, 'records' => $batch677Records], $verifiedBaseline, $root);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(($actual['status'] ?? '') === 'PASS', 'Verified Batch 677 browser workflow should cover the documentation-only Batch 677 report commit.');
$assert(($actual['run_id'] ?? 0) === 38644357385, 'Latest exact-commit GitHub Actions run was not selected.');
$assert(($actual['relation'] ?? '') === 'EXACT', 'Exact-commit browser evidence was not distinguished.');
$assert(($batch677['status'] ?? '') === 'PASS'
    && ($batch677['run_id'] ?? 0) === 38641793846
    && ($batch677['application_commit'] ?? '') === 'e8deef80e2aa658c7b7b5054a19247f79871fe58'
    && ($batch677['relation'] ?? '') === 'DOCUMENTATION_ONLY_DESCENDANT', 'Batch 677 browser evidence was not preserved across its documentation-only descendant.');
$assert(($actual['records_count'] ?? 0) === count($registry['records']), 'Historical registry records were dropped.');
$assert(BrowserEvidenceService::classifyRelation(true, ['docs/batches/batch-677.md']) === 'DOCUMENTATION_ONLY_DESCENDANT', 'Documentation-only relation was rejected.');
$assert(BrowserEvidenceService::classifyRelation(true, ['app/Controllers/DashboardController.php']) === 'STALE', 'Application code change did not stale earlier browser evidence.');
$assert(BrowserEvidenceService::classifyRelation(false, []) === 'MISMATCH', 'Non-ancestor workflow evidence was accepted.');
$dirtyWorktree = BrowserEvidenceService::evaluate($registry, $verifiedBaseline, $root, ['app/Views/dashboard/index.php']);
$assert(($dirtyWorktree['status'] ?? '') === 'UNVERIFIED'
    && ($dirtyWorktree['relation'] ?? '') === 'DIRTY_WORKTREE', 'Committed browser evidence was allowed to certify uncommitted application changes.');
$assert(BrowserEvidenceService::applicationAffectingPaths([
    'docs/batches/batch-678.md',
    'storage/attempts.json',
    'storage/question-statistics.json',
    'storage/recovery/previous-report.json',
    'public/assets/me/gear-train.svg',
    '.commandcode/batch.log',
    'tools/Batch596Author.php',
], $root) === [], 'Unrelated documentation, learner, recovery, asset, and utility files should not invalidate committed application evidence.');
$assert(BrowserEvidenceService::applicationAffectingPaths(['storage/study-materials.json'], $root) === ['storage/study-materials.json'], 'Uncommitted educational content did not invalidate committed browser evidence.');

$questionFixture = sys_get_temp_dir() . '/boardprep-browser-question-state-' . bin2hex(random_bytes(6));
mkdir($questionFixture . '/storage', 0700, true);
$fixtureGit = static function (array $args) use ($questionFixture): array {
    $command = 'git -C ' . escapeshellarg($questionFixture) . ' '
        . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    $output = [];
    exec($command, $output, $exit);
    return [$exit, implode("\n", $output)];
};
[$gitInit] = $fixtureGit(['init', '-q']);
if ($gitInit !== 0) {
    throw new RuntimeException('Unable to create the temporary Browser evidence fixture repository.');
}
$baselineQuestions = [[
    'id' => 1,
    'question' => 'Fixture question',
    'answer' => 'A',
    'concept' => 'fixture',
]];
file_put_contents($questionFixture . '/storage/questions.json', json_encode($baselineQuestions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
[$addResult] = $fixtureGit(['add', 'storage/questions.json']);
[$commitResult, $commitOutput] = $fixtureGit([
    '-c', 'user.name=Batch 678 test',
    '-c', 'user.email=batch678@example.invalid',
    'commit', '-qm', 'question baseline',
]);
if ($addResult !== 0 || $commitResult !== 0) {
    throw new RuntimeException('Unable to commit the temporary Browser evidence fixture: ' . $commitOutput);
}
$learnerState = $baselineQuestions;
$learnerState[0]['timesUsed'] = 4;
$learnerState[0]['timesCorrect'] = 3;
$learnerState[0]['timesIncorrect'] = 1;
$learnerState[0]['updatedAt'] = '2026-10-15';
file_put_contents($questionFixture . '/storage/questions.json', json_encode($learnerState, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
$assert(BrowserEvidenceService::applicationAffectingPaths(['storage/questions.json'], $questionFixture) === [], 'Learner usage counters incorrectly invalidated committed browser evidence.');
$learnerState[0]['question'] = 'Changed uncommitted question text';
file_put_contents($questionFixture . '/storage/questions.json', json_encode($learnerState, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
$assert(BrowserEvidenceService::applicationAffectingPaths(['storage/questions.json'], $questionFixture) === ['storage/questions.json'], 'Canonical question content changes were incorrectly ignored as learner counters.');
$fixtureIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($questionFixture, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($fixtureIterator as $entry) {
    if ($entry->isDir()) {
        rmdir($entry->getPathname());
    } else {
        unlink($entry->getPathname());
    }
}
rmdir($questionFixture);

$record = $batch677Records[0];
$unverified = $record;
$unverified['verification_status'] = 'UNVERIFIED';
$assert(BrowserEvidenceService::validateRecord($unverified)['valid'] === false, 'A manually asserted PASS without GitHub verification was accepted.');

$failedJob = $record;
$failedJob['jobs'][0]['conclusion'] = 'failure';
$assert(BrowserEvidenceService::validateRecord($failedJob)['passed'] === false, 'A successful workflow with a failed job was treated as PASS.');

$newerExactFailure = $record;
$newerExactFailure['run_id'] = 99999999999;
$newerExactFailure['head_sha'] = $verifiedBaseline;
$newerExactFailure['workflow_conclusion'] = 'failure';
$failure = BrowserEvidenceService::evaluate(['schema_version' => 1, 'records' => [$record, $newerExactFailure]], $verifiedBaseline, $root);
$assert(($failure['status'] ?? '') === 'FAIL', 'An exact-commit failure did not supersede older passing documentation-ancestor evidence.');

echo "[PASS] Browser workflow registration validates GitHub conclusions and commit ancestry.\n";
echo "[PASS] Exact failures, stale code, mismatches, unverified claims, and historical records are distinguished.\n";
