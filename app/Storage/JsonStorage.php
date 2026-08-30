<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;
use App\Exceptions\StorageException;
use JsonException;

class JsonStorage implements StorageInterface
{
    private string $storagePath;
    private string $primaryKey;

    public function __construct(string $storagePath, string $primaryKey = 'id')
    {
        $this->storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);
        $this->primaryKey = $primaryKey;
    }

    private function collectionPath(string $collection): string
    {
        $collection = trim(str_replace('\\', '/', $collection), '/');
        if ($collection === '' || str_contains($collection, '../')) {
            throw new StorageException('Invalid storage collection name.');
        }

        return $this->storagePath
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $collection)
            . '.json';
    }

    private function ensureCollectionExists(string $collection): void
    {
        $path = $this->collectionPath($collection);
        $directory = dirname($path);

        if (!is_dir($directory)
            && !mkdir($directory, 0777, true)
            && !is_dir($directory)) {
            throw new StorageException(
                "Unable to create storage directory: {$directory}"
            );
        }

        if (!file_exists($path)
            && file_put_contents($path, '[]', LOCK_EX) === false) {
            throw new StorageException(
                "Unable to create collection: {$collection}"
            );
        }
    }

    private function readCollection(string $collection): array
    {
        $this->ensureCollectionExists($collection);
        $contents = file_get_contents($this->collectionPath($collection));

        if ($contents === false) {
            throw new StorageException(
                "Unable to read collection: {$collection}"
            );
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StorageException(
                "Collection '{$collection}' contains malformed JSON.",
                0,
                $exception
            );
        }

        if (!is_array($data) || !array_is_list($data)) {
            throw new StorageException(
                "Collection '{$collection}' must contain a JSON list."
            );
        }

        return $data;
    }

    private function writeCollection(string $collection, array $records): void
    {
        $path = $this->collectionPath($collection);
        $this->ensureCollectionExists($collection);

        $temp = tempnam(dirname($path), basename($path) . '.tmp-');
        if ($temp === false) {
            throw new StorageException(
                "Unable to prepare collection write: {$collection}"
            );
        }

        try {
            $json = json_encode(
                array_values($records),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            if (file_put_contents($temp, $json, LOCK_EX) === false) {
                throw new StorageException(
                    "Unable to write collection: {$collection}"
                );
            }

            if (!rename($temp, $path)) {
                throw new StorageException(
                    "Unable to replace collection: {$collection}"
                );
            }
        } catch (JsonException $exception) {
            throw new StorageException(
                "Unable to encode collection: {$collection}",
                0,
                $exception
            );
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    private function withMutationLock(callable $callback): mixed
    {
        if (!is_dir($this->storagePath)
            && !mkdir($this->storagePath, 0777, true)
            && !is_dir($this->storagePath)) {
            throw new StorageException(
                "Unable to create storage directory: {$this->storagePath}"
            );
        }

        $lockPath = $this->storagePath . DIRECTORY_SEPARATOR . '.storage.lock';
        $handle = fopen($lockPath, 'c');
        if ($handle === false) {
            throw new StorageException('Unable to open storage mutation lock.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException('Unable to acquire storage mutation lock.');
            }

            return $callback();
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function primaryKeyValue(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new StorageException(
                "Primary key '{$this->primaryKey}' must be a string or integer."
            );
        }

        $id = trim((string) $value);
        if ($id === '') {
            throw new StorageException(
                "Primary key '{$this->primaryKey}' cannot be empty."
            );
        }

        return $id;
    }

    private function value(array $record, string $key): mixed
    {
        if (!str_contains($key, '.')) {
            return $record[$key] ?? null;
        }

        $value = $record;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function all(string $collection): array
    {
        return $this->readCollection($collection);
    }

    public function find(string $collection, string $id): ?array
    {
        foreach ($this->readCollection($collection) as $record) {
            if (!is_array($record)) {
                continue;
            }

            $value = $record[$this->primaryKey] ?? null;
            if (($value !== null) && is_scalar($value) && (string) $value === $id) {
                return $record;
            }
        }

        return null;
    }

    public function where(string $collection, array $criteria): array
    {
        return array_values(array_filter(
            $this->readCollection($collection),
            function (mixed $record) use ($criteria): bool {
                if (!is_array($record)) {
                    return false;
                }

                foreach ($criteria as $key => $value) {
                    if ($this->value($record, (string) $key) !== $value) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    public function create(string $collection, array $data): array
    {
        if (!array_key_exists($this->primaryKey, $data)) {
            throw new StorageException(
                "Missing primary key '{$this->primaryKey}'."
            );
        }

        $id = $this->primaryKeyValue($data[$this->primaryKey]);

        return $this->withMutationLock(function () use ($collection, $data, $id): array {
            $records = $this->readCollection($collection);

            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $existing = $record[$this->primaryKey] ?? null;
                if (is_scalar($existing) && (string) $existing === $id) {
                    throw new StorageException(
                        "Duplicate primary key '{$id}'."
                    );
                }
            }

            $records[] = $data;
            $this->writeCollection($collection, $records);

            return $data;
        });
    }

    public function update(string $collection, string $id, array $data): ?array
    {
        return $this->withMutationLock(function () use ($collection, $id, $data): ?array {
            $records = $this->readCollection($collection);

            foreach ($records as $index => $record) {
                if (!is_array($record)) {
                    continue;
                }

                $existingId = $record[$this->primaryKey] ?? null;
                if (!is_scalar($existingId) || (string) $existingId !== $id) {
                    continue;
                }

                if (array_key_exists($this->primaryKey, $data)) {
                    $requestedId = $this->primaryKeyValue($data[$this->primaryKey]);
                    if ($requestedId !== (string) $existingId) {
                        throw new StorageException(
                            "Primary key '{$this->primaryKey}' cannot be changed during update."
                        );
                    }
                }

                $records[$index] = array_merge($record, $data);
                $records[$index][$this->primaryKey] = $existingId;
                $this->writeCollection($collection, $records);

                return $records[$index];
            }

            return null;
        });
    }

    public function updateBatch(string $collection, array $ids, callable $updater): void
    {
        if ($ids === []) {
            return;
        }

        $this->withMutationLock(function () use ($collection, $ids, $updater): void {
            $records = $this->readCollection($collection);
            $positions = [];

            foreach ($records as $index => $record) {
                $value = is_array($record) ? ($record[$this->primaryKey] ?? null) : null;
                if (is_scalar($value)) {
                    $positions[(string) $value] = $index;
                }
            }

            $changed = false;
            foreach ($ids as $id) {
                $index = $positions[$id] ?? null;
                if ($index === null) {
                    continue;
                }

                $updated = $updater($records[$index]);
                $updated[$this->primaryKey] = $records[$index][$this->primaryKey];
                $records[$index] = $updated;
                $changed = true;
            }

            if ($changed) {
                $this->writeCollection($collection, $records);
            }
        });
    }

    public function delete(string $collection, string $id): bool
    {
        return $this->withMutationLock(function () use ($collection, $id): bool {
            $records = $this->readCollection($collection);

            foreach ($records as $index => $record) {
                if (!is_array($record)) {
                    continue;
                }

                $value = $record[$this->primaryKey] ?? null;
                if (is_scalar($value) && (string) $value === $id) {
                    unset($records[$index]);
                    $this->writeCollection($collection, $records);
                    return true;
                }
            }

            return false;
        });
    }

    public function exists(string $collection, string $id): bool
    {
        return $this->find($collection, $id) !== null;
    }

    public function replace(string $collection, array $records): void
    {
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new StorageException(
                    "Collection '{$collection}' replacement requires record arrays."
                );
            }

            if (!array_key_exists($this->primaryKey, $record)) {
                throw new StorageException(
                    "Collection '{$collection}' replacement record is missing primary key '{$this->primaryKey}'."
                );
            }

            $this->primaryKeyValue($record[$this->primaryKey]);
        }

        $this->withMutationLock(function () use ($collection, $records): void {
            $seen = [];
            foreach ($records as $record) {
                $id = $this->primaryKeyValue($record[$this->primaryKey]);
                if (isset($seen[$id])) {
                    throw new StorageException(
                        "Duplicate primary key '{$id}' in collection replacement."
                    );
                }
                $seen[$id] = true;
            }

            $this->writeCollection($collection, $records);
        });
    }
}
