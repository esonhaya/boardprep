<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Learning\LearnerIdentityService;
use App\Contracts\StorageInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final class FlashcardStateRepository extends BaseRepository
{
    protected string $collection = 'flashcard_state';

    public function __construct(StorageInterface $storage)
    {
        parent::__construct($storage);
    }

    public function all(): array
    {
        $userId = $this->userId();

        return array_values(array_filter(
            parent::all(),
            static fn(mixed $record): bool => is_array($record)
                && (string) ($record['user_id'] ?? '') === $userId
        ));
    }

    public function find(string $cardKey): ?array
    {
        $record = parent::find($this->recordId($cardKey));

        return is_array($record) && ($record['user_id'] ?? '') === $this->userId()
            ? $record
            : null;
    }

    public function where(array $criteria): array
    {
        $criteria['user_id'] = $this->userId();

        return array_values(array_filter(
            parent::all(),
            static function (mixed $record) use ($criteria): bool {
                if (!is_array($record)) {
                    return false;
                }
                foreach ($criteria as $key => $expected) {
                    if (($record[$key] ?? null) !== $expected) {
                        return false;
                    }
                }
                return true;
            }
        ));
    }

    public function create(array $data): array
    {
        return $this->save($data);
    }

    public function update(string $cardKey, array $data): ?array
    {
        $existing = $this->find($cardKey);
        if ($existing === null) {
            return null;
        }

        $data['card_key'] = $cardKey;

        return $this->save(array_merge($existing, $data));
    }

    public function save(array $data): array
    {
        $cardKey = $this->required($data['card_key'] ?? null, 'card_key');
        $userId = $this->userId();
        $now = (new DateTimeImmutable('now'))->format(DATE_ATOM);
        $existing = $this->find($cardKey);

        $state = array_merge([
            'id' => $this->recordId($cardKey),
            'user_id' => $userId,
            'card_key' => $cardKey,
            'question_id' => '',
            'card_type' => '',
            'review_count' => 0,
            'last_reviewed_at' => null,
            'next_due_at' => null,
            'interval_days' => 0,
            'ease_factor' => 2.5,
            'lapses' => 0,
            'state' => 'new',
            'suspended' => false,
            'algorithm_version' => 'sm2-like-v1',
            'created_at' => $existing['created_at'] ?? $now,
            'updated_at' => $now,
        ], $existing ?? [], $data);

        // The caller can never choose another learner's owner or storage ID.
        $state['id'] = $this->recordId($cardKey);
        $state['user_id'] = $userId;
        $state['card_key'] = $cardKey;

        if ($existing === null) {
            return parent::create($state);
        }

        return parent::update($state['id'], $state) ?? $state;
    }

    public function delete(string $cardKey): bool
    {
        return parent::delete($this->recordId($cardKey));
    }

    public function exists(string $cardKey): bool
    {
        return $this->find($cardKey) !== null;
    }

    public function setSuspended(string $cardKey, bool $suspended): ?array
    {
        $state = $this->find($cardKey);
        if ($state === null) {
            return null;
        }

        $state['suspended'] = $suspended;

        return $this->save($state);
    }

    public function replaceAll(array $records): void
    {
        $userId = $this->userId();
        $others = array_values(array_filter(
            parent::all(),
            static fn(mixed $record): bool => is_array($record)
                && (string) ($record['user_id'] ?? '') !== $userId
        ));
        $mine = array_map(fn(array $record): array => $this->saveData($record), $records);

        parent::replaceAll(array_merge($others, $mine));
    }

    private function saveData(array $data): array
    {
        $cardKey = $this->required($data['card_key'] ?? null, 'card_key');
        $data['id'] = $this->recordId($cardKey);
        $data['user_id'] = $this->userId();
        return $data;
    }

    private function userId(): string
    {
        return LearnerIdentityService::current();
    }

    private function recordId(string $cardKey): string
    {
        return $this->userId() . '|' . $cardKey;
    }

    private function required(mixed $value, string $label): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            throw new InvalidArgumentException("Missing {$label}.");
        }

        return trim((string) $value);
    }
}
