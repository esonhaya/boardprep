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
            $topic = trim((string) ($record['id'] ?? ''));

            if ($topic === '') {
                continue;
            }

            $weakness[$topic] = [
                'correct' => (int) ($record['correct'] ?? 0),
                'wrong' => (int) ($record['wrong'] ?? 0),
            ];
        }

        return $weakness;
    }

    public static function save(array $data): void
    {
        $storage = App::storage();

        foreach ($storage->all(self::COLLECTION) as $record) {
            $id = trim((string) ($record['id'] ?? ''));

            if ($id !== '') {
                $storage->delete(self::COLLECTION, $id);
            }
        }

        foreach ($data as $topic => $stats) {
            $topic = trim((string) $topic);

            if ($topic === '') {
                continue;
            }

            $storage->create(self::COLLECTION, [
                'id' => $topic,
                'correct' => (int) ($stats['correct'] ?? 0),
                'wrong' => (int) ($stats['wrong'] ?? 0),
            ]);
        }
    }

    public static function clear(): void
    {
        $storage = App::storage();

        foreach ($storage->all(self::COLLECTION) as $record) {
            $id = trim((string) ($record['id'] ?? ''));

            if ($id !== '') {
                $storage->delete(self::COLLECTION, $id);
            }
        }
    }
}
