<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Database\LegacyCollectionImporter;
use App\Database\MigrationRunner;
use App\Exceptions\StorageException;
use App\Storage\JsonStorage;
use App\Storage\SqliteStorage;
use App\Storage\StorageRouter;

$root = sys_get_temp_dir() . '/boardprep-db-' . bin2hex(random_bytes(6));
$path = $root . '/boardprep.sqlite';
$legacyPath = $root . '/legacy';
mkdir($legacyPath, 0777, true);
$legacy = new JsonStorage($legacyPath);
$legacy->replace('attempts', [
    ['id' => 'legacy-1', 'date' => '2026-01-01', 'mode' => 'practice', 'score' => 2],
    ['id' => 'stable-1', 'date' => '2026-01-02', 'mode' => 'exam', 'score' => 4],
]);
$legacy->replace('weakness', [
    ['id' => 'Hydraulics', 'correct' => 3, 'wrong' => 1],
]);

try {
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $runner = new MigrationRunner(dirname(__DIR__, 2) . '/database/migrations');
    $first = $runner->migrate($pdo);
    if ($first !== ['001_create_storage_records']) {
        throw new RuntimeException('migration from zero did not apply exactly once');
    }
    if ($runner->migrate($pdo) !== []) {
        throw new RuntimeException('migration rerun applied an already-applied migration');
    }
    $storage = new SqliteStorage($pdo);
    $storage->create('attempts', ['id' => 'a1', 'score' => 3]);
    $storage->update('attempts', 'a1', ['score' => 4]);
    if (($storage->find('attempts', 'a1')['score'] ?? null) !== 4) {
        throw new RuntimeException('SQLite CRUD roundtrip failed');
    }
    $storage->transaction(function (SqliteStorage $db): void {
        $db->create('attempts', ['id' => 'committed', 'score' => 1]);
    });
    try {
        $storage->transaction(function (SqliteStorage $db): void {
            $db->create('attempts', ['id' => 'rolled-back', 'score' => 1]);
            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException $exception) {
    }
    if ($storage->exists('attempts', 'rolled-back')) {
        throw new RuntimeException('transaction rollback failed');
    }
    try {
        $storage->create('attempts', ['id' => 'a1', 'score' => 9]);
        throw new RuntimeException('duplicate primary key was accepted');
    } catch (StorageException $exception) {
    }
    $import = (new LegacyCollectionImporter())->import($legacy, $storage, 'attempts');
    $weaknessImport = (new LegacyCollectionImporter())->import($legacy, $storage, 'weakness');
    if ($import['imported'] !== 2 || $import['invalid'] !== 0 || $weaknessImport['imported'] !== 1) {
        throw new RuntimeException('legacy import did not preserve valid records');
    }
    $rerun = (new LegacyCollectionImporter())->import($legacy, $storage, 'attempts');
    $weaknessRerun = (new LegacyCollectionImporter())->import($legacy, $storage, 'weakness');
    if ($rerun['imported'] !== 0 || $rerun['existing'] !== 2 || $weaknessRerun['imported'] !== 0 || $weaknessRerun['existing'] !== 1) {
        throw new RuntimeException('legacy import was not idempotent');
    }
    $router = new StorageRouter(new JsonStorage($root . '/content'), ['attempts' => $storage, 'weakness' => $storage]);
    $router->create('content', ['id' => 'json-1', 'value' => 'canonical']);
    if (!$router->exists('content', 'json-1') || !$router->exists('attempts', 'a1') || !$router->exists('weakness', 'Hydraulics')) {
        throw new RuntimeException('scoped production storage routing failed');
    }
    unset($storage, $pdo);
    $reconnected = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $check = $reconnected->prepare('SELECT COUNT(*) FROM storage_records WHERE collection = ?');
    $check->execute(['attempts']);
    if ((int) $check->fetchColumn() !== 4) {
        throw new RuntimeException('reconnect persistence failed');
    }
    echo "[PASS] SQLite migrations, CRUD, routing, transactions, import, and reconnect persistence verified.\n";
} finally {
    foreach (glob($root . '/{,*/}*', GLOB_BRACE) ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($legacyPath);
    @rmdir($root);
}
