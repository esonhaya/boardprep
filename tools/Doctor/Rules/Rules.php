<?php

declare(strict_types=1);

namespace Tools\Doctor\Rules;

final class Rules
{
    public static function controllerMaxLines(): int
    {
        return 300;
    }

    public static function serviceMaxLines(): int
    {
        return 250;
    }

    public static function methodMaxLines(): int
    {
        return 60;
    }

    public static function controllerMaxMethods(): int
    {
        return 12;
    }

    public static function cyclomaticComplexity(): int
    {
        return 10;
    }

    public static function domainMigrationTarget(): int
    {
        return 100;
    }

    public static function projectHealthWarning(): int
    {
        return 85;
    }
}
