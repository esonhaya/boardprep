<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\WeaknessService;
use App\Services\Learning\WeaknessStorageService;
use App\Services\Quiz\Result\QuizAnswerStatisticsRecorder;

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

echo "[PASS] unanswered quiz items do not inflate persisted weakness errors.\n";
