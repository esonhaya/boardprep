<?php

declare(strict_types=1);

// Synthetic-only fixture for the isolated browser account server.
$root = dirname(__DIR__, 2);
$storagePath = (string) getenv('APP_STORAGE_PATH');
$temporaryRoot = realpath(sys_get_temp_dir());
$storageReal = realpath($storagePath);
if (getenv('APP_ENV') !== 'testing'
    || !is_string($temporaryRoot)
    || !is_string($storageReal)
    || !str_starts_with($storageReal, $temporaryRoot . DIRECTORY_SEPARATOR)
    || !str_contains(basename(dirname($storageReal)), 'boardprep-browser-')) {
    fwrite(STDERR, "Refusing to seed academic browser identity outside isolated testing storage.\n");
    exit(2);
}

require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
\App\Core\App::boot();

$accountId = 'b716-browser-reviewer-synthetic';
$learnerId = 'b716-browser-learner-synthetic';
$account = new \App\Services\Accounts\AccountRepository();
if (!$account->create([
    'account_id' => $accountId,
    'learner_id' => $learnerId,
    'username' => 'b716-synthetic-reviewer',
    'password_hash' => password_hash('B716 synthetic browser fixture password only', PASSWORD_DEFAULT),
    'status' => 'active',
    'auth_version' => 1,
    'created_at' => gmdate(DATE_ATOM),
    'updated_at' => gmdate(DATE_ATOM),
])) {
    fwrite(STDERR, "Unable to seed the synthetic browser reviewer account.\n");
    exit(3);
}
\App\Core\App::storage()->create('academic-reviewer-authorizations', [
    'id' => 'b716-browser-review-grant-synthetic',
    'account_id' => $accountId,
    'authorization_status' => 'ACTIVE',
    'qualification_type' => 'SUBJECT_EXPERT',
    'qualification_reference' => 'SYNTHETIC_TEST_FIXTURE_ONLY',
    'authorized_by' => 'SYNTHETIC_TEST_FIXTURE_OWNER',
    'authorized_at' => gmdate(DATE_ATOM),
    'expires_at' => '2099-12-31T23:59:59+00:00',
    'scopes' => [
        [
            'board_id' => 'let',
            'subject_id' => 'early-childhood-education',
            'capabilities' => ['REVIEW', 'SOURCE_REVIEW', 'TAXONOMY_RECONCILE', 'STUDY_LINK'],
        ],
        [
            'board_id' => 'let',
            'subject_id' => 'technical-vocational-teacher-education',
            'capabilities' => ['REVIEW', 'SOURCE_REVIEW', 'TAXONOMY_RECONCILE', 'STUDY_LINK'],
        ],
    ],
]);

// Deliberately stale one supplemental evidence row only in this temporary
// browser fixture so the private inspector's fail-closed version warning is
// exercised. The production checkout and its review data are never touched.
$rootReal = realpath($root);
$temporaryRoot = realpath(sys_get_temp_dir());
$matrixPath = $root . '/docs/reports/batch-716-source-evidence-matrix.json';
if (!is_string($rootReal) || !is_string($temporaryRoot)
    || !str_starts_with($rootReal, $temporaryRoot . DIRECTORY_SEPARATOR)
    || !is_file($matrixPath)) {
    fwrite(STDERR, "Refusing to alter the stale-version browser fixture outside the extracted temporary checkout.\n");
    exit(4);
}
$matrix = json_decode((string) file_get_contents($matrixPath), true, 512, JSON_THROW_ON_ERROR);
$staled = false;
foreach ((array) ($matrix['citation_rows'] ?? []) as &$citation) {
    if (is_array($citation) && (string) ($citation['question_id'] ?? '') === '9348') {
        $citation['question_version'] = str_repeat('0', 64);
        $staled = true;
        break;
    }
}
unset($citation);
if (!$staled || file_put_contents($matrixPath, json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", LOCK_EX) === false) {
    fwrite(STDERR, "Unable to prepare the isolated stale-version browser evidence fixture.\n");
    exit(5);
}
fwrite(STDOUT, "Seeded isolated synthetic reviewer; no import capability or real identity.\n");
