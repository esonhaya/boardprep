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

    public function delete(string $collection, string $id): bool;

    public function exists(string $collection, string $id): bool;

    /**
     * Replace the entire collection as one storage operation.
     *
     * @param array<int, array<string, mixed>> $records
     */
    public function replace(string $collection, array $records): void;
}
