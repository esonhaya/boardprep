<?php

declare(strict_types=1);

namespace Tools\Doctor\Metrics;

use Tools\Doctor\Rules\Rules;
use Tools\Doctor\Snapshot\ProjectSnapshot;

final class MethodMetricsAnalyzer
{
    public function analyze(
        ProjectSnapshot $snapshot
    ): void {
        $methods = $snapshot->methods;

        usort(
            $methods,
            static fn(array $a, array $b): int =>
                ($b['lines'] ?? 0) <=> ($a['lines'] ?? 0)
        );

        $largeMethods = array_values(
            array_filter(
                $methods,
                static fn(array $method): bool =>
                    ($method['lines'] ?? 0) > Rules::methodMaxLines()
            )
        );

        $largeServices = array_values(
            array_filter(
                $largeMethods,
                static fn(array $method): bool =>
                    self::isServiceFile($method['file'] ?? '')
            )
        );

        $snapshot->setMetric(
            'largest-method',
            $largeMethods
        );

        $snapshot->setMetric(
            'large-service-method',
            $largeServices
        );
    }

    private static function isServiceFile(
        string $file
    ): bool {
        return str_contains(
            str_replace('\\', '/', $file),
            '/Services/'
        );
    }
}
