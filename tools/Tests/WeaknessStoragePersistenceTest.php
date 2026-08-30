<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Learning\WeaknessStorageService;

$original = WeaknessStorageService::all();

try {
    WeaknessStorageService::save([
        'Grammar' => ['correct' => 3, 'wrong' => 1],
        'Reading' => ['correct' => 1, 'wrong' => 3],
    ]);

    $stored = WeaknessStorageService::all();
    if (($stored['Grammar']['accuracy'] ?? null) !== 75
        || ($stored['Reading']['accuracy'] ?? null) !== 25
        || count($stored) !== 2) {
        throw new RuntimeException('weakness replacement did not persist the complete canonical set');
    }

    WeaknessStorageService::save([
        'Grammar' => ['correct' => 4, 'wrong' => 0],
    ]);

    $replaced = WeaknessStorageService::all();
    if (isset($replaced['Reading'])
        || ($replaced['Grammar']['accuracy'] ?? null) !== 100) {
        throw new RuntimeException('weakness save retained stale records from the prior state');
    }

    WeaknessStorageService::clear();
    if (WeaknessStorageService::all() !== []) {
        throw new RuntimeException('weakness clear did not atomically replace the collection with an empty set');
    }
} finally {
    WeaknessStorageService::save($original);
}

echo "[PASS] Weakness persistence replaces complete learner state without stale-record residue.\n";
