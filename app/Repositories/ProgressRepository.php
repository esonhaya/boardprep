<?php

class ProgressRepository
{
    private const FILE =
        __DIR__ . "/../../storage/progress.json";

    public static function all(): array
    {
        if (!file_exists(self::FILE)) {
            return [];
        }

        $progress = json_decode(
            file_get_contents(self::FILE),
            true
        );

        return is_array($progress)
            ? $progress
            : [];
    }

    public static function save(array $progress): void
    {
        file_put_contents(
            self::FILE,
            json_encode(
                $progress,
                JSON_PRETTY_PRINT
            )
        );
    }

    public static function clear(): void
    {
        self::save([]);
    }
}
