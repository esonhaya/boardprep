<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Shared\TaxonomyStorageService;

$root = dirname(__DIR__, 2) . '/storage/';
$checks = [
    'boards.json' => TaxonomyStorageService::boards(),
    'subjects.json' => TaxonomyStorageService::subjects(),
    'taxonomy/domains.json' => TaxonomyStorageService::domains(),
    'taxonomy/topics.json' => TaxonomyStorageService::topics(),
    'taxonomy/concepts.json' => TaxonomyStorageService::concepts(),
    'board-subjects.json' => TaxonomyStorageService::boardSubjects(),
];

foreach ($checks as $relativePath => $serviceRecords) {
    $decoded = json_decode(
        (string) file_get_contents($root . $relativePath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $fileRecords = array_values(array_filter($decoded, 'is_array'));

    if ($serviceRecords !== $fileRecords) {
        throw new RuntimeException("taxonomy service diverged from canonical JSON storage at {$relativePath}");
    }
}

echo "[PASS] Taxonomy reads preserve canonical JSON-backed taxonomy collections.\n";
