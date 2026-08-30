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

file_put_contents($directory . '/questions.json', '["legacy",{"id":"safe","value":"kept"},null]');
if ($storage->find('questions', 'safe')['value'] !== 'kept'
    || $storage->where('questions', ['value' => 'kept'])[0]['id'] !== 'safe'
    || $storage->update('questions', 'safe', ['value' => 'updated'])['value'] !== 'updated') {
    throw new RuntimeException('malformed individual records broke safe record access');
}

$beforeInvalidWrite = (string) file_get_contents($directory . '/questions.json');
try {
    $storage->create('questions', ['id' => 'invalid', 'value' => NAN]);
    throw new RuntimeException('invalid JSON write was accepted');
} catch (StorageException) {
    if ((string) file_get_contents($directory . '/questions.json') !== $beforeInvalidWrite) {
        throw new RuntimeException('failed JSON write changed existing canonical data');
    }
}

foreach ([['id' => []], ['id' => ''], ['value' => 'missing-id']] as $invalid) {
    try {
        $storage->create('questions', $invalid);
        throw new RuntimeException('invalid primary key was accepted');
    } catch (StorageException) {
    }
}

$storage->replace('questions', [
    ['id' => 'a', 'value' => 'one'],
    ['id' => 'b', 'value' => 'two'],
]);
if (count($storage->all('questions')) !== 2
    || ($storage->find('questions', 'b')['value'] ?? null) !== 'two') {
    throw new RuntimeException('atomic collection replacement did not persist canonical records');
}

$beforeFailedReplace = (string) file_get_contents($directory . '/questions.json');
try {
    $storage->replace('questions', [
        ['id' => 'duplicate', 'value' => 'one'],
        ['id' => 'duplicate', 'value' => 'two'],
    ]);
    throw new RuntimeException('duplicate replacement IDs were accepted');
} catch (StorageException) {
    if ((string) file_get_contents($directory . '/questions.json') !== $beforeFailedReplace) {
        throw new RuntimeException('failed replacement changed existing canonical data');
    }
}

try {
    $storage->update('questions', 'a', ['id' => 'renamed']);
    throw new RuntimeException('primary key mutation was accepted');
} catch (StorageException) {
    if ($storage->find('questions', 'a') === null) {
        throw new RuntimeException('rejected primary-key mutation damaged original record');
    }
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

@unlink($directory . '/questions.json');
@unlink($directory . '/.storage.lock');
@rmdir($directory);

echo "[PASS] JSON storage preserves addressable IDs and safe malformed-row reads.\n";
echo "[PASS] JSON storage replacement is atomic and rejects invalid primary-key mutations.\n";
echo "[PASS] Failed JSON writes preserve existing canonical data.\n";
