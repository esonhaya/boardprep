<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators\Content;

final class ContentLength
{
    public static function lessThan(string $text, int $threshold): bool
    {
        return function_exists('mb_strlen')
            ? mb_strlen($text) < $threshold
            : strlen($text) < $threshold;
    }
}
