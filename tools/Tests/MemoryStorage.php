<?php

declare(strict_types=1);

namespace Tools\Tests;

use App\Contracts\StorageInterface;

final class MemoryStorage implements StorageInterface
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $collections = [];

    public function all(string $collection): array
    {
        return array_values(
            $this->collections[$collection] ?? []
        );
    }

    public function find(
        string $collection,
        string $id
    ): ?array {
        return $this->collections[$collection][$id] ?? null;
    }

    public function where(
        string $collection,
        array $criteria
    ): array {
        $results = [];

        foreach (
            $this->collections[$collection] ?? []
            as $record
        ) {
            $matches = true;

            foreach ($criteria as $key => $expected) {
                if (($record[$key] ?? null) !== $expected) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $results[] = $record;
            }
        }

        return $results;
    }

    public function create(
        string $collection,
        array $data
    ): array {
        $id = (string) ($data['id'] ?? '');

        if ($id === '') {
            throw new \RuntimeException(
                "MemoryStorage requires an id."
            );
        }

        if ($this->exists($collection, $id)) {
            throw new \RuntimeException(
                "Record already exists: {$collection}/{$id}"
            );
        }

        $this->collections[$collection][$id] = $data;

        return $data;
    }

    public function update(
        string $collection,
        string $id,
        array $data
    ): ?array {
        if (!$this->exists($collection, $id)) {
            return null;
        }

        $this->collections[$collection][$id] = array_merge(
            $this->collections[$collection][$id],
            $data
        );

        return $this->collections[$collection][$id];
    }

    public function delete(
        string $collection,
        string $id
    ): bool {
        if (!$this->exists($collection, $id)) {
            return false;
        }

        unset(
            $this->collections[$collection][$id]
        );

        return true;
    }

    public function exists(
        string $collection,
        string $id
    ): bool {
        return isset(
            $this->collections[$collection][$id]
        );
    }

    public function replace(string $collection, array $records): void
    {
        $replacement = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new \RuntimeException('MemoryStorage replacement requires record arrays.');
            }

            $id = (string) ($record['id'] ?? '');
            if ($id === '') {
                throw new \RuntimeException('MemoryStorage replacement requires an id.');
            }

            if (isset($replacement[$id])) {
                throw new \RuntimeException("Duplicate replacement id: {$id}");
            }

            $replacement[$id] = $record;
        }

        $this->collections[$collection] = $replacement;
    }

    public function reset(): void
    {
        $this->collections = [];
    }
}
