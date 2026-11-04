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
    /** @var list<string> */
    public array $questionReadCallers = [];
    /** @var array<string, int> */
    public array $allReads = [];

    public function __construct(private StorageInterface $inner)
    {
    }

    public function all(string $collection): array
    {
        $this->allReads[$collection] = ($this->allReads[$collection] ?? 0) + 1;
        if ($collection === 'questions') {
            $this->questionCollectionReads++;
            if (getenv('BOARDPREP_TRACE_QUESTION_READS') === '1') {
                foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $frame) {
                    $file = (string) ($frame['file'] ?? '');
                    if ($file !== '' && str_contains($file, '/app/')) {
                        $this->questionReadCallers[] = basename($file) . ':' . (int) ($frame['line'] ?? 0)
                            . ' ' . (string) ($frame['class'] ?? '') . (string) ($frame['type'] ?? '')
                            . (string) ($frame['function'] ?? '');
                        break;
                    }
                }
            }
        }
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
$fullQuestionCount = count(App::storage()->all('questions'));
$countingStorage->allReads = [];
$countingStorage->questionCollectionReads = 0;
$runs = [];
for ($run = 0; $run < 3; $run++) {
    $readsBefore = $countingStorage->allReads;
    ob_start();
    $startedAt = hrtime(true);
    FlashcardController::index();
    $html = (string) ob_get_clean();
    $requestReads = [];
    foreach ($countingStorage->allReads as $collection => $count) {
        $delta = $count - ($readsBefore[$collection] ?? 0);
        if ($delta > 0) {
            $requestReads[$collection] = $delta;
        }
    }
    $runs[] = [
        'elapsed_ms' => (hrtime(true) - $startedAt) / 1_000_000,
        'question_reads' => $countingStorage->questionCollectionReads - array_sum(array_column($runs, 'question_reads')),
        'all_reads' => $requestReads,
    ];
}
$peakMemoryMb = memory_get_peak_usage(true) / 1_048_576;
session_write_close();
$availableCount = null;
if (preg_match('/<span class="stat-label">Available in this focus<\/span>\s*<strong class="stat-value">(\d+)<\/strong>/s', $html, $availableMatch) === 1) {
    $availableCount = (int) $availableMatch[1];
}

if (!str_contains($html, '<h1>Flashcards</h1>')
    || !str_contains($html, '<option value="let" selected')
    || !str_contains($html, '<option value="english" selected')
    || $availableCount === null) {
    throw new RuntimeException('Scoped production flashcard controller did not preserve its learner route output.');
}
if (array_sum(array_column($runs, 'question_reads')) > 3) {
    throw new RuntimeException(sprintf(
        'Three scoped flashcard page requests reread the full question collection %d times; expected one read per request.',
        $countingStorage->questionCollectionReads
    ));
}

foreach ($runs as $index => $run) {
    if ($run['question_reads'] > 1) {
        throw new RuntimeException('Scoped flashcard page reloaded the canonical bank after deriving filter options.');
    }
    foreach (['blueprints', 'question-eligibility', 'question-reuse-overrides'] as $collection) {
        if (($run['all_reads'][$collection] ?? 0) !== 1) {
            throw new RuntimeException(sprintf(
                'Scoped flashcard request %d read the %s collection %d times; expected exactly once.',
                $index + 1,
                $collection,
                $run['all_reads'][$collection]
            ));
        }
    }
    if ($index === 0) {
        foreach (['question-reuse-metadata', 'concept-derived-eligibility', 'study-question-links'] as $collection) {
            if (($run['all_reads'][$collection] ?? 0) !== 1) {
                throw new RuntimeException(sprintf(
                    'Cold scoped flashcard request read %s %d times; expected one shared request snapshot.',
                    $collection,
                    $run['all_reads'][$collection] ?? 0
                ));
            }
        }
        printf(
            "[PASS] cold collection snapshots: reuse_metadata=%d derived_eligibility=%d study_links=%d.\n",
            $run['all_reads']['question-reuse-metadata'],
            $run['all_reads']['concept-derived-eligibility'],
            $run['all_reads']['study-question-links']
        );
    }
    printf("[PASS] flashcard request %d: reads=%d elapsed_ms=%.1f\n", $index + 1, $run['question_reads'], $run['elapsed_ms']);
}
$requestQuestionReads = $countingStorage->questionCollectionReads;
$filtersMethod = new ReflectionMethod(FlashcardController::class, 'filters');
$parsedFilters = $filtersMethod->invoke(null, ['board' => 'let', 'subject' => 'english']);
$scopedQuestionCount = count((array) ($parsedFilters['question_snapshot'] ?? []));
if ($scopedQuestionCount < 1 || $scopedQuestionCount >= $fullQuestionCount) {
    throw new RuntimeException(sprintf(
        'Scoped flashcard filters retained %d of %d canonical questions; expected a strictly smaller eligible selection scope.',
        $scopedQuestionCount,
        $fullQuestionCount
    ));
}
printf("[PASS] flashcard controller available_cards=%d 3-run peak_mb=%.1f total_question_reads=%d.\n", $availableCount, $peakMemoryMb, $requestQuestionReads);
printf("[PASS] flashcard selection scope reduced from %d to %d canonical questions.\n", $fullQuestionCount, $scopedQuestionCount);
printf("[PASS] flashcard response sha256=%s bytes=%d.\n", hash('sha256', $html), strlen($html));
if (getenv('BOARDPREP_TRACE_QUESTION_READS') === '1') {
    foreach ($countingStorage->questionReadCallers as $caller) echo "[READ] {$caller}\n";
}
