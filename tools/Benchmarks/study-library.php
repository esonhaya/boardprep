<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$startedAt = hrtime(true);
$materials = \App\Services\Study\StudyLibraryService::all();
$elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;
$sourceCount = 0;
$questionLinkCount = 0;
foreach ($materials as $material) {
    $sourceCount += count((array) ($material['sources'] ?? []));
    $questionLinkCount += count((array) ($material['question_links'] ?? []));
}

$json = json_encode(
    $materials,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
);

printf(
    "MATERIALS=%d SOURCES=%d QUESTION_LINKS=%d OUTPUT_SHA256=%s ELAPSED_MS=%.1f PEAK_MB=%.1f\n",
    count($materials),
    $sourceCount,
    $questionLinkCount,
    hash('sha256', $json),
    $elapsedMilliseconds,
    memory_get_peak_usage(true) / 1_048_576
);
