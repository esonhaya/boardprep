<?php

declare(strict_types=1);

namespace Tools\Doctor\History;

final class TrendAnalyzer
{
    public static function compare(
        array $previous,
        array $current
    ): array {

        if ($previous === []) {
            return [];
        }

        $trend = [];

        foreach ($current as $key => $value) {

            if (
                $key === "timestamp"
                || !isset($previous[$key])
                || !is_numeric($value)
                || !is_numeric($previous[$key])
            ) {
                continue;
            }

            $trend[$key] =
                $value - $previous[$key];

        }

        return $trend;

    }
}
