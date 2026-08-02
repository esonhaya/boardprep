<?php

declare(strict_types=1);

namespace App\Domains\Question\Actions;

class UpdateQuestion
{
    public static function execute(
        string|int $id,
        array $data
    ): array {
        return $data;
    }
}
