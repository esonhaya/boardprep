<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/TemporaryTestArtifactCleanup.php';

use Tools\Doctor\Project\BoardPrep\Simulation\TemporaryTestArtifactCleanup;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$removeTree = static function (string $path): void {
    if (!is_dir($path) || is_link($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($path);
};

$temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . '/boardprep-artifact-cleanup-test-' . bin2hex(random_bytes(8));
if (!mkdir($temporaryRoot, 0700, true)) {
    throw new RuntimeException('Unable to create isolated cleanup test root.');
}

$oldFixture = $temporaryRoot . '/boardprep-release-test-storage-' . bin2hex(random_bytes(6));
$oldSessions = $temporaryRoot . '/boardprep-release-sessions-' . bin2hex(random_bytes(16));
$recentFixture = $temporaryRoot . '/boardprep-release-test-storage-' . bin2hex(random_bytes(6));
$unknownPath = $temporaryRoot . '/boardprep-simulator-preserved-diagnostic';
$activeSessions = $temporaryRoot . '/boardprep-release-sessions-' . bin2hex(random_bytes(16));
$linkedFixture = $temporaryRoot . '/boardprep-release-test-storage-' . bin2hex(random_bytes(6));
$activeProcess = null;
$passed = false;

try {
    $escapedRootRejected = false;
    try {
        TemporaryTestArtifactCleanup::inventory(dirname(__DIR__, 2));
    } catch (RuntimeException) {
        $escapedRootRejected = true;
    }
    $assert($escapedRootRejected, 'Cleanup accepted a root outside the system temporary directory.');

    mkdir($oldFixture, 0700, true);
    file_put_contents($oldFixture . '/questions.json', "[]\n");
    file_put_contents($oldFixture . '/boards.json', "[]\n");
    mkdir($oldSessions, 0700, true);
    file_put_contents($oldSessions . '/sess_test', 'synthetic-session');
    mkdir($recentFixture, 0700, true);
    file_put_contents($recentFixture . '/questions.json', "[]\n");
    file_put_contents($recentFixture . '/boards.json', "[]\n");
    mkdir($unknownPath, 0700, true);
    file_put_contents($unknownPath . '/keep.txt', 'preserve');
    mkdir($activeSessions, 0700, true);
    $activeFile = $activeSessions . '/sess_active';
    file_put_contents($activeFile, 'synthetic-session');
    mkdir($linkedFixture, 0700, true);
    file_put_contents($linkedFixture . '/questions.json', "[]\n");
    file_put_contents($linkedFixture . '/boards.json', "[]\n");
    file_put_contents($unknownPath . '/private-source.txt', 'preserve');
    if (!symlink($unknownPath . '/private-source.txt', $linkedFixture . '/external-link')) {
        throw new RuntimeException('Unable to create the symlink safety fixture.');
    }

    $old = time() - TemporaryTestArtifactCleanup::MINIMUM_AGE_SECONDS - 60;
    foreach ([$oldFixture, $oldSessions, $activeSessions, $linkedFixture] as $path) {
        touch($path, $old);
    }
    touch($recentFixture, time());

    $holderCode = '$handle=fopen($argv[1], "rb"); echo "READY\\n"; flush(); sleep(30);';
    $activeProcess = proc_open(
        [PHP_BINARY, '-r', $holderCode, $activeFile],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($activeProcess)) {
        throw new RuntimeException('Unable to start the active-path safety child.');
    }
    stream_set_timeout($pipes[1], 3);
    $ready = fgets($pipes[1]);
    if ($ready !== "READY\n") {
        throw new RuntimeException('Active-path safety child did not open its fixture.');
    }
    fclose($pipes[1]);
    fclose($pipes[2]);

    $inventory = TemporaryTestArtifactCleanup::inventory($temporaryRoot);
    $byPath = [];
    foreach ($inventory as $entry) {
        $byPath[$entry['path']] = $entry;
    }
    $assert(($byPath[$oldFixture]['status'] ?? '') === 'SAFE_REGENERABLE', 'Old release fixture was not classified from its verified namespace and structure.');
    $assert(($byPath[$oldSessions]['status'] ?? '') === 'SAFE_REGENERABLE', 'Old release session tree was not classified as stale and unreferenced.');
    $assert(($byPath[$recentFixture]['status'] ?? '') === 'REQUIRES_OWNER_REVIEW', 'Recent fixture was not protected by the stale-age window.');
    $assert(($byPath[$activeSessions]['status'] ?? '') === 'REQUIRES_OWNER_REVIEW', 'Live process reference was not rejected.');
    $assert(($byPath[$linkedFixture]['status'] ?? '') === 'REQUIRES_OWNER_REVIEW', 'A fixture containing a symlink was not rejected.');
    $assert(!isset($byPath[$unknownPath]), 'A non-allowlisted simulator diagnostic path entered cleanup inventory.');

    $dryRun = TemporaryTestArtifactCleanup::prune($temporaryRoot, false);
    $dryRunPaths = array_column(array_filter($dryRun, static fn(array $entry): bool => ($entry['action'] ?? '') === 'DRY_RUN'), 'path');
    $assert(in_array($oldFixture, $dryRunPaths, true) && is_dir($oldFixture), 'Dry run did not report eligible paths without deleting them.');

    $results = TemporaryTestArtifactCleanup::prune($temporaryRoot, true);
    $removed = array_column(array_filter($results, static fn(array $entry): bool => ($entry['action'] ?? '') === 'REMOVED'), 'path');
    $assert(in_array($oldFixture, $removed, true) && in_array($oldSessions, $removed, true), 'Explicit apply did not remove eligible stale fixtures.');
    $assert(is_dir($recentFixture) && is_dir($unknownPath) && is_dir($activeSessions) && is_dir($linkedFixture), 'Cleanup removed a recent, unknown, active or linked path.');
    $assert(!is_link($temporaryRoot) && is_file($unknownPath . '/keep.txt'), 'Cleanup crossed a path boundary or modified the preserved diagnostic tree.');
    $assert(is_file($unknownPath . '/private-source.txt'), 'Cleanup followed a symlink outside its candidate tree.');

    proc_terminate($activeProcess);
    proc_close($activeProcess);
    $activeProcess = null;

    echo "[PASS] Temporary test artifact cleanup: stale exact namespaces removed; recent, active, unknown and diagnostic paths preserved.\n";
    $passed = true;
} finally {
    if (is_resource($activeProcess)) {
        @proc_terminate($activeProcess);
        @proc_close($activeProcess);
    }
    if ($passed) {
        $removeTree($temporaryRoot);
    } else {
        fwrite(STDERR, 'TEST_FIXTURE_PRESERVED=' . $temporaryRoot . PHP_EOL);
    }
}
