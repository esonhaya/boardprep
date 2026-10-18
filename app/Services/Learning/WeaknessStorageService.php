<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;
use App\Services\Accounts\AccountLifecycleLock;

final class WeaknessStorageService
{
    private const COLLECTION = 'weakness';

    public static function all(): array
    {
        $userId = LearnerIdentityService::current();
        $records = App::storage()->all(self::COLLECTION);
        $weakness = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            if ((string) ($record['user_id'] ?? '') !== $userId) {
                continue;
            }

            $rawTopic = $record['topic'] ?? '';
            if (!is_scalar($rawTopic)) {
                continue;
            }

            $topic = trim((string) $rawTopic);
            if ($topic === '') {
                continue;
            }

            $correct = max(0, (int) ($record['correct'] ?? 0));
            $wrong = max(0, (int) ($record['wrong'] ?? 0));
            $total = $correct + $wrong;

            $weakness[$topic] = [
                'topic' => $topic,
                'correct' => $correct,
                'wrong' => $wrong,
                'accuracy' => $total > 0
                    ? (int) round(($correct / $total) * 100)
                    : 0,
            ];
        }

        return $weakness;
    }

    public static function save(array $data): void
    {
        $userId = LearnerIdentityService::current();
        AccountLifecycleLock::withWritableLearner($userId, static function () use ($data, $userId): void {
            self::saveForLearner($userId, $data);
        });
    }

    private static function saveForLearner(string $userId, array $data): void
    {
        $records = array_values(array_filter(
            App::storage()->all(self::COLLECTION),
            static fn(mixed $record): bool => is_array($record)
                && (string) ($record['user_id'] ?? '') !== $userId
        ));

        foreach ($data as $topic => $stats) {
            $topic = trim((string) $topic);
            if ($topic === '' || !is_array($stats)) {
                continue;
            }

            $records[] = [
                'id' => self::recordId($userId, $topic),
                'user_id' => $userId,
                'topic' => $topic,
                'correct' => max(0, (int) ($stats['correct'] ?? 0)),
                'wrong' => max(0, (int) ($stats['wrong'] ?? 0)),
            ];
        }

        App::storage()->replace(self::COLLECTION, $records);
    }

    public static function clear(): void
    {
        $userId = LearnerIdentityService::current();
        AccountLifecycleLock::withWritableLearner($userId, static function () use ($userId): void {
            App::storage()->replace(
                self::COLLECTION,
                array_values(array_filter(
                    App::storage()->all(self::COLLECTION),
                    static fn(mixed $record): bool => is_array($record)
                        && (string) ($record['user_id'] ?? '') !== $userId
                ))
            );
        });
    }

    private static function recordId(string $userId, string $topic): string
    {
        return $userId . '|' . $topic;
    }
}
