<?php

declare(strict_types=1);

namespace Tools\Tests;

use App\Contracts\StorageInterface;
use App\Core\App;
use App\Core\Autoloader;
use App\Services\Board\BoardViewService;

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
Autoloader::register();

final class BoardDetailCountingStorage implements StorageInterface
{
    /** @var array<string, int> */
    public array $reads = [];

    public function __construct(private StorageInterface $inner)
    {
    }

    public function all(string $collection): array
    {
        $this->reads[$collection] = ($this->reads[$collection] ?? 0) + 1;
        return $this->inner->all($collection);
    }

    public function find(string $collection, string $id): ?array
    {
        return $this->inner->find($collection, $id);
    }

    public function where(string $collection, array $criteria): array
    {
        return $this->inner->where($collection, $criteria);
    }

    public function create(string $collection, array $data): array
    {
        return $this->inner->create($collection, $data);
    }

    public function update(string $collection, string $id, array $data): ?array
    {
        return $this->inner->update($collection, $id, $data);
    }

    public function updateBatch(string $collection, array $ids, callable $updater): void
    {
        $this->inner->updateBatch($collection, $ids, $updater);
    }

    public function delete(string $collection, string $id): bool
    {
        return $this->inner->delete($collection, $id);
    }

    public function exists(string $collection, string $id): bool
    {
        return $this->inner->exists($collection, $id);
    }

    public function replace(string $collection, array $records): void
    {
        $this->inner->replace($collection, $records);
    }
}

App::boot();
$database = App::database();
$storageProperty = new \ReflectionProperty($database, 'storage');
$countingStorage = new BoardDetailCountingStorage($storageProperty->getValue($database));
$storageProperty->setValue($database, $countingStorage);

if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal') && function_exists('pcntl_alarm')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGALRM, static function () use ($countingStorage): void {
        echo json_encode([
            'timeout_seconds' => 5,
            'reads' => $countingStorage->reads,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(124);
    });
    pcntl_alarm(5);
}

$boards = App::container()->get(\App\Repositories\BoardRepository::class)->all();
$ids = array_values(array_filter(array_map(
    static fn(mixed $board): string => is_array($board) ? (string) ($board['id'] ?? '') : '',
    $boards
)));

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
$snapshotHash = static function (mixed $boards) use ($snapshot): string {
    $snapshots = is_array($boards) ? array_map($snapshot, $boards) : [];
    return hash('sha256', (string) json_encode($snapshots, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
};

$run = static function (callable $operation) use ($countingStorage): array {
    $countingStorage->reads = [];
    $start = hrtime(true);
    $result = $operation();
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    return [$result, $elapsed, $countingStorage->reads];
};

$single = [];
$singleIds = ['let', 'civil-service', 'architecture', 'civil-engineering'];
if (($argv[1] ?? '') !== '' && ($argv[1] ?? '') !== 'single' && ($argv[1] ?? '') !== 'all') {
    $singleIds = [(string) $argv[1]];
}
foreach ($singleIds as $id) {
    [$result, $elapsed, $reads] = $run(static fn() => BoardViewService::find($id));
    $single[$id] = [
        'elapsed_ms' => round($elapsed, 3),
        'output' => is_array($result) ? $snapshot($result) : null,
        'reads' => $reads,
    ];
}

if (($argv[1] ?? '') !== '' && ($argv[1] ?? '') !== 'all') {
    echo json_encode(['single' => $single], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

[$all, $allElapsed, $allReads] = $run(static fn() => BoardViewService::all());
[$repeat, $repeatElapsed, $repeatReads] = $run(static function () use ($ids): array {
    $results = [];
    foreach ($ids as $id) {
        $results[] = BoardViewService::find($id);
    }
    return $results;
});

$allHash = $snapshotHash($all);
$repeatHash = $snapshotHash($repeat);
if (function_exists('pcntl_alarm')) {
    pcntl_alarm(0);
}

$output = [
    'board_count' => count($ids),
    'question_count' => count(App::container()->get(\App\Repositories\QuestionRepository::class)->all()),
    'single' => $single,
    'all' => [
        'elapsed_ms' => round($allElapsed, 3),
        'board_count' => is_array($all) ? count($all) : null,
        'output_sha256' => $allHash,
        'outputs' => is_array($all) ? array_map($snapshot, $all) : null,
        'reads' => $allReads,
    ],
    'repeated_same_process' => [
        'elapsed_ms' => round($repeatElapsed, 3),
        'board_count' => count($repeat),
        'output_sha256' => $repeatHash,
        'output_invariant' => $allHash === $repeatHash,
        'reads' => $repeatReads,
    ],
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
