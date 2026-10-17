<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/SimulationStorageFixture.php';

$temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . '/boardprep-simulation-private-state-'
    . bin2hex(random_bytes(8));
$storageSource = $temporaryRoot . '/storage';
mkdir($storageSource . '/private/learner-accounts', 0700, true);

$fixtureValues = [
    'questions.json' => "[{\"id\":\"fixture-content\"}]\n",
    'attempts.json' => "[\"private-attempt-marker\"]\n",
    'results.json' => "[\"private-result-marker\"]\n",
    'history.json' => "[\"private-history-marker\"]\n",
    'progress.json' => "[\"private-progress-marker\"]\n",
    'weakness.json' => "[\"private-weakness-marker\"]\n",
    'question-statistics.json' => "[\"private-statistics-marker\"]\n",
    'flashcard_state.json' => "[\"private-flashcard-marker\"]\n",
    'private/learner-accounts/accounts.json' => "{\"secret\":\"private-credential-marker\"}\n",
];
foreach ($fixtureValues as $relative => $contents) {
    file_put_contents($storageSource . '/' . $relative, $contents, LOCK_EX);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    putenv('APP_STORAGE_PATH');
    putenv('APP_SESSION_PATH');
    \Tools\Doctor\Project\BoardPrep\Simulation\SimulationStorageFixture::activate($temporaryRoot);
    $fixtureStorage = \Tools\Doctor\Project\BoardPrep\Simulation\SimulationStorageFixture::storagePath();

    foreach (['attempts.json', 'weakness.json', 'question-statistics.json', 'flashcard_state.json'] as $name) {
        $assert(file_get_contents($fixtureStorage . '/' . $name) === "[]\n", 'Mutable learner state was copied into simulator fixture: ' . $name);
    }
    foreach (['results.json', 'history.json', 'progress.json'] as $name) {
        $assert(!is_file($fixtureStorage . '/' . $name), 'Historical learner data was copied into simulator fixture: ' . $name);
    }
    $assert(!is_dir($fixtureStorage . '/private'), 'Private account storage was copied into simulator fixture.');
    $assert(file_get_contents($fixtureStorage . '/questions.json') === $fixtureValues['questions.json'], 'Canonical fixture content was not retained.');
    foreach ($fixtureValues as $relative => $contents) {
        $assert(file_get_contents($storageSource . '/' . $relative) === $contents, 'Synthetic source storage changed during fixture preparation.');
    }

    echo "[PASS] Simulator browser/HTTP fixtures exclude learner histories, flashcard state, and private account credentials while retaining canonical content.\n";
} finally {
    $safeRoot = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    $resolved = realpath($temporaryRoot);
    if ($resolved !== false
        && str_starts_with($resolved, $safeRoot . DIRECTORY_SEPARATOR)
        && basename($resolved) === basename($temporaryRoot)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
            else @unlink($entry->getPathname());
        }
        @rmdir($resolved);
    }
}
