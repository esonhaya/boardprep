<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Operations\BrowserEvidenceService;

$root = dirname(__DIR__, 2);
$registry = json_decode((string) file_get_contents($root . '/docs/release-evidence/browser-workflows.json'), true, 512, JSON_THROW_ON_ERROR);
$registrationSource = (string) file_get_contents($root . '/tools/register-browser-evidence.php');
$releaseWorkflowSource = (string) file_get_contents($root . '/.github/workflows/release-gates.yml');
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
$assert(str_contains($registrationSource, 'extension_loaded(\'curl\')')
    && str_contains($registrationSource, 'CURLOPT_SSL_VERIFYPEER => true')
    && str_contains($registrationSource, 'stream_context_create')
    && !str_contains($registrationSource, 'curl_close('), 'GitHub API registration must use verified TLS, retain a stream fallback, and avoid deprecated cURL cleanup.');
$doctorJobSource = explode("  security-privacy:", explode("  doctor-release:", $releaseWorkflowSource, 2)[1] ?? '', 2)[0] ?? '';
$assert(str_contains($doctorJobSource, 'fetch-depth: 0')
    && substr_count($releaseWorkflowSource, 'docs/release-evidence/browser-workflows.json') === 2,
    'The Doctor job must fetch full history, and browser evidence updates must rerun release gates.');
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

// Reproduce the Doctor job's checkout boundary: full history can prove a
// documentation-only descendant, while depth-1 history cannot certify it.
$historyRoot = sys_get_temp_dir() . '/boardprep-browser-history-' . bin2hex(random_bytes(6));
if (!mkdir($historyRoot, 0700, true)) {
    throw new RuntimeException('Unable to create a bounded Browser history fixture.');
}
$historyGit = static function (array $arguments, ?string $directory = null): array {
    $command = 'git ';
    if ($directory !== null) {
        $command .= '-C ' . escapeshellarg($directory) . ' ';
    }
    $command .= implode(' ', array_map('escapeshellarg', $arguments));
    $output = [];
    exec($command . ' 2>&1', $output, $exit);
    return [$exit, implode("\n", $output)];
};
$historyRun = static function (array $arguments, ?string $directory = null) use ($historyGit): string {
    [$exit, $output] = $historyGit($arguments, $directory);
    if ($exit !== 0) {
        throw new RuntimeException('Browser history fixture command failed: ' . $output);
    }
    return $output;
};
try {
    $sourceRepository = $historyRoot . '/source';
    $fullRepository = $historyRoot . '/full';
    $shallowRepository = $historyRoot . '/shallow';
    mkdir($sourceRepository, 0700);
    $historyRun(['init', '-q'], $sourceRepository);
    $historyRun(['config', 'user.name', 'BoardPrep Browser Fixture'], $sourceRepository);
    $historyRun(['config', 'user.email', 'browser-fixture@example.invalid'], $sourceRepository);
    file_put_contents($sourceRepository . '/app.php', "<?php // baseline application\n");
    $historyRun(['add', 'app.php'], $sourceRepository);
    $historyRun(['-c', 'user.name=BoardPrep Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-qm', 'application baseline'], $sourceRepository);
    $historyBaseline = $historyRun(['rev-parse', 'HEAD'], $sourceRepository);

    mkdir($sourceRepository . '/docs', 0700);
    file_put_contents($sourceRepository . '/docs/batch-report.md', "Documentation-only descendant.\n");
    $historyRun(['add', 'docs/batch-report.md'], $sourceRepository);
    $historyRun(['-c', 'user.name=BoardPrep Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-qm', 'documentation descendant'], $sourceRepository);
    $historyHead = $historyRun(['rev-parse', 'HEAD'], $sourceRepository);

    $historyUrl = 'file://' . $sourceRepository;
    $historyRun(['clone', '--quiet', '--no-tags', $historyUrl, $fullRepository]);
    $historyRun(['clone', '--quiet', '--depth', '1', '--no-tags', $historyUrl, $shallowRepository]);
    $fullHead = $historyRun(['rev-parse', 'HEAD'], $fullRepository);
    $shallowHead = $historyRun(['rev-parse', 'HEAD'], $shallowRepository);
    $historyRecord = $record;
    $historyRecord['head_sha'] = trim($historyBaseline);
    $fullHistoryResult = BrowserEvidenceService::evaluate(
        ['schema_version' => 1, 'records' => [$historyRecord]],
        trim($fullHead),
        $fullRepository
    );
    $shallowHistoryResult = BrowserEvidenceService::evaluate(
        ['schema_version' => 1, 'records' => [$historyRecord]],
        trim($shallowHead),
        $shallowRepository
    );
    $assert(trim($fullHead) === trim($historyHead)
        && ($fullHistoryResult['status'] ?? '') === 'PASS'
        && ($fullHistoryResult['relation'] ?? '') === 'DOCUMENTATION_ONLY_DESCENDANT',
        'Complete Git history must preserve verified documentation-only descendant evidence.');
    $assert(($shallowHistoryResult['status'] ?? '') !== 'PASS'
        && ($shallowHistoryResult['relation'] ?? '') === 'MISMATCH',
        'A shallow checkout missing the evidence commit must fail closed instead of certifying a descendant.');
} finally {
    $historyIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($historyRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($historyIterator as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($historyRoot);
}

echo "[PASS] Browser workflow registration validates GitHub conclusions and commit ancestry.\n";
echo "[PASS] Exact failures, stale code, mismatches, unverified claims, and historical records are distinguished.\n";
echo "[PASS] Full history preserves documentation descendants; shallow history fails closed.\n";
