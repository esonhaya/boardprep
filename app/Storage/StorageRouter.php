<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;

final class StorageRouter implements StorageInterface
{
    /** @param array<string, StorageInterface> $routes */
    public function __construct(private StorageInterface $default, private array $routes)
    {
    }

    private function storage(string $collection): StorageInterface
    {
        return $this->routes[$collection] ?? $this->default;
    }

    public function all(string $collection): array { return $this->storage($collection)->all($collection); }
    public function find(string $collection, string $id): ?array { return $this->storage($collection)->find($collection, $id); }
    public function where(string $collection, array $criteria): array { return $this->storage($collection)->where($collection, $criteria); }
    public function create(string $collection, array $data): array { return $this->storage($collection)->create($collection, $data); }
    public function update(string $collection, string $id, array $data): ?array { return $this->storage($collection)->update($collection, $id, $data); }
    public function updateBatch(string $collection, array $ids, callable $updater): void { $this->storage($collection)->updateBatch($collection, $ids, $updater); }
    public function delete(string $collection, string $id): bool { return $this->storage($collection)->delete($collection, $id); }
    public function exists(string $collection, string $id): bool { return $this->storage($collection)->exists($collection, $id); }
    public function replace(string $collection, array $records): void { $this->storage($collection)->replace($collection, $records); }
}
