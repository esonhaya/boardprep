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

        $directory = dirname($path);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new StorageException(
                    "Unable to create storage directory: {$directory}"
                );
            }
        }

        if (!file_exists($path)) {
            if (file_put_contents($path, '[]', LOCK_EX) === false) {
                throw new StorageException(
                    "Unable to create collection: {$collection}"
                );
            }
        }
    }

    private function readCollection(
        string $collection
    ): array {
        $this->ensureCollectionExists($collection);

        $contents = file_get_contents(
            $this->collectionPath($collection)
        );

        if ($contents === false) {
            throw new StorageException(
                "Unable to read collection: {$collection}"
            );
        }

        try {
            $data = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
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

    private function writeCollection(
        string $collection,
        array $records
    ): void {

        $path = $this->collectionPath($collection);

        $temp = $path . '.tmp';

        $json = json_encode(
            array_values($records),
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($temp, $json, LOCK_EX) === false) {
            throw new StorageException(
                "Unable to write collection: {$collection}"
            );
        }

        if (!rename($temp, $path)) {

            @unlink($temp);

            throw new StorageException(
                "Unable to replace collection: {$collection}"
            );

        }

    }

    private function value(
        array $record,
        string $key
    ): mixed {

        if (!str_contains($key, '.')) {
            return $record[$key] ?? null;
        }

        $value = $record;

        foreach (explode('.', $key) as $segment) {

            if (
                !is_array($value)
                || !array_key_exists($segment, $value)
            ) {
                return null;
            }

            $value = $value[$segment];

        }

        return $value;

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

            if (
                is_scalar($record[$this->primaryKey] ?? null)
                && (string) $record[$this->primaryKey] === $id
            ) {
                return $record;
            }

        }

        return null;

    }

    public function where(
        string $collection,
        array $criteria
    ): array {

        return array_values(
            array_filter(
                $this->readCollection($collection),
                function (
                    array $record
                ) use (
                    $criteria
                ): bool {

                    foreach (
                        $criteria as $key => $value
                    ) {

                        if (
                            $this->value(
                                $record,
                                $key
                            ) !== $value
                        ) {
                            return false;
                        }

                    }

                    return true;

                }
            )
        );

    }

    public function create(
        string $collection,
        array $data
    ): array {

        if (
            !isset(
                $data[$this->primaryKey]
            )
        ) {

            throw new StorageException(
                "Missing primary key '{$this->primaryKey}'."
            );

        }

        if (
            $this->exists(
                $collection,
                (string) $data[$this->primaryKey]
            )
        ) {

            throw new StorageException(
                "Duplicate primary key '{$data[$this->primaryKey]}'."
            );

        }

        $records =
            $this->readCollection($collection);

        $records[] = $data;

        $this->writeCollection(
            $collection,
            $records
        );

        return $data;

    }

    public function update(
        string $collection,
        string $id,
        array $data
    ): ?array {

        $records =
            $this->readCollection($collection);

        foreach ($records as $index => $record) {

            if (
                is_scalar($record[$this->primaryKey] ?? null)
                && (string) $record[$this->primaryKey] === $id
            ) {

                $records[$index] =
                    array_merge(
                        $record,
                        $data
                    );

                $this->writeCollection(
                    $collection,
                    $records
                );

                return $records[$index];

            }

        }

        return null;

    }

    public function delete(
        string $collection,
        string $id
    ): bool {

        $records =
            $this->readCollection($collection);

        foreach ($records as $index => $record) {

            if (
                is_scalar($record[$this->primaryKey] ?? null)
                && (string) $record[$this->primaryKey] === $id
            ) {

                unset($records[$index]);

                $this->writeCollection(
                    $collection,
                    $records
                );

                return true;

            }

        }

        return false;

    }

    public function exists(
        string $collection,
        string $id
    ): bool {

        return $this->find(
            $collection,
            $id
        ) !== null;

    }
}
