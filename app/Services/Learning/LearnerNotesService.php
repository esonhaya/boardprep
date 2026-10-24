<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;
use App\Services\Accounts\AccountLifecycleLock;
use App\Services\Shared\TaxonomyStorageService;
use InvalidArgumentException;

/** Private, learner-owned notes. Note text is stored as plain text and is never executed. */
final class LearnerNotesService
{
    public const MAX_NOTES = 50;
    public const MAX_TITLE_CHARACTERS = 120;
    public const MAX_BODY_CHARACTERS = 12000;
    public const MAX_BODY_BYTES = 48000;

    private const COLLECTION = 'learner_notes';

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $userId = LearnerIdentityService::current();
        $notes = array_values(array_filter(
            App::storage()->where(self::COLLECTION, ['user_id' => $userId]),
            static fn(mixed $record): bool => is_array($record) && ($record['user_id'] ?? null) === $userId
        ));
        usort($notes, static fn(array $a, array $b): int => strcmp(
            (string) ($b['updated_at'] ?? ''),
            (string) ($a['updated_at'] ?? '')
        ));
        return $notes;
    }

    /** Returns null for malformed, missing, or another learner's note IDs. */
    public function find(string $id): ?array
    {
        if (preg_match('/\Anote-[a-f0-9]{32}\z/', $id) !== 1) {
            return null;
        }
        $record = App::storage()->find(self::COLLECTION, $id);
        return is_array($record)
            && ($record['id'] ?? null) === $id
            && ($record['user_id'] ?? null) === LearnerIdentityService::current()
                ? $record
                : null;
    }

    /** @return array<string,mixed> */
    public function save(array $input): array
    {
        $userId = LearnerIdentityService::current();
        $record = $this->validatedRecord($input);
        $requestedId = is_scalar($input['note_id'] ?? null) ? trim((string) $input['note_id']) : '';

        return AccountLifecycleLock::withWritableLearner($userId, function () use ($userId, $record, $requestedId): array {
            if ($requestedId !== '') {
                $existing = $this->find($requestedId);
                if (!is_array($existing)) {
                    throw new InvalidArgumentException('That note is unavailable in this learner session.');
                }
                $updated = array_merge($existing, $record, ['updated_at' => gmdate(DATE_ATOM)]);
                $saved = App::storage()->update(self::COLLECTION, $requestedId, $updated);
                if (!is_array($saved) || ($saved['user_id'] ?? null) !== $userId) {
                    throw new InvalidArgumentException('The note could not be saved. Please reopen it and try again.');
                }
                return $saved;
            }

            if (count($this->all()) >= self::MAX_NOTES) {
                throw new InvalidArgumentException('You have reached the limit of 50 saved notes. Delete one before creating another.');
            }
            $saved = App::storage()->create(self::COLLECTION, [
                'id' => 'note-' . bin2hex(random_bytes(16)),
                'user_id' => $userId,
                ...$record,
                'created_at' => gmdate(DATE_ATOM),
                'updated_at' => gmdate(DATE_ATOM),
            ]);
            if (($saved['user_id'] ?? null) !== $userId) {
                throw new InvalidArgumentException('The note could not be saved. Please try again.');
            }
            return $saved;
        });
    }

    public function delete(string $id): bool
    {
        $userId = LearnerIdentityService::current();
        return AccountLifecycleLock::withWritableLearner($userId, function () use ($id, $userId): bool {
            $record = $this->find($id);
            if (!is_array($record) || ($record['user_id'] ?? null) !== $userId) {
                return false;
            }
            return App::storage()->delete(self::COLLECTION, $id);
        });
    }

    /** @return array{title:string,body:string,board_id:string} */
    private function validatedRecord(array $input): array
    {
        foreach (['title', 'body', 'board_id'] as $key) {
            if (!is_scalar($input[$key] ?? null)) {
                throw new InvalidArgumentException('Enter a note title, examination board, and study notes.');
            }
        }
        $title = trim((string) $input['title']);
        $body = str_replace(["\r\n", "\r"], "\n", (string) $input['body']);
        $boardId = $this->canonicalBoardId(trim((string) $input['board_id']));

        if ($title === '' || self::characterLength($title) > self::MAX_TITLE_CHARACTERS) {
            throw new InvalidArgumentException('A note title is required and must be 120 characters or fewer.');
        }
        if (!self::validUtf8($title) || !self::validUtf8($body)) {
            throw new InvalidArgumentException('Use valid UTF-8 text for your title and notes.');
        }
        if (strlen($body) > self::MAX_BODY_BYTES || self::characterLength($body) > self::MAX_BODY_CHARACTERS) {
            throw new InvalidArgumentException('Notes are limited to 12,000 characters. Shorten the text and try again.');
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('Add some study notes before saving.');
        }
        if ($boardId === '') {
            throw new InvalidArgumentException('Choose a supported examination board for these notes.');
        }
        return ['title' => $title, 'body' => $body, 'board_id' => $boardId];
    }

    private function canonicalBoardId(string $value): string
    {
        foreach (TaxonomyStorageService::boards() as $board) {
            if (!is_array($board)) continue;
            foreach (['id', 'code', 'slug', 'short_code'] as $key) {
                if (is_scalar($board[$key] ?? null) && strcasecmp(trim((string) $board[$key]), $value) === 0) {
                    return (string) ($board['id'] ?? '');
                }
            }
        }
        return '';
    }

    private static function characterLength(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
        $count = preg_match_all('/./us', $value, $matches);
        return is_int($count) ? $count : strlen($value);
    }

    private static function validUtf8(string $value): bool
    {
        return preg_match('//u', $value) === 1;
    }
}
