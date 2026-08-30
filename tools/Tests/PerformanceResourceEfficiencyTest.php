<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Repositories\QuestionRepository;
use App\Storage\JsonStorage;

$directory = sys_get_temp_dir() . '/boardprep-performance-' . bin2hex(random_bytes(6));
mkdir($directory, 0777, true);

try {
    file_put_contents($directory . '/questions.json', json_encode([
        ['id' => 'q1', 'timesUsed' => 2, 'timesCorrect' => 1],
        ['id' => 'q2', 'timesUsed' => 4, 'timesIncorrect' => 3],
        'malformed legacy row',
    ], JSON_THROW_ON_ERROR));

    $repository = new QuestionRepository(new JsonStorage($directory));
    $repository->updateStatistics([
        ['question_id' => 'q1', 'correct' => true],
        ['question_id' => 'missing', 'correct' => false],
        ['question_id' => 'q2', 'correct' => false],
        ['question_id' => 'q1', 'correct' => null],
    ]);

    $records = json_decode(
        file_get_contents($directory . '/questions.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (($records[0]['timesUsed'] ?? null) !== 4
        || ($records[0]['timesCorrect'] ?? null) !== 2
        || ($records[0]['timesIncorrect'] ?? null) !== 0) {
        throw new RuntimeException('batched duplicate question statistics changed behavior');
    }
    if (($records[1]['timesUsed'] ?? null) !== 5
        || ($records[1]['timesIncorrect'] ?? null) !== 4) {
        throw new RuntimeException('batched incorrect statistics changed behavior');
    }
    if (($records[2] ?? null) !== 'malformed legacy row') {
        throw new RuntimeException('batched statistics discarded malformed legacy data');
    }
} finally {
    @unlink($directory . '/questions.json');
    @unlink($directory . '/.storage.lock');
    @rmdir($directory);
}

echo "[PASS] Quiz statistics batch one question-bank mutation without changing counters.\n";
