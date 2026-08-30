<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/MemoryStorage.php';

use Tools\Tests\MemoryStorage;

$storage = new MemoryStorage();
$storage->create('records', ['id' => 'old', 'value' => 1]);
$storage->replace('records', [
    ['id' => 'new-1', 'value' => 2],
    ['id' => 'new-2', 'value' => 3],
]);

if ($storage->find('records', 'old') !== null
    || count($storage->all('records')) !== 2
    || ($storage->find('records', 'new-2')['value'] ?? null) !== 3) {
    throw new RuntimeException('StorageInterface replacement contract is not honored by test storage');
}

try {
    $storage->replace('records', [
        ['id' => 'same'],
        ['id' => 'same'],
    ]);
    throw new RuntimeException('duplicate replacement IDs were accepted by test storage');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'Duplicate replacement id')) {
        throw $exception;
    }
}

echo "[PASS] Storage replacement contract replaces a complete collection without append residue.\n";
