<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\StorageInterface;

/** Storage access for unfinished account-owned exam drafts. */
final class ExamDraftRepository
{
    public const COLLECTION = 'exam-drafts';

    public function __construct(private StorageInterface $storage)
    {
    }

    /** @return list<array<string,mixed>> */
    public function byLearner(string $learnerId): array
    {
        return array_values(array_filter(
            $this->storage->where(self::COLLECTION, ['user_id' => $learnerId]),
            'is_array'
        ));
    }

    /** @return array<string,mixed>|null */
    public function findForLearner(string $draftId, string $learnerId): ?array
    {
        $draft = $this->storage->find(self::COLLECTION, $draftId);
        if (!is_array($draft) || ($draft['user_id'] ?? null) !== $learnerId) {
            return null;
        }
        return $draft;
    }

    /** @param array<string,mixed> $draft @return array<string,mixed> */
    public function create(array $draft): array
    {
        return $this->storage->create(self::COLLECTION, $draft);
    }

    /** @param array<string,mixed> $changes @return array<string,mixed>|null */
    public function update(string $draftId, array $changes): ?array
    {
        return $this->storage->update(self::COLLECTION, $draftId, $changes);
    }

    public function delete(string $draftId): bool
    {
        return $this->storage->delete(self::COLLECTION, $draftId);
    }
}
