<?php

declare(strict_types=1);

namespace App\Contracts;

interface StorageInterface
{
    public function all(string $collection): array;

    public function find(string $collection, string $id): ?array;

    public function where(string $collection, array $criteria): array;

    public function create(string $collection, array $data): array;

    public function update(string $collection, string $id, array $data): ?array;

    /**
     * Apply an updater to each requested record as one storage mutation.
     *
     * @param array<int, string> $ids
     * @param callable(array<string, mixed>): array<string, mixed> $updater
     */
    public function updateBatch(string $collection, array $ids, callable $updater): void;

    public function delete(string $collection, string $id): bool;

    public function exists(string $collection, string $id): bool;

    /**
     * Replace the entire collection as one storage operation.
     *
     * @param array<int, array<string, mixed>> $records
     */
    public function replace(string $collection, array $records): void;
}
