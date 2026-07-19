<?php

require_once
__DIR__ . "/../Core/Storage.php";

class WeaknessRepository
{
    private const FILE =
        "database/attempts/weakness.json";

    public static function all(): array
    {
        return Storage::read(
            self::FILE
        );
    }

    public static function save(
        array $data
    ): void
    {
        Storage::write(
            self::FILE,
            $data
        );
    }

    public static function clear(): void
    {
        self::save([]);
    }
}
