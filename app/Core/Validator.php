<?php

class Validator
{
    public static function required($value): bool
    {
        return !empty($value);
    }

    public static function integer($value): bool
    {
        return filter_var(
            $value,
            FILTER_VALIDATE_INT
        ) !== false;
    }

    public static function min(
        int $value,
        int $minimum
    ): bool
    {
        return $value >= $minimum;
    }

    public static function max(
        int $value,
        int $maximum
    ): bool
    {
        return $value <= $maximum;
    }
}
