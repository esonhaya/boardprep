<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AttemptRepository;
use App\Services\Accounts\AccountLifecycleLock;
use App\Services\Learning\LearnerIdentityService;

class AttemptService
{
    public function __construct(
        private AttemptRepository $attempts
    ) {
    }

    public function save(
        array $attempt
    ): array {
        $attempt = $this->normalizeAttempt($attempt);
        $attempt['user_id'] = LearnerIdentityService::current();
        return AccountLifecycleLock::withWritableLearner($attempt['user_id'], function () use ($attempt): array {
            return $this->saveForLearner($attempt);
        });
    }

    private function saveForLearner(array $attempt): array
    {
        $sessionId = is_scalar($attempt['session_id'] ?? null)
            ? trim((string) $attempt['session_id'])
            : '';

        if ($sessionId !== '') {
            $existing = $this->attempts->bySessionId(
                $sessionId,
                $attempt['user_id']
            );
            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->attempts->create($attempt);
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
