<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AttemptRepository;

class AttemptService
{
    public function __construct(
        private AttemptRepository $attempts
    ) {
    }

    public function save(
        array $attempt
    ): array {
        return $this->attempts->create($attempt);
    }

    public function history(
        string $userId
    ): array {
        return $this->attempts->byUser($userId);
    }

    public function completed(
        string $userId
    ): array {
        return array_values(array_filter(
            $this->history($userId),
            static fn(array $attempt): bool =>
                ($attempt['completed'] ?? false) === true
        ));
    }
}
