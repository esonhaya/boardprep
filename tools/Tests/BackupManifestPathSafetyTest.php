<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Operations\BackupService;

$root = dirname(__DIR__, 2);
$temporary = sys_get_temp_dir() . '/boardprep-backup-path-safety-' . bin2hex(random_bytes(5));

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$removeTree = static function (string $directory) use (&$removeTree): void {
    if (!is_dir($directory)) return;
    foreach (new FilesystemIterator($directory) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) $removeTree($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($directory);
};
$writeManifest = static function (string $backup, string $storageMode, string $relative, string $source): void {
    $manifest = [
        'storage_mode' => $storageMode,
        'files' => [[
            'relative' => $relative,
            'bytes' => filesize($source),
            'sha256' => hash_file('sha256', $source),
        ]],
    ];
    file_put_contents($backup . '/backup-manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
};
$mustReject = static function (string $caseRoot, string $relative, string $source, string $storageMode = 'json') use ($root, $assert, $writeManifest): void {
    $backup = $caseRoot . '/backup';
    $destination = $caseRoot . '/restore/nested/target';
    if (!is_dir($backup . '/json/etc')) mkdir($backup . '/json/etc', 0700, true);
    if (!is_dir($destination)) mkdir($destination, 0700, true);
    $writeManifest($backup, $storageMode, $relative, $source);
    $verification = BackupService::verify($root, $backup);
    $assert(($verification['status'] ?? '') === 'FAIL', 'Unsafe manifest path was accepted: ' . $relative);
    try {
        BackupService::restore($root, $backup, $destination);
        throw new RuntimeException('Restore accepted unsafe manifest path: ' . $relative);
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'verification failed'), 'Unsafe restore failed for an unrelated reason.');
    }
    $assert(!is_file($caseRoot . '/restore/outside.txt'), 'Unsafe restore wrote outside its requested destination.');
};

