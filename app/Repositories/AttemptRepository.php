<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\StorageInterface;

class AttemptRepository extends BaseRepository
{
    protected string $collection = 'attempts';

    public function __construct(
        StorageInterface $storage
    ) {
        parent::__construct($storage);
    }

    public function byUser(
        string $userId
    ): array {
        return $this->where([
            'user_id' => $userId,
        ]);
    }

    public function byMode(
        string $mode
    ): array {
        return $this->where([
            'mode' => $mode,
        ]);
    }

    public function bySessionId(string $sessionId): ?array
    {
        foreach ($this->all() as $attempt) {
            if (!is_array($attempt)) {
                continue;
            }

            $value = $attempt['session_id'] ?? null;
            if (is_scalar($value) && trim((string) $value) === $sessionId) {
                return $attempt;
            }
        }

        return null;
    }

    public function completed(): array
    {
        return $this->where([
            'completed' => true,
        ]);
    }
}
