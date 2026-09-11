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
$temporary = sys_get_temp_dir() . '/boardprep-release-test-' . bin2hex(random_bytes(4));
$packageOne = $temporary . '/build-one.zip';
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

$run = static function (string $command, string $workingDirectory): string {
    $descriptor = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, $workingDirectory);
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
    $php = escapeshellarg(PHP_BINARY);
    $tool = escapeshellarg($releaseTool);
    $run("{$php} {$tool} --output=" . escapeshellarg($packageOne), $root);
    $run("{$php} {$tool} --output=" . escapeshellarg($packageTwo), $root);

    $shaOne = hash_file('sha256', $packageOne);
    $shaTwo = hash_file('sha256', $packageTwo);
    $assert(is_string($shaOne) && $shaOne === $shaTwo, 'repeated package builds differ byte-for-byte');

    $zipOne = new ZipArchive();
    $zipTwo = new ZipArchive();
    $assert($zipOne->open($packageOne) === true, 'first package cannot be opened');
    $assert($zipTwo->open($packageTwo) === true, 'second package cannot be opened');

    $entriesOne = [];
    for ($index = 0; $index < $zipOne->numFiles; $index++) {
        $name = $zipOne->getNameIndex($index);
        $entriesOne[$name] = hash('sha256', (string) $zipOne->getFromIndex($index));
    }
    $entriesTwo = [];
    for ($index = 0; $index < $zipTwo->numFiles; $index++) {
        $name = $zipTwo->getNameIndex($index);
        $entriesTwo[$name] = hash('sha256', (string) $zipTwo->getFromIndex($index));
    }
    ksort($entriesOne, SORT_STRING);
    ksort($entriesTwo, SORT_STRING);
    $assert($entriesOne === $entriesTwo, 'normalized package inventories differ');

    $prefix = array_key_first($entriesOne);
    $assert(is_string($prefix) && preg_match('#^BoardPrep_RC1\.1_[^/]+/#', $prefix) === 1, 'package root is not release-scoped');
    $packageRoot = substr($prefix, 0, strpos($prefix, '/') + 1);
    $required = [
        'RELEASE_MANIFEST.json',
        'public/index.php',
        'app/Core/App.php',
        'storage/board-concept-coverage.json',
        'storage/concept-derived-eligibility.json',
        'storage/question-reuse-metadata.json',
        'storage/questions.json',
        'storage/question-eligibility.json',
        'storage/study-materials.json',
        'storage/taxonomy/domains.json',
        'tools/db-migrate.php',
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

    $manifest = json_decode((string) $zipOne->getFromName($packageRoot . 'RELEASE_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);
    $head = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
    $assert(($manifest['release'] ?? null) === 'RC1.1', 'release manifest version is incorrect');
    $assert(($manifest['release_commit'] ?? null) === $head, 'release manifest commit does not match HEAD');
    $packagedQuestions = json_decode((string) $zipOne->getFromName($packageRoot . 'storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
    $packagedBoards = json_decode((string) $zipOne->getFromName($packageRoot . 'storage/boards.json'), true, 512, JSON_THROW_ON_ERROR);
    $packagedEligibility = json_decode((string) $zipOne->getFromName($packageRoot . 'storage/question-eligibility.json'), true, 512, JSON_THROW_ON_ERROR);
    $packagedLinks = json_decode((string) $zipOne->getFromName($packageRoot . 'storage/study-question-links.json'), true, 512, JSON_THROW_ON_ERROR);
    $expectedCanonical = is_array($packagedQuestions) ? count($packagedQuestions) : 0;
    $expectedBoards = is_array($packagedBoards) ? count($packagedBoards) : 0;
    $assert(($manifest['canonical_questions'] ?? null) === $expectedCanonical, 'release manifest canonical count does not match packaged question storage');
    $assert(($manifest['registered_boards'] ?? null) === $expectedBoards, 'release manifest board count does not match packaged board storage');

    $zipOne->close();
    $zipTwo->close();

    $extract = $temporary . '/extracted';
    $archive = new ZipArchive();
    $assert($archive->open($packageOne) === true, 'package cannot be reopened for extraction');
    $assert($archive->extractTo($extract), 'package extraction failed');
    $archive->close();
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
    $result = $run($php . ' -r ' . escapeshellarg($integrityCode), $extractedRoot);
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
    foreach (is_array($packagedQuestions) ? $packagedQuestions : [] as $question) {
        $status = App\Services\Shared\ContentIntegrityService::derivationStatus($question);
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