mkdir($temporary, 0700, true);
try {
    // This was reproduced against production code: verify and restore both
    // accepted this manifest and wrote outside the requested restore root.
    $traversalRoot = $temporary . '/traversal';
    mkdir($traversalRoot . '/backup/json', 0700, true);
    mkdir($traversalRoot . '/restore/nested/target', 0700, true);
    $traversalSource = $traversalRoot . '/outside.txt';
    file_put_contents($traversalSource, 'isolated traversal canary');
    $writeManifest($traversalRoot . '/backup', 'json', 'json/../../outside.txt', $traversalSource);
    $verification = BackupService::verify($root, $traversalRoot . '/backup');
    $assert(($verification['status'] ?? '') === 'FAIL', 'Traversal manifest must fail verification.');
    try {
        BackupService::restore($root, $traversalRoot . '/backup', $traversalRoot . '/restore/nested/target');
        throw new RuntimeException('Traversal manifest must not restore.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'verification failed'), 'Traversal restore failed for an unrelated reason.');
    }
    $assert(!is_file($traversalRoot . '/restore/outside.txt'), 'Traversal wrote outside the requested restore destination.');

    $absoluteRoot = $temporary . '/absolute';
    mkdir($absoluteRoot . '/backup/etc', 0700, true);
    mkdir($absoluteRoot . '/restore/nested/target', 0700, true);
    $absoluteSource = $absoluteRoot . '/backup/etc/passwd';
    file_put_contents($absoluteSource, 'isolated absolute-path canary');
    $mustReject($absoluteRoot, '/etc/passwd', $absoluteSource);

    $unknownRoot = $temporary . '/unknown-collection';
    mkdir($unknownRoot . '/backup/json', 0700, true);
    mkdir($unknownRoot . '/restore/nested/target', 0700, true);
    $unknownSource = $unknownRoot . '/backup/json/custom.json';
    file_put_contents($unknownSource, 'unregistered collection');
    $mustReject($unknownRoot, 'json/custom.json', $unknownSource);

    $modeRoot = $temporary . '/wrong-mode';
    mkdir($modeRoot . '/backup/sqlite', 0700, true);
    mkdir($modeRoot . '/restore/nested/target', 0700, true);
    $modeSource = $modeRoot . '/backup/sqlite/boardprep.sqlite';
    file_put_contents($modeSource, 'sqlite-mode marker');
    $mustReject($modeRoot, 'sqlite/boardprep.sqlite', $modeSource, 'json');

    $symlinkRoot = $temporary . '/symlink';
    mkdir($symlinkRoot . '/backup/json', 0700, true);
    mkdir($symlinkRoot . '/restore/nested/target', 0700, true);
    $symlinkSource = $symlinkRoot . '/private-source.txt';
    file_put_contents($symlinkSource, 'private source must not be imported');
    if (!@symlink($symlinkSource, $symlinkRoot . '/backup/json/attempts.json')) {
        throw new RuntimeException('Unable to create symlink fixture required by this safety test.');
    }
    $writeManifest($symlinkRoot . '/backup', 'json', 'json/attempts.json', $symlinkSource);
    $symlinkVerification = BackupService::verify($root, $symlinkRoot . '/backup');
    $assert(($symlinkVerification['status'] ?? '') === 'FAIL', 'Backup file symlink must fail verification.');
    try {
        BackupService::restore($root, $symlinkRoot . '/backup', $symlinkRoot . '/restore/nested/target');
        throw new RuntimeException('Restore accepted symlinked backup content.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'verification failed'), 'Symlink restore failed for an unrelated reason.');
    }

    $validRoot = $temporary . '/valid';
    mkdir($validRoot . '/backup/json', 0700, true);
    $validSource = $validRoot . '/backup/json/attempts.json';
    file_put_contents($validSource, "[]\n");
    $writeManifest($validRoot . '/backup', 'json', 'json/attempts.json', $validSource);
    $validVerification = BackupService::verify($root, $validRoot . '/backup');
    $assert(($validVerification['status'] ?? '') === 'PASS', 'A supported mutable collection backup must verify.');
    $validRestore = BackupService::restore($root, $validRoot . '/backup', $validRoot . '/restore');
    $assert(($validRestore['status'] ?? '') === 'PASS', 'A supported mutable collection backup must restore.');
    $assert(file_get_contents($validRoot . '/restore/attempts.json') === "[]\n", 'Valid restore content changed.');

    $duplicateRoot = $temporary . '/duplicate-entry';
    mkdir($duplicateRoot . '/backup/json', 0700, true);
    $duplicateSource = $duplicateRoot . '/backup/json/attempts.json';
    file_put_contents($duplicateSource, "[]\n");
    $writeManifest($duplicateRoot . '/backup', 'json', 'json/attempts.json', $duplicateSource);
    $duplicateManifest = json_decode((string) file_get_contents($duplicateRoot . '/backup/backup-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $duplicateManifest['files'][] = $duplicateManifest['files'][0];
    file_put_contents($duplicateRoot . '/backup/backup-manifest.json', json_encode($duplicateManifest, JSON_THROW_ON_ERROR));
    $assert(BackupService::verify($root, $duplicateRoot . '/backup')['status'] === 'FAIL', 'Duplicate manifest entries must fail verification.');

    $symlinkTargetRoot = $temporary . '/symlink-target';
    mkdir($symlinkTargetRoot . '/backup/json', 0700, true);
    mkdir($symlinkTargetRoot . '/restore', 0700, true);
    $targetSource = $symlinkTargetRoot . '/backup/json/attempts.json';
    $outsideTarget = $symlinkTargetRoot . '/outside-target.json';
    file_put_contents($targetSource, "[\"restored\"]\n");
    file_put_contents($outsideTarget, "[\"preserved\"]\n");
    $writeManifest($symlinkTargetRoot . '/backup', 'json', 'json/attempts.json', $targetSource);
    if (!@symlink($outsideTarget, $symlinkTargetRoot . '/restore/attempts.json')) {
        throw new RuntimeException('Unable to create restore-target symlink fixture required by this safety test.');
    }
    try {
        BackupService::restore($root, $symlinkTargetRoot . '/backup', $symlinkTargetRoot . '/restore', false, true);
        throw new RuntimeException('Restore must not follow a symlinked destination file.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'Symlinked backup or restore paths'), 'Symlink target restore failed for an unrelated reason.');
    }
    $assert(file_get_contents($outsideTarget) === "[\"preserved\"]\n", 'Restore changed a file outside its requested destination through a symlink.');

    echo "[PASS] Backup verification rejects traversal, absolute, unknown, wrong-mode, duplicate, and symlink paths; restore does not follow a destination symlink and valid JSON collection restore remains supported.\n";
} finally {
    $removeTree($temporary);
}
