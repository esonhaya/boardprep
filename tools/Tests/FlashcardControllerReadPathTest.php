<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Contracts\StorageInterface;
use App\Core\App;
use App\Core\Autoloader;
use App\Controllers\FlashcardController;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationStorageFixture;

final class FlashcardReadCountingStorage implements StorageInterface
{
    public int $questionCollectionReads = 0;

    public function __construct(private StorageInterface $inner)
    {
    }

    public function all(string $collection): array
    {
        if ($collection === 'questions') $this->questionCollectionReads++;
        return $this->inner->all($collection);
    }

    public function find(string $collection, string $id): ?array { return $this->inner->find($collection, $id); }
    public function where(string $collection, array $criteria): array { return $this->inner->where($collection, $criteria); }
    public function create(string $collection, array $data): array { return $this->inner->create($collection, $data); }
    public function update(string $collection, string $id, array $data): ?array { return $this->inner->update($collection, $id, $data); }
    public function updateBatch(string $collection, array $ids, callable $updater): void { $this->inner->updateBatch($collection, $ids, $updater); }
    public function delete(string $collection, string $id): bool { return $this->inner->delete($collection, $id); }
    public function exists(string $collection, string $id): bool { return $this->inner->exists($collection, $id); }
    public function replace(string $collection, array $records): void { $this->inner->replace($collection, $records); }
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . SimulationStorageFixture::storagePath());
putenv('APP_SESSION_PATH=' . SimulationStorageFixture::sessionPath());
require dirname(__DIR__, 2) . '/bootstrap/app.php';

App::boot();
$database = App::database();
$storageProperty = new ReflectionProperty($database, 'storage');
$countingStorage = new FlashcardReadCountingStorage($storageProperty->getValue($database));
$storageProperty->setValue($database, $countingStorage);

session_id('bpflashcardreadpath' . bin2hex(random_bytes(4)));
if (!session_start()) throw new RuntimeException('Unable to start isolated flashcard read-path session.');
$_GET = ['board' => 'let', 'subject' => 'english'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/flashcards?board=let&subject=english';
ob_start();
$startedAt = hrtime(true);
FlashcardController::index();
$html = (string) ob_get_clean();
$elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;
$peakMemoryMb = memory_get_peak_usage(true) / 1_048_576;
session_write_close();

if (!str_contains($html, '<h1>Flashcards</h1>')
    || !str_contains($html, '<option value="let" selected')
    || !str_contains($html, '<option value="english" selected')) {
    throw new RuntimeException('Scoped production flashcard controller did not preserve its learner route output.');
}
if ($countingStorage->questionCollectionReads > 3) {
    throw new RuntimeException(sprintf(
        'Scoped flashcard page reread the full question collection %d times; expected no more than three reads for eligibility validation, selection, and exact lesson-reference resolution.',
        $countingStorage->questionCollectionReads
    ));
}

printf(
    "[PASS] Scoped flashcard controller reads the canonical question collection %d times; elapsed_ms=%.1f peak_mb=%.1f.\n",
    $countingStorage->questionCollectionReads,
    $elapsedMs,
    $peakMemoryMb
);
