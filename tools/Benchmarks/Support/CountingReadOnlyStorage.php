<?php

declare(strict_types=1);

use App\Contracts\StorageInterface;

/** Read-only benchmark wrapper. Any attempted learner/storage mutation fails closed. */
final class CountingReadOnlyStorage implements StorageInterface
{
    /** @var array<string,int> */
    public array $allReads = [];

    public function __construct(private readonly StorageInterface $delegate)
    {
    }

    public function all(string $collection): array
    {
        $this->allReads[$collection] = ($this->allReads[$collection] ?? 0) + 1;
        return $this->delegate->all($collection);
    }

    public function find(string $collection, string $id): ?array { return $this->delegate->find($collection, $id); }
    public function where(string $collection, array $criteria): array { return $this->delegate->where($collection, $criteria); }
    public function exists(string $collection, string $id): bool { return $this->delegate->exists($collection, $id); }

    public function create(string $collection, array $data): array { throw new RuntimeException('Read-only benchmark blocked create.'); }
    public function update(string $collection, string $id, array $data): ?array { throw new RuntimeException('Read-only benchmark blocked update.'); }
    public function updateBatch(string $collection, array $ids, callable $updater): void { throw new RuntimeException('Read-only benchmark blocked updateBatch.'); }
    public function delete(string $collection, string $id): bool { throw new RuntimeException('Read-only benchmark blocked delete.'); }
    public function replace(string $collection, array $records): void { throw new RuntimeException('Read-only benchmark blocked replace.'); }
}
