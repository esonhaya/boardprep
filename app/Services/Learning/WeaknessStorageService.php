<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;

final class WeaknessStorageService
{
    private const FILE =
        'database/attempts/weakness.json';

    public static function all(): array
    {
        return App::storage()->read(
            self::FILE
        );
    }

    public static function save(
        array $data
    ): void {

        App::storage()->write(
            self::FILE,
            $data
        );

    }

    public static function clear(): void
    {

        self::save([]);

    }
}
