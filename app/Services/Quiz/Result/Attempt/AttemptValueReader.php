<?php

declare(strict_types=1);

namespace App\Services\Quiz\Result\Attempt;

final class AttemptValueReader
{
    public static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : "";
    }

    public static function nonNegativeInt(mixed $value, int $fallback = 0): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            return max(0, (int) $value);
        }

        return max(0, $fallback);
    }
}
