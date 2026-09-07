<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Database;
use App\Repositories\QuestionRepository;
use App\Repositories\QuestionStatisticsRepository;
use App\Storage\JsonStorage;

$root = sys_get_temp_dir() . '/boardprep-question-statistics-' . bin2hex(random_bytes(6));
$jsonPath = $root . '/json';
$sqliteContentPath = $root . '/sqlite-content';
$sqlitePath = $root . '/runtime/boardprep.sqlite';
$questions = [
    ['id' => 'q1', 'status' => 'approved', 'timesUsed' => 2, 'timesCorrect' => 1],
    ['id' => 'q2', 'status' => 'approved', 'timesUsed' => 4, 'timesIncorrect' => 3],
];

mkdir($jsonPath, 0777, true);
$json = new JsonStorage($jsonPath);
$json->replace('questions', $questions);
mkdir($sqliteContentPath, 0777, true);
(new JsonStorage($sqliteContentPath))->replace('questions', $questions);

try {
    $jsonRepository = new QuestionRepository($json);
    (new QuestionStatisticsRepository($json))->recordAnswers([
        ['question_id' => 'q1', 'correct' => true],
        ['question_id' => 'q1', 'correct' => null],
        ['question_id' => 'q2', 'correct' => false],
    ], $jsonRepository->all());

    if (json_decode(file_get_contents($jsonPath . '/questions.json'), true, 512, JSON_THROW_ON_ERROR) !== $questions) {
        throw new RuntimeException('JSON statistics recording mutated canonical questions');
    }
    $jsonView = $jsonRepository->find('q1');
    if (($jsonView['timesUsed'] ?? null) !== 4 || ($jsonView['timesCorrect'] ?? null) !== 2) {
        throw new RuntimeException('JSON statistics were not overlaid for production reads');
    }

    $database = new Database([
        'driver' => 'sqlite',
        'path' => $sqliteContentPath,
        'sqlite_path' => $sqlitePath,
        'migration_path' => dirname(__DIR__, 2) . '/database/migrations',
    ]);
    $sqliteStorage = $database->storage();
    $sqliteRepository = new QuestionRepository($sqliteStorage);
    (new QuestionStatisticsRepository($sqliteStorage))->recordAnswers([
        ['question_id' => 'q1', 'correct' => true],
    ], $sqliteRepository->all());

    if (!is_file($sqlitePath) || is_file($sqliteContentPath . '/question-statistics.json')) {
        throw new RuntimeException('SQLite statistics did not remain on the SQLite backend');
    }
    $sqliteView = $sqliteRepository->find('q1');
    if (($sqliteView['timesUsed'] ?? null) !== 3 || ($sqliteView['timesCorrect'] ?? null) !== 2) {
        throw new RuntimeException('SQLite statistics were not overlaid for production reads');
    }

    echo "[PASS] question statistics stay mutable and backend-scoped without canonical question mutation.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    @rmdir($root);
}
