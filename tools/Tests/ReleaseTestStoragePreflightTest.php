<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\RepositoryHealth\Release\ReleaseReadinessService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$fixtureBytes = 92412638;
$preflight = ReleaseReadinessService::evaluateFlowTestStoragePreflight(null, $fixtureBytes);
$assert(($preflight['minimum_free_bytes'] ?? null) === 1610612736, 'The 1.5 GiB storage safety floor changed.');
$assert(($preflight['runtime_reserve_bytes'] ?? null) === 134217728, 'The 128 MiB runtime reserve changed.');
$assert(($preflight['fixture_bytes'] ?? null) === $fixtureBytes, 'The measured fixture bytes were not included.');
$assert(($preflight['observed_additional_test_storage_reserve_bytes'] ?? null) === 533401600, 'The measured additional test-storage peak was not reserved.');
$assert(($preflight['required_available_bytes'] ?? null) === 2370644702, 'The evidence-derived storage requirement changed unexpectedly.');
$assert(($preflight['status'] ?? '') === 'REVIEW', 'Unknown free space must keep release tests blocked.');

$assert(
    (ReleaseReadinessService::evaluateFlowTestStoragePreflight(2370644701, $fixtureBytes)['status'] ?? '') === 'REVIEW',
    'A one-byte storage shortfall incorrectly passed preflight.'
);
$assert(
    (ReleaseReadinessService::evaluateFlowTestStoragePreflight(2370644702, $fixtureBytes)['status'] ?? '') === 'PASS',
    'The exact evidence-derived storage requirement did not pass preflight.'
);

$negativeFixtureRejected = false;
try {
    ReleaseReadinessService::evaluateFlowTestStoragePreflight(2370644702, -1);
} catch (InvalidArgumentException) {
    $negativeFixtureRejected = true;
}
$assert($negativeFixtureRejected, 'Negative fixture size was accepted.');

echo "Release test storage preflight PASS (floor preserved; required=2370644702; exact boundary verified).\n";
