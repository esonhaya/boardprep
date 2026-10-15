#!/usr/bin/env php
<?php

declare(strict_types=1);

// Packaging validates and serializes the full curated bank. Keep the
// repository-native release command independent of a small CLI default.
@ini_set('memory_limit', '1024M');

/**
 * Build a production-only BoardPrep package from committed Git state.
 *
 * The working tree is intentionally never copied.  Git archive is the
 * source of truth so dirty files and untracked local assets cannot enter a
 * release accidentally.
 */

$root = dirname(__DIR__);
$releaseConfig = is_file($root . '/config/release.php') ? (array) require $root . '/config/release.php' : [];
$productVersion = (string) ($releaseConfig['product_version'] ?? '1.0.0');
$releaseTag = (string) ($releaseConfig['release_tag'] ?? 'v1.0.0');
$releaseName = $releaseTag !== '' ? $releaseTag : 'v' . $productVersion;
$outputArgument = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--output=')) {
        $outputArgument = substr($argument, 9);
        continue;
    }
    fwrite(STDERR, "Unknown release option: {$argument}\n");
    exit(2);
}

function releaseFail(string $message): never
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function releaseRemoveTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
}

function releaseFiles(string $source, array $files, array $directories): array
{
    $paths = $files;
    foreach ($directories as $directory) {
        $base = $source . '/' . trim($directory, '/');
        if (!is_dir($base)) {
            releaseFail("Required release directory is missing: {$directory}");
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $paths[] = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($source))), '/');
            }
        }
    }
    $paths = array_values(array_unique($paths));
    sort($paths, SORT_STRING);
    foreach ($paths as $path) {
        if (!is_file($source . '/' . $path)) {
            releaseFail("Required release file is missing: {$path}");
        }
    }
    return $paths;
}

$head = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
$shortHead = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD'));
$commitDate = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' show -s --format=%cI HEAD'));
if (!preg_match('/^[0-9a-f]{40}$/', $head) || !preg_match('/^[0-9a-f]+$/', $shortHead)) {
    releaseFail('Unable to resolve the committed Git source tree.');
}

$output = $outputArgument ?? "BoardPrep_v{$productVersion}_{$shortHead}.zip";
if (!str_starts_with($output, '/')) {
    $output = getcwd() . '/' . $output;
}
$output = str_replace('\\', '/', $output);
if (file_exists($output)) {
    releaseFail("Refusing to overwrite existing package: {$output}");
}
if (!is_dir(dirname($output)) && !mkdir(dirname($output), 0777, true) && !is_dir(dirname($output))) {
    releaseFail('Unable to create package output directory.');
}

$packageFiles = [
    '.env.example', 'LICENSE', 'README.md',
    'docs/Batch444_Configuration_Runtime_Readiness.md',
    'docs/Batch449_Deployment_Production_Readiness.md',
    'docs/MVP_RELEASE_SCOPE.md', 'docs/RELEASE_CHECKLIST_v0.1.0.md',
    'docs/RELEASE_NOTES_v0.1.0.md', 'docs/RELEASE_NOTES_V1.0.1.md',
    'docs/RELEASE_RUNBOOK_V1.md', 'docs/POST_V1_BASELINE.md',
    'docs/PRODUCTION_HANDOFF_V1.0.1.md', 'tools/doctor.php',
    'tools/backup.php', 'tools/restore.php', 'tools/release.php', 'tools/db-migrate.php',
];
$packageDirectories = ['app', 'bootstrap', 'config', 'database/migrations', 'public', 'routes', 'tools/Doctor'];
$curatedStorage = [
    'storage/board-subjects.json', 'storage/boards.json', 'storage/blueprints.json',
    'storage/flashcard-exclusions.json', 'storage/let-specializations.json',
    'storage/exam-intelligence.json', 'storage/board-concept-coverage.json',
    'storage/concept-equivalence.json', 'storage/concept-derived-eligibility.json',
    'storage/organizations/organizations.json', 'storage/question-eligibility.json',
    'storage/question-required-concepts.json', 'storage/question-reuse-metadata.json',
    'storage/question-reuse-overrides.json', 'storage/questions.json', 'storage/sources.json',
    'storage/study-materials.json', 'storage/study-question-links.json', 'storage/subjects.json',
    'storage/taxonomy/concepts.json', 'storage/taxonomy/domains.json', 'storage/taxonomy/topics.json',
];

$temporary = sys_get_temp_dir() . '/boardprep-release-' . bin2hex(random_bytes(6));
$source = $temporary . '/tree';
if (!mkdir($temporary, 0777, true) || !mkdir($source, 0777, true)) {
    releaseFail('Unable to create the isolated package workspace.');
}

