<?php

declare(strict_types=1);

namespace App\Core;

use App\Contracts\StorageInterface;

class Storage
{
    private static function storage(): StorageInterface
    {
        return App::storage();
    }

    public static function read(
        string $file
    ): array {
        return self::storage()->read($file);
    }

    public static function write(
        string $file,
        array $data
    ): void {
        self::storage()->write($file, $data);
    }
}
