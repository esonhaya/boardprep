<?php

declare(strict_types=1);

namespace App\Services\Question\Statistics;

final class QuestionStatisticsCounter
{
    public static function read(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            return max(0, (int) $value);
        }

        return 0;
    }
}
