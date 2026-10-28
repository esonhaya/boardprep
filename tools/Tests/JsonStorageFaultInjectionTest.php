<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Exceptions\StorageException;
use App\Storage\JsonStorage;

final class FaultInjectingJsonStorage extends JsonStorage
{
    public string $fault = '';

    protected function writeTemporaryFile(string $path, string $contents): int|false
    {
        if ($this->fault === 'before_write') {
            return false;
        }
        if ($this->fault === 'short_write') {
            return parent::writeTemporaryFile($path, substr($contents, 0, max(1, intdiv(strlen($contents), 2))));
        }
        return parent::writeTemporaryFile($path, $contents);
    }

    protected function replaceCollectionFile(string $temporary, string $path): bool
    {
        if ($this->fault === 'replace') {
            return false;
        }
        return parent::replaceCollectionFile($temporary, $path);
    }
}

function jsonStorageAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = sys_get_temp_dir() . '/boardprep-json-durability-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700, true)) {
    throw new RuntimeException('Unable to create isolated JSON storage fixture.');
}
$parentPid = getmypid();
register_shutdown_function(static function () use ($root, $parentPid): void {
    if (getmypid() !== $parentPid) {
        return;
    }
    $real = realpath($root);
    $tmp = realpath(sys_get_temp_dir());
    if (!is_string($real) || !is_string($tmp) || !str_starts_with($real, $tmp . DIRECTORY_SEPARATOR)
        || !str_contains(basename($real), 'boardprep-json-durability-')) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($real);
});

$path = $root . '/collections';
$storage = new FaultInjectingJsonStorage($path);
$storage->create('attempts', ['id' => 'attempt-one', 'score' => 1]);
$collectionPath = $path . '/attempts.json';
$originalBytes = file_get_contents($collectionPath);
if (!is_string($originalBytes)) {
    throw new RuntimeException('Could not read initial isolated collection.');
}
$originalHash = hash('sha256', $originalBytes);

foreach (['before_write', 'short_write', 'replace'] as $fault) {
    $storage->fault = $fault;
    try {
        $storage->update('attempts', 'attempt-one', ['score' => 99]);
        throw new RuntimeException("Injected {$fault} failure was ignored.");
    } catch (StorageException) {
    }

    jsonStorageAssert(hash_file('sha256', $collectionPath) === $originalHash,
        "Injected {$fault} failure changed the last valid collection bytes.");
    jsonStorageAssert(($storage->find('attempts', 'attempt-one')['score'] ?? null) === 1,
        "Injected {$fault} failure did not preserve the authoritative record.");
    jsonStorageAssert((glob($path . '/attempts.json.tmp-*') ?: []) === [],
        "Injected {$fault} failure left an uncommitted temporary file.");
}

$storage->fault = '';
$storage->update('attempts', 'attempt-one', ['score' => 2]);
jsonStorageAssert(($storage->find('attempts', 'attempt-one')['score'] ?? null) === 2,
    'A valid retry after a failed write did not commit.');

$malformedPath = $root . '/malformed';
mkdir($malformedPath, 0700, true);
$corruptBytes = "[{\"id\":\"valid-before-corruption\"";
file_put_contents($malformedPath . '/attempts.json', $corruptBytes);
$malformedStorage = new JsonStorage($malformedPath);
try {
    $malformedStorage->all('attempts');
    throw new RuntimeException('Malformed JSON was silently read as an empty collection.');
} catch (StorageException $exception) {
    jsonStorageAssert(str_contains($exception->getMessage(), 'malformed JSON'),
        'Malformed JSON did not fail with the storage integrity error.');
}
jsonStorageAssert(file_get_contents($malformedPath . '/attempts.json') === $corruptBytes,
    'A failed malformed-state read modified the original bytes.');

if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
    throw new RuntimeException('The supported local PHP runtime lacks process concurrency primitives.');
}
$concurrentPath = $root . '/concurrent';
$concurrent = new JsonStorage($concurrentPath);
$concurrent->create('attempts', ['id' => 'seed', 'value' => 0]);
$children = [];
for ($i = 0; $i < 8; $i++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        throw new RuntimeException('Could not start isolated JSON mutation worker.');
    }
    if ($pid === 0) {
        try {
            (new JsonStorage($concurrentPath))->create('attempts', ['id' => 'worker-' . $i, 'value' => $i]);
            exit(0);
        } catch (Throwable) {
            exit(1);
        }
    }
    $children[] = $pid;
}
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
    jsonStorageAssert(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0,
        'A concurrent isolated collection write failed.');
}
$rows = $concurrent->all('attempts');
jsonStorageAssert(count($rows) === 9, 'Concurrent JSON writes lost or duplicated collection records.');
jsonStorageAssert(count(array_unique(array_column($rows, 'id'))) === 9,
    'Concurrent JSON writes produced duplicate collection identities.');

echo "[PASS] Short writes, pre-write failure, replacement failure, malformed JSON, retry recovery, temporary cleanup, and 8 concurrent collection creates preserved authoritative state.\n";
