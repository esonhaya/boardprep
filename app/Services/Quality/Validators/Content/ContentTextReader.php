<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators\Content;

final class ContentTextReader
{
    public static function read(array $question, string $field): string
    {
        $value = $question[$field] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
