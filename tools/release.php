#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Build a production-only RC package from committed Git state.
 *
 * The working tree is intentionally never copied.  Git archive is the
 * source of truth so dirty files and untracked local assets cannot enter a
 * release accidentally.
 */

const RELEASE = 'RC1.1';

$root = dirname(__DIR__);
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

function releaseCommand(string $command, string $workingDirectory, ?string $stderr = null): void
{
    $descriptor = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => $stderr === null ? ['pipe', 'w'] : ['file', $stderr, 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, $workingDirectory);
    if (!is_resource($process)) {
        releaseFail('Unable to start packaging command.');
    }
    $error = '';
    if (isset($pipes[2])) {
        $error = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
    }
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        releaseFail(trim($error) !== '' ? trim($error) : "Packaging command failed: {$command}");
    }
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

$output = $outputArgument ?? "BoardPrep_RC1.1_{$shortHead}.zip";
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

$temporary = sys_get_temp_dir() . '/boardprep-release-' . bin2hex(random_bytes(6));
$source = $temporary . '/tree';
$tar = $temporary . '/source.tar';
if (!mkdir($temporary, 0777, true) || !mkdir($source, 0777, true)) {
    releaseFail('Unable to create the isolated package workspace.');
}

try {
    $prefix = "BoardPrep_RC1.1_{$shortHead}/";
    $archiveCommand = 'git -C ' . escapeshellarg($root)
        . ' archive --format=tar --prefix=' . escapeshellarg($prefix) . ' HEAD';
    $descriptor = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', $tar, 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($archiveCommand, $descriptor, $pipes, $root);
    if (!is_resource($process)) {
        releaseFail('Unable to export the committed Git tree.');
    }
    $error = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        releaseFail(trim($error) !== '' ? trim($error) : 'Git archive failed.');
    }
    releaseCommand('tar -xf ' . escapeshellarg($tar) . ' -C ' . escapeshellarg($source), $source);
    $tree = $source . '/BoardPrep_RC1.1_' . $shortHead;

    $includedFiles = releaseFiles(
        $tree,
        [
            '.env.example',
            'LICENSE',
            'README.md',
            'docs/Batch444_Configuration_Runtime_Readiness.md',
            'docs/Batch449_Deployment_Production_Readiness.md',
            'docs/MVP_RELEASE_SCOPE.md',
            'docs/RELEASE_CHECKLIST_v0.1.0.md',
            'docs/RELEASE_NOTES_v0.1.0.md',
            'tools/db-migrate.php',
        ],
        [
            'app',
            'bootstrap',
            'config',
            'database/migrations',
            'public',
            'routes',
        ]
    );
    $includedFiles = releaseFiles(
        $tree,
        $includedFiles,
        []
    );

    $curatedStorage = [
        'storage/board-subjects.json',
        'storage/boards.json',
        'storage/blueprints.json',
        'storage/let-specializations.json',
        'storage/exam-intelligence.json',
        'storage/board-concept-coverage.json',
        'storage/concept-equivalence.json',
        'storage/concept-derived-eligibility.json',
        'storage/organizations/organizations.json',
        'storage/question-eligibility.json',
        'storage/question-required-concepts.json',
        'storage/question-reuse-metadata.json',
        'storage/question-reuse-overrides.json',
        'storage/questions.json',
        'storage/sources.json',
        'storage/study-materials.json',
        'storage/study-question-links.json',
        'storage/subjects.json',
        'storage/taxonomy/concepts.json',
        'storage/taxonomy/domains.json',
        'storage/taxonomy/topics.json',
    ];
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
        'release' => RELEASE,
        'release_commit' => $head,
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

    printf("PACKAGE=%s\nCOMMIT=%s\nFILES=%d\nQUESTIONS=%d\nBOARDS=%d\n", $output, $shortHead, count($manifest['included_files']), $manifest['canonical_questions'], $manifest['registered_boards']);
} finally {
    releaseRemoveTree($temporary);
}
