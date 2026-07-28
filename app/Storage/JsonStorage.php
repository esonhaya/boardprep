<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;
use App\Exceptions\StorageException;

class JsonStorage implements StorageInterface
{
    private string $storagePath;

    private string $primaryKey;

    public function __construct(
        string $storagePath,
        string $primaryKey = 'id'
    ) {
        $this->storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);
        $this->primaryKey = $primaryKey;
    }

    private function collectionPath(
        string $collection
    ): string {
        return $this->storagePath
            . DIRECTORY_SEPARATOR
            . $collection
            . '.json';
    }

    private function ensureCollectionExists(
        string $collection
    ): void {
        $path = $this->collectionPath($collection);

        if (!file_exists($path)) {
            file_put_contents($path, "[]");
        }
    }

    private function readCollection(
        string $collection
    ): array {
        $this->ensureCollectionExists($collection);

        $path = $this->collectionPath($collection);

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new StorageException(
                "Unable to read collection: {$collection}"
            );
        }

        $data = json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return is_array($data)
            ? $data
            : [];
    }

    private function writeCollection(
        string $collection,
        array $records
    ): void {
        $path = $this->collectionPath($collection);

        $tempPath = $path . '.tmp';

        $json = json_encode(
            array_values($records),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($tempPath, $json, LOCK_EX) === false) {
            throw new StorageException(
                "Unable to write collection: {$collection}"
            );
        }

        if (!rename($tempPath, $path)) {
            @unlink($tempPath);

            throw new StorageException(
                "Unable to replace collection: {$collection}"
            );
        }
    }

    public function all(
        string $collection
    ): array {
        return $this->readCollection($collection);
    }

    public function find(
        string $collection,
        string $id
    ): ?array {
        foreach ($this->readCollection($collection) as $record) {
            if (($record[$this->primaryKey] ?? null) === $id) {
                return $record;
            }
        }

        return null;
    }

    public function where(
        string $collection,
        array $criteria
    ): array {
        return array_values(array_filter(
            $this->readCollection($collection),
            function (array $record) use ($criteria): bool {
                foreach ($criteria as $key => $value) {
                    if (($record[$key] ?? null) !== $value) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    public function create(
        string $collection,
        array $data
    ): array {
        $records = $this->readCollection($collection);

        $records[] = $data;

        $this->writeCollection($collection, $records);

        return $data;
    }

    public function update(
        string $collection,
        string $id,
        array $data
    ): ?array {
        $records = $this->readCollection($collection);

        foreach ($records as $index => $record) {
            if (($record[$this->primaryKey] ?? null) === $id) {
                $records[$index] = array_merge($record, $data);

                $this->writeCollection($collection, $records);

                return $records[$index];
            }
        }

        return null;
    }

    public function delete(
        string $collection,
        string $id
    ): bool {
        $records = $this->readCollection($collection);

        foreach ($records as $index => $record) {
            if (($record[$this->primaryKey] ?? null) === $id) {
                unset($records[$index]);

                $this->writeCollection($collection, $records);

                return true;
            }
        }

        return false;
    }

    public function exists(
        string $collection,
        string $id
    ): bool {
        return $this->find($collection, $id) !== null;
    }
}
