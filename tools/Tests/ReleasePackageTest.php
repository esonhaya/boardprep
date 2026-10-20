<?php

declare(strict_types=1);

/**
 * Release-package contract and reproducibility regression.
 *
 * This test intentionally invokes the production package builder, then runs
 * integrity validation from the extracted archive rather than the checkout.
 */

$root = dirname(__DIR__, 2);
require_once $root . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();
$temporaryRoot = trim((string) (getenv('APP_SESSION_PATH') ?: ''));
if ($temporaryRoot === '' || !is_dir($temporaryRoot)) $temporaryRoot = sys_get_temp_dir();
$temporary = rtrim($temporaryRoot, DIRECTORY_SEPARATOR) . '/boardprep-release-test-' . bin2hex(random_bytes(4));
$packageOne = $temporary . '/build-one.zip';
$evidenceOne = $packageOne . '.evidence.json';
$packageTwo = $temporary . '/build-two.zip';

$removeTree = static function (string $directory) use (&$removeTree): void {
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
};

$run = static function (string $command, string $workingDirectory, ?array $environment = null): string {
    $descriptor = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, $workingDirectory, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start release command.');
    }
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException("Release command failed ({$exitCode}): {$stdout}\n{$stderr}");
    }
    return $stdout;
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

if (!mkdir($temporary, 0777, true)) {
    throw new RuntimeException('Unable to create release test workspace.');
}

