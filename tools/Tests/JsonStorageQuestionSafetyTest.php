<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Exceptions\StorageException;
use App\Storage\JsonStorage;

$directory = sys_get_temp_dir() . '/boardprep-json-storage-' . getmypid();
mkdir($directory, 0777, true);
file_put_contents($directory . '/questions.json', '[{"id":1,"value":"old"}]');
$storage = new JsonStorage($directory);
if ($storage->find('questions', '1') === null
    || ($storage->update('questions', '1', ['value' => 'new'])['value'] ?? null) !== 'new') {
    throw new RuntimeException('numeric JSON ID was not addressable through repository string IDs');
}

file_put_contents($directory . '/questions.json', '{broken');
try {
    $storage->all('questions');
    throw new RuntimeException('malformed collection JSON was silently accepted');
} catch (StorageException $exception) {
    if (!str_contains($exception->getMessage(), 'malformed JSON')) {
        throw $exception;
    }
}

unlink($directory . '/questions.json');
rmdir($directory);

echo "[PASS] JSON question storage handles numeric IDs and malformed content safely.\n";
