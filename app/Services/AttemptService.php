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
        return $this->attempts->create(
            $this->normalizeAttempt($attempt)
        );
    }

    private function normalizeAttempt(
        array $attempt
    ): array {
        if (!isset($attempt["id"]) || trim((string) $attempt["id"]) === "") {
            $attempt["id"] = "attempt-" . bin2hex(random_bytes(8));
        }

        $attempt["completed"] =
            (bool) ($attempt["completed"] ?? true);

        return $attempt;
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
