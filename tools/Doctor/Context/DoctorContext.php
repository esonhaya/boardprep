<?php

declare(strict_types=1);

namespace Tools\Doctor\Context;

use Tools\Doctor\Metrics\MetricRegistry;
use Tools\Doctor\Snapshot\ProjectSnapshot;

final class DoctorContext
{
    private static ?ProjectSnapshot $snapshot = null;

    public static function setSnapshot(ProjectSnapshot $snapshot): void
    {
        self::$snapshot = $snapshot;
    }

    public static function snapshot(): ProjectSnapshot
    {
        if (self::$snapshot === null) {
            self::$snapshot = new ProjectSnapshot();
        }

        return self::$snapshot;
    }

    public static function metric(string $key, mixed $default = []): mixed
    {
        return MetricRegistry::get($key, $default);
    }

    public static function setMetric(string $key, mixed $value): void
    {
        MetricRegistry::set($key, $value);
    }

    public static function addMetric(string $key, mixed $value): void
    {
        MetricRegistry::add($key, $value);
    }
}
