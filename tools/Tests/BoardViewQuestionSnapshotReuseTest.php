<?php

declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_STORAGE_PATH');
putenv('APP_SESSION_PATH');
require_once __DIR__ . '/Support/StaticStorageFixture.php';
$testDriver = strtolower(trim((string) (getenv('BATCH746_TEST_DB_DRIVER') ?: 'json')));
if (!in_array($testDriver, ['json', 'sqlite'], true)) {
    throw new RuntimeException('BATCH746_TEST_DB_DRIVER must be json or sqlite.');
}
$fixturePath = StaticStorageFixture::create(dirname(__DIR__, 2), 'batch746-board-snapshot-' . $testDriver);
putenv('APP_STORAGE_PATH=' . $fixturePath);
putenv('DB_DRIVER=' . $testDriver);
if ($testDriver === 'sqlite') {
    putenv('DB_SQLITE_PATH=' . $fixturePath . '/batch746-test.sqlite');
}
register_shutdown_function(static function () use ($fixturePath): void {
    if (!is_dir($fixturePath)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixturePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $path = $entry->getPathname();
        if ($entry->isDir() && !$entry->isLink()) @rmdir($path);
        else @unlink($path);
    }
    @rmdir($fixturePath);
});
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Contracts\StorageInterface;
use App\Core\App;
use App\Services\Board\BoardViewService;
use App\Services\Question\QuestionReuseEligibilityResolver;

final class BoardViewSnapshotReadCounter implements StorageInterface
{
    /** @var array<string,int> */
    public array $allReads = [];

    public function __construct(private readonly StorageInterface $inner)
    {
    }

    public function all(string $collection): array
    {
        $this->allReads[$collection] = ($this->allReads[$collection] ?? 0) + 1;
        return $this->inner->all($collection);
    }

    public function find(string $collection, string $id): ?array { return $this->inner->find($collection, $id); }
    public function where(string $collection, array $criteria): array { return $this->inner->where($collection, $criteria); }
    public function exists(string $collection, string $id): bool { return $this->inner->exists($collection, $id); }
    public function create(string $collection, array $data): array { return $this->inner->create($collection, $data); }
    public function update(string $collection, string $id, array $data): ?array { return $this->inner->update($collection, $id, $data); }
    public function updateBatch(string $collection, array $ids, callable $updater): void { $this->inner->updateBatch($collection, $ids, $updater); }
    public function delete(string $collection, string $id): bool { return $this->inner->delete($collection, $id); }
    public function replace(string $collection, array $records): void { $this->inner->replace($collection, $records); }
}

require dirname(__DIR__, 2) . '/bootstrap/app.php';

App::boot();
$database = App::database();
$storageProperty = new ReflectionProperty($database, 'storage');
$counter = new BoardViewSnapshotReadCounter($storageProperty->getValue($database));
$storageProperty->setValue($database, $counter);

if (!QuestionReuseEligibilityResolver::productionDerivationEnabled()) {
    throw new RuntimeException('Synthetic repository fixture did not enable the production-derived eligibility branch.');
}

$snapshot = static function (array $board): array {
    return [
        'id' => $board['id'] ?? null,
        'code' => $board['code'] ?? null,
        'name' => $board['name'] ?? null,
        'status' => $board['status'] ?? null,
        'subjects' => $board['subjects'] ?? [],
        'available_questions' => $board['available_questions'] ?? null,
        'available_subjects' => $board['available_subjects'] ?? null,
        'content_readiness' => $board['content_readiness'] ?? null,
        'study_status' => $board['study_status'] ?? null,
    ];
};
$hash = static fn (array $boards): string => hash(
    'sha256',
    (string) json_encode(array_map($snapshot, $boards), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
);

// This must be the first board-view call in a fresh process so the test
// exercises the same cold resolver path as an ordinary HTTP request.
$allBoards = BoardViewService::all();
$coldReads = $counter->allReads;
$allHash = $hash($allBoards);
$repeatedFinds = [];
foreach ($allBoards as $board) {
    $found = BoardViewService::find((string) ($board['id'] ?? ''));
    if (!is_array($found)) {
        throw new RuntimeException('Board detail lookup failed for an entry returned by board discovery.');
    }
    $repeatedFinds[] = $found;
}
$findHash = $hash($repeatedFinds);

$report = [
    'driver' => $testDriver,
    'board_count' => count($allBoards),
    'cold_reads' => $coldReads,
    'discovery_output_sha256' => $allHash,
    'repeated_detail_output_sha256' => $findHash,
    'output_equivalent' => hash_equals($allHash, $findHash),
];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

if (count($allBoards) !== 10) {
    throw new RuntimeException('Board discovery did not return all ten supported boards.');
}
if (($coldReads['questions'] ?? 0) !== 1) {
    throw new RuntimeException(sprintf(
        'Cold board discovery read the full canonical question bank %d times; expected exactly once.',
        $coldReads['questions'] ?? 0
    ));
}
if (($coldReads['blueprints'] ?? 0) !== 1) {
    throw new RuntimeException(sprintf(
        'Cold board discovery read the blueprint collection %d times; expected exactly once.',
        $coldReads['blueprints'] ?? 0
    ));
}
if (($coldReads['question-statistics'] ?? 0) !== 0) {
    throw new RuntimeException('Board availability unexpectedly read learner question statistics.');
}
if ($allHash !== $findHash) {
    throw new RuntimeException('Board discovery and individual board views returned different learner availability data.');
}

echo '[PASS] cold board discovery reuses canonical request snapshots, preserves all ten board outputs, and avoids learner statistics.' . PHP_EOL;
