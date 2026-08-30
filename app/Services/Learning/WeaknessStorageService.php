<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;

final class WeaknessStorageService
{
    private const COLLECTION = 'weakness';

    public static function all(): array
    {
        $records = App::storage()->all(self::COLLECTION);
        $weakness = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $rawTopic = $record['id'] ?? '';
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
        $records = [];

        foreach ($data as $topic => $stats) {
            $topic = trim((string) $topic);
            if ($topic === '' || !is_array($stats)) {
                continue;
            }

            $records[] = [
                'id' => $topic,
                'correct' => max(0, (int) ($stats['correct'] ?? 0)),
                'wrong' => max(0, (int) ($stats['wrong'] ?? 0)),
            ];
        }

        App::storage()->replace(self::COLLECTION, $records);
    }

    public static function clear(): void
    {
        App::storage()->replace(self::COLLECTION, []);
    }
}
