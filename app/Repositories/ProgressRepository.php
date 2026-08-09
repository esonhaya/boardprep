<?php

namespace App\Repositories;

class ProgressRepository
{
    private JsonStorage $storage;

    public function __construct()
    {
        $this->storage = new JsonStorage(
            "./database/progress/progress.json",
            "user_id"
        );
    }

    public function all(): array
    {
        return $this->storage->all();
    }

    public function find(string $userId): ?array
    {
        return $this->storage->find($userId);
    }

    public function save(array $progress): void
    {
        $existing = $this->find($progress["user_id"]);

        if ($existing === null) {
            $this->storage->create($progress);
            return;
        }

        $this->storage->update(
            $progress["user_id"],
            $progress
        );
    }
}