try {
    $prefix = "BoardPrep_v{$productVersion}_{$shortHead}/";
    $archivePaths = array_merge($packageFiles, $packageDirectories, $curatedStorage);
    $archiveCommand = array_merge(
        ['git', '-C', $root, 'archive', '--format=tar', '--prefix=' . $prefix, 'HEAD', '--'],
        $archivePaths
    );
    $archiveDescriptor = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $archiveProcess = proc_open($archiveCommand, $archiveDescriptor, $archivePipes, $root);
    if (!is_resource($archiveProcess)) {
        releaseFail('Unable to export the committed release files.');
    }
    $tarDescriptor = [
        0 => ['pipe', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['pipe', 'w'],
    ];
    $tarProcess = proc_open(['tar', '-xf', '-', '-C', $source], $tarDescriptor, $tarPipes, $root);
    if (!is_resource($tarProcess)) {
        @proc_terminate($archiveProcess);
        fclose($archivePipes[1]);
        $archiveError = stream_get_contents($archivePipes[2]) ?: '';
        fclose($archivePipes[2]);
        proc_close($archiveProcess);
        releaseFail(trim($archiveError) !== '' ? trim($archiveError) : 'Unable to start the release archive extractor.');
    }
    $streamedBytes = @stream_copy_to_stream($archivePipes[1], $tarPipes[0]);
    fclose($archivePipes[1]);
    fclose($tarPipes[0]);
    $archiveError = stream_get_contents($archivePipes[2]) ?: '';
    $tarError = stream_get_contents($tarPipes[2]) ?: '';
    fclose($archivePipes[2]);
    fclose($tarPipes[2]);
    $archiveExit = proc_close($archiveProcess);
    $tarExit = proc_close($tarProcess);
    if ($streamedBytes === false || $archiveExit !== 0 || $tarExit !== 0) {
        $detail = trim($archiveError . "\n" . $tarError);
        releaseFail($detail !== '' ? $detail : 'Unable to stream committed release files into the package workspace.');
    }
    $tree = $source . '/BoardPrep_v' . $productVersion . '_' . $shortHead;

    $includedFiles = releaseFiles($tree, $packageFiles, $packageDirectories);
    $includedFiles = releaseFiles(
        $tree,
        $includedFiles,
        []
    );

    foreach ($curatedStorage as $path) {
        if (!is_file($tree . '/' . $path)) {
            releaseFail("Required curated storage file is missing: {$path}");
        }
        $includedFiles[] = $path;
    }
    $includedFiles = array_values(array_unique($includedFiles));
    sort($includedFiles, SORT_STRING);

    $questions = json_decode((string) file_get_contents($tree . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
    $boards = json_decode((string) file_get_contents($tree . '/storage/boards.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = [
        'product' => 'BoardPrep',
        'release' => $releaseName,
        'product_version' => $productVersion,
        'release_tag' => $releaseTag,
        'release_commit' => $head,
        'build_commit' => $head,
        'release_commit_short' => $shortHead,
        'source' => 'git committed tree',
        'source_commit_date' => $commitDate,
        'supported_release_profiles' => ['JSON', 'SQLite'],
        'mysql_release_status' => 'NOT_CLAIMED',
        'canonical_questions' => count(is_array($questions) ? $questions : []),
        'registered_boards' => count(is_array($boards) ? $boards : []),
        'included_files' => array_merge($includedFiles, ['RELEASE_MANIFEST.json']),
    ];
    $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

    $zip = new ZipArchive();
    if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        releaseFail("Unable to create package: {$output}");
    }
    $mtime = $commitDate !== '' ? strtotime($commitDate) : 0;
    foreach ($includedFiles as $path) {
        $contents = file_get_contents($tree . '/' . $path);
        if ($contents === false || !$zip->addFromString($prefix . $path, $contents)) {
            $zip->close();
            releaseFail("Unable to add package file: {$path}");
        }
        $zip->setMtimeName($prefix . $path, $mtime ?: 0);
        $zip->setCompressionName($prefix . $path, ZipArchive::CM_DEFLATE, 9);
    }
    $manifestPath = $prefix . 'RELEASE_MANIFEST.json';
    $zip->addFromString($manifestPath, $manifestJson);
    $zip->setMtimeName($manifestPath, $mtime ?: 0);
    $zip->setCompressionName($manifestPath, ZipArchive::CM_DEFLATE, 9);
    if (!$zip->close()) {
        releaseFail('Unable to finalize package archive.');
    }

    printf("PACKAGE=%s\nPRODUCT_VERSION=%s\nCOMMIT=%s\nFILES=%d\nQUESTIONS=%d\nBOARDS=%d\n", $output, $productVersion, $shortHead, count($manifest['included_files']), $manifest['canonical_questions'], $manifest['registered_boards']);
} finally {
    releaseRemoveTree($temporary);
}