try {
    $releaseTool = $root . '/tools/release.php';
    $php = escapeshellarg(PHP_BINARY) . ' -d memory_limit=1024M';
    $tool = escapeshellarg($releaseTool);
    $inspectPackage = static function (string $path) use ($assert): array {
        $sha = hash_file('sha256', $path);
        $archive = new ZipArchive();
        $assert($archive->open($path) === true, 'release package cannot be opened');
        $entries = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);
            $entries[$name] = hash('sha256', (string) $archive->getFromIndex($index));
        }
        $archive->close();
        ksort($entries, SORT_STRING);
        return [$sha, $entries];
    };

    $run("{$php} {$tool} --output=" . escapeshellarg($packageOne), $root);
    [$shaOne, $entriesOne] = $inspectPackage($packageOne);
    $assert(is_file($evidenceOne), 'release builder did not write its external build-evidence manifest');
    $buildEvidence = json_decode((string) file_get_contents($evidenceOne), true, 512, JSON_THROW_ON_ERROR);
    $assert(($buildEvidence['source_sha'] ?? null) === trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD')), 'build evidence source SHA does not match the committed package tree');
    $assert(($buildEvidence['package']['sha256'] ?? null) === $shaOne, 'build evidence package digest does not match the actual archive');
    $assert(($buildEvidence['package']['bytes'] ?? null) === filesize($packageOne), 'build evidence package byte count does not match the archive');
    $assert(($buildEvidence['deployment_status'] ?? '') === 'NOT_DEPLOYED'
        && ($buildEvidence['hosted_verification'] ?? '') === 'NOT_RUN_BY_PACKAGE_BUILDER', 'package builder overstated deployment or hosted evidence');
    $assert(unlink($packageOne), 'first release package could not be removed after its digest was captured');
    $run("{$php} {$tool} --output=" . escapeshellarg($packageTwo), $root);
    [$shaTwo, $entriesTwo] = $inspectPackage($packageTwo);
    $assert(is_string($shaOne) && $shaOne === $shaTwo, 'repeated package builds differ byte-for-byte');
    $assert($entriesOne === $entriesTwo, 'normalized package inventories differ');

    $packageArchive = new ZipArchive();
    $assert($packageArchive->open($packageTwo) === true, 'verified package cannot be reopened');

    $prefix = array_key_first($entriesOne);
    $assert(is_string($prefix) && preg_match('#^BoardPrep_v1\.0\.1_[^/]+/#', $prefix) === 1, 'package root is not release-scoped');
    $packageRoot = substr($prefix, 0, strpos($prefix, '/') + 1);
    $required = [
        'RELEASE_MANIFEST.json',
        'public/index.php',
        'app/Core/App.php',
        'storage/board-concept-coverage.json',
        'storage/concept-derived-eligibility.json',
        'storage/flashcard-exclusions.json',
        'storage/question-reuse-metadata.json',
        'storage/questions.json',
        'storage/question-eligibility.json',
        'storage/study-materials.json',
        'storage/taxonomy/domains.json',
        'tools/db-migrate.php',
        'tools/doctor.php',
        'tools/backup.php',
        'tools/restore.php',
        'config/deployment-profile.json',
        'docs/RELEASE_RUNBOOK_V1.md',
        'docs/POST_V1_BASELINE.md',
        'docs/RELEASE_NOTES_V1.0.1.md',
        'docs/PRODUCTION_HANDOFF_V1.0.1.md',
    ];
    foreach ($required as $path) {
        $assert(isset($entriesOne[$packageRoot . $path]), "required package file missing: {$path}");
    }
    foreach (array_keys($entriesOne) as $name) {
        $relative = substr($name, strlen($packageRoot));
        $assert(!str_contains($relative, '.git/'), 'Git metadata entered package');
        $assert(!str_starts_with($relative, '.env') || $relative === '.env.example', 'local environment file entered package');
        $assert(!str_starts_with($relative, 'tools/Tests/'), 'test tooling entered production package');
        $assert(!str_contains($relative, 'doctor-report') && !str_contains($relative, 'doctor-manual'), 'diagnostic report entered package');
        $assert(!in_array($relative, ['storage/attempts.json', 'storage/weakness.json', 'storage/question-statistics.json', 'storage/flashcard_state.json', 'boardprep.zip'], true), "local runtime artifact entered package: {$relative}");
    }

    $manifest = json_decode((string) $packageArchive->getFromName($packageRoot . 'RELEASE_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);
    $head = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
    $assert(($manifest['release'] ?? null) === 'v1.0.1', 'release manifest version is incorrect');
    $assert(($manifest['product_version'] ?? null) === '1.0.1', 'product version is incorrect');
    $assert(($manifest['release_commit'] ?? null) === $head, 'release manifest commit does not match HEAD');
    $packagedQuestions = json_decode((string) $packageArchive->getFromName($packageRoot . 'storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
    $packagedBoards = json_decode((string) $packageArchive->getFromName($packageRoot . 'storage/boards.json'), true, 512, JSON_THROW_ON_ERROR);
    $packagedEligibility = json_decode((string) $packageArchive->getFromName($packageRoot . 'storage/question-eligibility.json'), true, 512, JSON_THROW_ON_ERROR);
    $packagedLinks = json_decode((string) $packageArchive->getFromName($packageRoot . 'storage/study-question-links.json'), true, 512, JSON_THROW_ON_ERROR);
    $packagedMaterials = json_decode((string) $packageArchive->getFromName($packageRoot . 'storage/study-materials.json'), true, 512, JSON_THROW_ON_ERROR);
    $packagedExclusions = json_decode((string) $packageArchive->getFromName($packageRoot . 'storage/flashcard-exclusions.json'), true, 512, JSON_THROW_ON_ERROR);
    $expectedCanonical = is_array($packagedQuestions) ? count($packagedQuestions) : 0;
    $expectedBoards = is_array($packagedBoards) ? count($packagedBoards) : 0;
    $assert(($manifest['canonical_questions'] ?? null) === $expectedCanonical, 'release manifest canonical count does not match packaged question storage');
    $assert(($manifest['registered_boards'] ?? null) === $expectedBoards, 'release manifest board count does not match packaged board storage');
    $packagedLessons = json_decode((string) $packageArchive->getFromName($packageRoot . 'storage/study-lessons.json'), true, 512, JSON_THROW_ON_ERROR);
    $packagedPdfManifest = json_decode((string) $packageArchive->getFromName($packageRoot . 'public/study-resources/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $assert(($manifest['canonical_questions_sha256'] ?? null) === ($entriesOne[$packageRoot . 'storage/questions.json'] ?? null), 'release manifest question fingerprint does not match the archived canonical file');
    $assert(($manifest['study_lessons']['count'] ?? null) === count($packagedLessons), 'release manifest lesson count does not match packaged lesson data');
    $assert(($manifest['study_lessons']['sha256'] ?? null) === ($entriesOne[$packageRoot . 'storage/study-lessons.json'] ?? null), 'release manifest lesson fingerprint does not match packaged lessons');
    $assert(($manifest['study_pdfs']['count'] ?? null) === count($packagedPdfManifest['files'] ?? []), 'release manifest PDF count does not match the packaged PDF manifest');
    $assert(($manifest['study_pdfs']['manifest_sha256'] ?? null) === ($entriesOne[$packageRoot . 'public/study-resources/manifest.json'] ?? null), 'release manifest PDF inventory fingerprint does not match');
    $assert(count($manifest['supported_boards'] ?? []) === $expectedBoards, 'supported board identity list does not match packaged board storage');
    $assert(($manifest['package_validation']['canonical_content'] ?? '') === 'STRUCTURE_PARSED_NOT_SEMANTICALLY_AUDITED'
        && ($manifest['package_validation']['study_pdf_integrity'] ?? '') === 'PASS'
        && ($manifest['package_validation']['deployment_status'] ?? '') === 'NOT_DEPLOYED', 'embedded manifest omitted the content audit limit, asset result, or deployment status');
    foreach ((array) ($manifest['study_pdfs']['files'] ?? []) as $pdf) {
        $assert(is_array($pdf) && ($entriesOne[$packageRoot . ($pdf['path'] ?? '')] ?? null) === ($pdf['sha256'] ?? null), 'release manifest PDF hash does not match packaged content');
    }
    foreach ((array) ($manifest['static_assets'] ?? []) as $asset) {
        $assert(is_array($asset) && ($entriesOne[$packageRoot . ($asset['path'] ?? '')] ?? null) === ($asset['sha256'] ?? null), 'release manifest static asset hash does not match packaged content');
    }

    $packageArchive->close();

    $extract = $temporary . '/extracted';
    $archive = new ZipArchive();
    $assert($archive->open($packageTwo) === true, 'package cannot be reopened for extraction');
    $assert($archive->extractTo($extract), 'package extraction failed');
    $archive->close();
    $assert(unlink($packageTwo), 'verified archive could not be removed after extraction');
    $extractedRoot = $extract . '/' . rtrim($packageRoot, '/');

    $integrityCode = <<<'PHP'
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
require 'app/Core/Autoloader.php';
App\Core\Autoloader::register();
App\Core\App::boot();
$report = App\Services\Shared\ContentIntegrityService::analyzeRepository();
echo json_encode($report, JSON_THROW_ON_ERROR), PHP_EOL;
exit(($report['portfolio']['status'] ?? '') === 'FAIL' ? 1 : 0);
PHP;
    $packageEnvironment = getenv();
    if (!is_array($packageEnvironment)) $packageEnvironment = [];
    $packageEnvironment['APP_ENV'] = 'testing';
    $packageEnvironment['APP_DEBUG'] = 'false';
    $packageEnvironment['APP_STORAGE_PATH'] = $extractedRoot . '/storage';
    $result = $run($php . ' -r ' . escapeshellarg($integrityCode), $extractedRoot, $packageEnvironment);
    $integrity = json_decode(trim($result), true, 512, JSON_THROW_ON_ERROR);
    $portfolio = $integrity['portfolio'] ?? [];
    $canonicalIds = [];
    foreach (is_array($packagedQuestions) ? $packagedQuestions : [] as $question) {
        if (is_array($question) && isset($question['id'])) {
            $canonicalIds[(string) $question['id']] = true;
        }
    }
    $eligibleIds = [];
    foreach (is_array($packagedEligibility) ? $packagedEligibility : [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        foreach ($entry['question_ids'] ?? [] as $questionId) {
            if (isset($canonicalIds[(string) $questionId])) {
                $eligibleIds[(string) $questionId] = true;
            }
        }
    }
    $linkedIds = [];
    foreach (is_array($packagedLinks) ? $packagedLinks : [] as $link) {
        if (is_array($link) && isset($canonicalIds[(string) ($link['question_id'] ?? '')])) {
            $linkedIds[(string) $link['question_id']] = true;
        }
    }
    $expectedDerivable = 0;
    $expectedJustifiedExclusions = 0;
    $deriver = new App\Services\Flashcard\FlashcardDerivationService(
        null,
        is_array($packagedMaterials) ? $packagedMaterials : [],
        is_array($packagedLinks) ? $packagedLinks : [],
        is_array($packagedExclusions) ? $packagedExclusions : []
    );
    foreach (is_array($packagedQuestions) ? $packagedQuestions : [] as $question) {
        $status = $deriver->derivationStatus($question);
        if ($status['derivable']) {
            $expectedDerivable++;
        } elseif ($status['justified']) {
            $expectedJustifiedExclusions++;
        }
    }
    foreach ([
        'total_canonical' => $expectedCanonical,
        'total_eligible' => count($eligibleIds),
        'total_linked' => count($linkedIds),
        'total_invalid_links' => 0,
        'total_invalid_taxonomy_refs' => 0,
        'total_derivable_cards' => $expectedDerivable,
        'total_justified_exclusions' => $expectedJustifiedExclusions,
        'total_accidental_non_derivable' => 0,
        'total_card_key_collisions' => 0,
        'total_broken_assets' => 0,
        'total_invalid_answer_keys' => 0,
    ] as $field => $expected) {
        $assert(($portfolio[$field] ?? null) === $expected, "packaged integrity {$field} changed");
    }
    $assert(($integrity['warnings']['duplicate_taxonomy_ids'] ?? []) === [], 'packaged integrity duplicate taxonomy IDs changed');
    $assert(($portfolio['status'] ?? null) === 'PASS_WITH_JUSTIFIED_EXCLUSIONS', 'packaged portfolio status changed');

    echo "[PASS] deterministic production package, inventory, extraction, and packaged integrity verified\n";
} finally {
    $removeTree($temporary);
}
