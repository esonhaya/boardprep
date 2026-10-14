<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService;

$repositoryRoot = dirname(__DIR__, 2);
$fixtureRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . '/boardprep-health-evidence-'
    . bin2hex(random_bytes(8));
if (!mkdir($fixtureRoot, 0700, true)) {
    throw new RuntimeException('Unable to create evidence freshness fixtures.');
}

$snapshotPath = $fixtureRoot . '/snapshot.json';
$validPayload = [
    'generated_at' => date(DATE_ATOM),
    'overall_status' => 'PASS',
    'summary' => ['pass_count' => 2, 'warn_count' => 0, 'fail_count' => 0],
];
$passed = false;

try {
    $cached = RepositoryHealthEvidenceService::preparePersistedSnapshot($validPayload, $repositoryRoot);
    file_put_contents($snapshotPath, json_encode($cached, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    $classification = RepositoryHealthEvidenceService::classifyPersistedSnapshot($snapshotPath, $repositoryRoot);
    if (($classification['state'] ?? '') !== RepositoryHealthEvidenceService::CACHED) {
        throw new RuntimeException('A fresh, intact snapshot was not recognized as current-checkout cached evidence.');
    }

    $historicalCheckout = $cached;
    $historicalCheckout['evidence']['repository_head'] = str_repeat('0', 40);
    file_put_contents($snapshotPath, json_encode($historicalCheckout, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    $historicalClassification = RepositoryHealthEvidenceService::classifyPersistedSnapshot($snapshotPath, $repositoryRoot);
    if (
        ($historicalClassification['state'] ?? '') !== RepositoryHealthEvidenceService::STALE
        || ($historicalClassification['reason'] ?? '') !== 'snapshot_from_different_checkout_state'
    ) {
        throw new RuntimeException('A report from a different Git checkout was not rejected as stale.');
    }

    $differentWorktree = $cached;
    $differentWorktree['evidence']['worktree_sha256'] = str_repeat('f', 64);
    file_put_contents($snapshotPath, json_encode($differentWorktree, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    $worktreeClassification = RepositoryHealthEvidenceService::classifyPersistedSnapshot($snapshotPath, $repositoryRoot);
    if (
        ($worktreeClassification['state'] ?? '') !== RepositoryHealthEvidenceService::STALE
        || ($worktreeClassification['reason'] ?? '') !== 'snapshot_from_different_checkout_state'
    ) {
        throw new RuntimeException('A report from a different worktree state was not rejected as stale.');
    }

    $tampered = $cached;
    $tampered['summary']['pass_count'] = 9;
    file_put_contents($snapshotPath, json_encode($tampered, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
    $tamperedClassification = RepositoryHealthEvidenceService::classifyPersistedSnapshot($snapshotPath, $repositoryRoot);
    if (($tamperedClassification['state'] ?? '') !== RepositoryHealthEvidenceService::STALE) {
        throw new RuntimeException('A report whose contents no longer match its hash was accepted.');
    }

    $legacyPath = $fixtureRoot . '/legacy.json';
    file_put_contents($legacyPath, json_encode($validPayload, JSON_THROW_ON_ERROR), LOCK_EX);
    $legacyClassification = RepositoryHealthEvidenceService::classifyPersistedSnapshot($legacyPath, $repositoryRoot);
    if (($legacyClassification['state'] ?? '') !== RepositoryHealthEvidenceService::STALE) {
        throw new RuntimeException('A legacy snapshot without provenance was accepted as current.');
    }

    $missingClassification = RepositoryHealthEvidenceService::classifyPersistedSnapshot($fixtureRoot . '/missing.json', $repositoryRoot);
    if (($missingClassification['state'] ?? '') !== RepositoryHealthEvidenceService::UNAVAILABLE) {
        throw new RuntimeException('A missing snapshot was not classified as unavailable evidence.');
    }

    $oldPayload = $validPayload;
    $oldPayload['generated_at'] = '2020-01-01T00:00:00+00:00';
    $oldPath = $fixtureRoot . '/old.json';
    $oldSnapshot = RepositoryHealthEvidenceService::preparePersistedSnapshot($oldPayload, $repositoryRoot);
    file_put_contents($oldPath, json_encode($oldSnapshot, JSON_THROW_ON_ERROR), LOCK_EX);
    $oldClassification = RepositoryHealthEvidenceService::classifyPersistedSnapshot($oldPath, $repositoryRoot);
    if (($oldClassification['state'] ?? '') !== RepositoryHealthEvidenceService::STALE) {
        throw new RuntimeException('An expired snapshot was accepted as current evidence.');
    }

    $passed = true;
    echo "[PASS] Current, cached, stale, and unavailable evidence classifications verified; report hashes, timestamps, Git heads, and recursive worktree fingerprints are enforced.\n";
} finally {
    if ($passed) {
        foreach (glob($fixtureRoot . '/*') ?: [] as $fixtureFile) {
            if (is_file($fixtureFile)) unlink($fixtureFile);
        }
        rmdir($fixtureRoot);
    } else {
        fwrite(STDERR, 'TEST_FIXTURE_PRESERVED=' . $fixtureRoot . PHP_EOL);
    }
}
