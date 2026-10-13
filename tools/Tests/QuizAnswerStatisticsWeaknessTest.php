<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\WeaknessService;
use App\Services\Learning\WeaknessStorageService;
use App\Services\Quiz\Result\QuizAnswerStatisticsRecorder;

$storagePath = sys_get_temp_dir() . '/boardprep-weakness-statistics-' . bin2hex(random_bytes(6));
if (!mkdir($storagePath, 0700, true)) {
    throw new RuntimeException('Unable to create isolated weakness/statistics fixture.');
}
file_put_contents($storagePath . '/weakness.json', "[]\n", LOCK_EX);
file_put_contents($storagePath . '/question-statistics.json', "[]\n", LOCK_EX);
$previousStoragePath = getenv('APP_STORAGE_PATH');
$previousDriver = getenv('DB_DRIVER');
putenv('APP_STORAGE_PATH=' . $storagePath);
putenv('DB_DRIVER=json');

try {
    $original = WeaknessStorageService::all();
    try {
        WeaknessService::clear();
        QuizAnswerStatisticsRecorder::record([
            ['id' => 'unanswered-1', 'topic' => 'Grammar', 'answer' => 'A'],
        ], []);

        if (WeaknessService::all() !== []) {
            throw new RuntimeException('unanswered questions were counted as weakness errors');
        }

        QuizAnswerStatisticsRecorder::record([
            ['id' => 'answered-1', 'topic' => 'Grammar', 'answer' => 'A'],
        ], ['answered-1' => 'B']);
        $weakness = WeaknessService::all();
        if (($weakness['Grammar']['wrong'] ?? null) !== 1
            || ($weakness['Grammar']['accuracy'] ?? null) !== 0) {
            throw new RuntimeException('answered incorrect questions no longer update weakness state');
        }
    } finally {
        WeaknessService::clear();
        WeaknessStorageService::save($original);
        if (WeaknessStorageService::all() !== $original) {
            throw new RuntimeException('weakness persistence did not restore its complete pre-test state');
        }
    }
} finally {
    if ($previousStoragePath === false) {
        putenv('APP_STORAGE_PATH');
    } else {
        putenv('APP_STORAGE_PATH=' . $previousStoragePath);
    }
    if ($previousDriver === false) {
        putenv('DB_DRIVER');
    } else {
        putenv('DB_DRIVER=' . $previousDriver);
    }
    foreach (glob($storagePath . '/*') ?: [] as $file) {
        if (is_file($file) || is_link($file)) @unlink($file);
    }
    @rmdir($storagePath);
}

echo "[PASS] unanswered quiz items do not inflate persisted weakness errors.\n";
