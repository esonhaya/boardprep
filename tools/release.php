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
    if (str_starts_with($argument, '--evidence=')) {
        $evidenceArgument = substr($argument, 11);
        continue;
    }
    fwrite(STDERR, "Unknown release option: {$argument}\nUsage: php tools/release.php [--output=/path/package.zip] [--evidence=/path/build-evidence.json]\n");
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
$evidenceOutput = $evidenceArgument ?? ($output . '.evidence.json');
if (!str_starts_with($evidenceOutput, '/')) $evidenceOutput = getcwd() . '/' . $evidenceOutput;
$evidenceOutput = str_replace('\\', '/', $evidenceOutput);
if ($evidenceOutput === $output) releaseFail('Package and build evidence must use separate paths.');
if (file_exists($evidenceOutput)) releaseFail("Refusing to overwrite existing package evidence: {$evidenceOutput}");
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
    'docs/PRODUCTION_HANDOFF_V1.0.1.md', 'tools/doctor.php', 'tools/generate-study-pdfs.php',
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
    'storage/study-materials.json', 'storage/study-lessons.json', 'storage/study-question-links.json', 'storage/subjects.json',
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
    $lessons = json_decode((string) file_get_contents($tree . '/storage/study-lessons.json'), true, 512, JSON_THROW_ON_ERROR);
    $pdfManifestPath = $tree . '/public/study-resources/manifest.json';
    $pdfManifest = json_decode((string) file_get_contents($pdfManifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($questions) || !is_array($boards) || !is_array($lessons) || !is_array($pdfManifest['files'] ?? null)) {
        releaseFail('Canonical content or study PDF manifest is malformed.');
    }

    $lessonsById = [];
    $expectedPdfLessons = [];
    foreach ($lessons as $lesson) {
        if (!is_array($lesson) || !is_scalar($lesson['id'] ?? null)) continue;
        $lessonId = (string) $lesson['id'];
        $lessonsById[$lessonId] = $lesson;
        if (is_scalar($lesson['pdf_file'] ?? null) && trim((string) $lesson['pdf_file']) !== '') $expectedPdfLessons[$lessonId] = true;
    }

    $studyPdfs = [];
    $seenPdfLessons = [];
    foreach ($pdfManifest['files'] as $entry) {
        if (!is_array($entry)) releaseFail('Study PDF manifest contains a malformed entry.');
        $lessonId = is_scalar($entry['lesson_id'] ?? null) ? (string) $entry['lesson_id'] : '';
        $relativePath = is_scalar($entry['path'] ?? null) ? (string) $entry['path'] : '';
        $expectedHash = is_string($entry['sha256'] ?? null) ? strtolower($entry['sha256']) : '';
        $lesson = $lessonsById[$lessonId] ?? null;
        if (!is_array($lesson) || !isset($expectedPdfLessons[$lessonId]) || isset($seenPdfLessons[$lessonId])
            || !preg_match('#\\Apublic/study-resources/[A-Za-z0-9._-]+\\.pdf\\z#', $relativePath)
            || basename($relativePath) !== (string) ($lesson['pdf_file'] ?? '')
            || !preg_match('/\\A[a-f0-9]{64}\\z/', $expectedHash)) {
            releaseFail('Study PDF manifest does not map uniquely to a known lesson resource.');
        }
        $pdfPath = $tree . '/' . $relativePath;
        if (!is_file($pdfPath) || !hash_equals($expectedHash, (string) hash_file('sha256', $pdfPath))) {
            releaseFail("Study PDF failed its committed manifest hash: {$relativePath}");
        }
        $seenPdfLessons[$lessonId] = true;
        $studyPdfs[] = ['lesson_id' => $lessonId, 'path' => $relativePath, 'sha256' => $expectedHash, 'bytes' => filesize($pdfPath) ?: 0];
    }
    if (array_diff_key($expectedPdfLessons, $seenPdfLessons) !== []) releaseFail('One or more lessons with a PDF are missing from the committed PDF manifest.');

    $staticAssets = [];
    foreach ($includedFiles as $path) {
        if (!str_starts_with($path, 'public/assets/')) continue;
        $assetPath = $tree . '/' . $path;
        $staticAssets[] = ['path' => $path, 'sha256' => hash_file('sha256', $assetPath) ?: '', 'bytes' => filesize($assetPath) ?: 0];
    }
    $supportedBoards = [];
    foreach ($boards as $board) {
        if (!is_array($board) || !is_scalar($board['id'] ?? null)) continue;
        $supportedBoards[] = ['id' => (string) $board['id'], 'code' => (string) ($board['code'] ?? ''), 'name' => (string) ($board['name'] ?? '')];
    }

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
        'canonical_questions_sha256' => hash_file('sha256', $tree . '/storage/questions.json'),
        'registered_boards' => count(is_array($boards) ? $boards : []),
        'supported_boards' => $supportedBoards,
        'study_lessons' => [
            'count' => count($lessonsById),
            'sha256' => hash_file('sha256', $tree . '/storage/study-lessons.json'),
        ],
        'study_pdfs' => [
            'count' => count($studyPdfs),
            'manifest_sha256' => hash_file('sha256', $pdfManifestPath),
            'files' => $studyPdfs,
        ],
        'static_assets' => $staticAssets,
        'package_validation' => [
            'canonical_content' => 'STRUCTURE_PARSED_NOT_SEMANTICALLY_AUDITED',
            'study_pdf_integrity' => 'PASS',
            'static_asset_hashes' => 'PASS',
            'deployment_status' => 'NOT_DEPLOYED',
            'product_readiness' => 'NOT_EVALUATED_BY_PACKAGE_BUILDER',
        ],
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

    $buildEvidence = [
        'schema_version' => 1,
        'generated_at' => gmdate(DATE_ATOM),
        'source_sha' => $head,
        'build_sha' => $head,
        'source_commit_timestamp' => $commitDate,
        'package' => [
            'filename' => basename($output),
            'bytes' => filesize($output) ?: 0,
            'sha256' => hash_file('sha256', $output),
            'release_manifest_sha256' => hash('sha256', $manifestJson),
        ],
        'inventory' => [
            'canonical_questions' => $manifest['canonical_questions'],
            'canonical_questions_sha256' => $manifest['canonical_questions_sha256'],
            'supported_boards' => $manifest['supported_boards'],
            'study_lessons' => $manifest['study_lessons'],
            'study_pdfs' => ['count' => $manifest['study_pdfs']['count'], 'manifest_sha256' => $manifest['study_pdfs']['manifest_sha256']],
            'static_asset_count' => count($staticAssets),
        ],
        'environment_classification' => 'LOCAL_PACKAGE_BUILD',
        'deployment_status' => 'NOT_DEPLOYED',
        'hosted_verification' => 'NOT_RUN_BY_PACKAGE_BUILDER',
    ];
    $evidenceJson = json_encode($buildEvidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $evidenceDirectory = dirname($evidenceOutput);
    if (!is_dir($evidenceDirectory) && !mkdir($evidenceDirectory, 0777, true) && !is_dir($evidenceDirectory)) releaseFail('Unable to create package evidence directory.');
    $evidenceTemporary = $evidenceOutput . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($evidenceTemporary, $evidenceJson, LOCK_EX) === false || !rename($evidenceTemporary, $evidenceOutput)) {
        @unlink($evidenceTemporary);
        releaseFail('Unable to write package build evidence.');
    }

    printf("PACKAGE=%s\nPRODUCT_VERSION=%s\nCOMMIT=%s\nFILES=%d\nQUESTIONS=%d\nBOARDS=%d\n", $output, $productVersion, $shortHead, count($manifest['included_files']), $manifest['canonical_questions'], $manifest['registered_boards']);
    printf("BUILD_EVIDENCE=%s\nPACKAGE_SHA256=%s\nBUILT_AT=%s\n", $evidenceOutput, $buildEvidence['package']['sha256'], $buildEvidence['generated_at']);
} finally {
    releaseRemoveTree($temporary);
}
