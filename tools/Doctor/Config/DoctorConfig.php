<?php

declare(strict_types=1);

namespace Tools\Doctor\Config;

final class DoctorConfig
{
    public static function values(): array
    {
        return [

            "controller.max_lines" => 300,
            "service.max_lines" => 250,
            "method.max_lines" => 60,

            "controller.max_methods" => 12,

            "health.penalty.low" => 1,
            "health.penalty.medium" => 3,
            "health.penalty.high" => 6,
            "health.penalty.critical" => 15,

            "domain.target" => 100,

        ];
    }

    public static function get(
        string $key,
        mixed $default = null
    ): mixed {

        return self::values()[$key]
            ?? $default;

    }
}
