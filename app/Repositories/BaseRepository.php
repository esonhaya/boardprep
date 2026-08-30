<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\StorageInterface;

abstract class BaseRepository
{
    protected StorageInterface $storage;

    protected string $collection;

    public function __construct(
        StorageInterface $storage
    ) {
        $this->storage = $storage;
    }

    public function all(): array
    {
        return $this->storage->all(
            $this->collection
        );
    }

    public function find(
        string $id
    ): ?array {
        return $this->storage->find(
            $this->collection,
            $id
        );
    }

    public function where(
        array $criteria
    ): array {
        return $this->storage->where(
            $this->collection,
            $criteria
        );
    }

    public function create(
        array $data
    ): array {
        return $this->storage->create(
            $this->collection,
            $data
        );
    }

    public function update(
        string $id,
        array $data
    ): ?array {
        return $this->storage->update(
            $this->collection,
            $id,
            $data
        );
    }

    public function delete(
        string $id
    ): bool {
        return $this->storage->delete(
            $this->collection,
            $id
        );
    }

    public function exists(
        string $id
    ): bool {
        return $this->storage->exists(
            $this->collection,
            $id
        );
    }

    public function replaceAll(array $records): void
    {
        $this->storage->replace(
            $this->collection,
            $records
        );
    }
}
