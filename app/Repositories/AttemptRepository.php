<?php

class AttemptRepository
{
    private const FILE = __DIR__ . "/../../storage/attempts.json";

    public static function all(): array
    {
        if (!file_exists(self::FILE)) {
            return [];
        }

        $data = json_decode(file_get_contents(self::FILE), true);

        return is_array($data) ? $data : [];
    }

    public static function save(array $attempt): void
    {
        $attempts = self::all();

        $attempts[] = $attempt;

        file_put_contents(
            self::FILE,
            json_encode($attempts, JSON_PRETTY_PRINT)
        );
    }
}
