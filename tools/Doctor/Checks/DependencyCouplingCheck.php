<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class DependencyCouplingCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $stats =
            DoctorContext::snapshot()
                ->metric('graph-statistics');

        if (!is_array($stats) || $stats === []) {
            return new CheckResult(
                title: 'Dependency Coupling',
                status: 'PASS',
                summary: 'No dependency information.'
            );
        }

        $stats = array_filter(
            $stats,
            function (mixed $row, string|int $file): bool {
                $file = str_replace('\\', '/', (string) $file);

                return !(
                    str_starts_with($file, './tools/Doctor/')
                    || str_starts_with($file, 'tools/Doctor/')
                    || str_starts_with($file, './tests/')
                    || str_starts_with($file, 'tests/')
                );
            },
            ARRAY_FILTER_USE_BOTH
        );

        uasort(
            $stats,
            fn(array $a, array $b): int =>
                ($b['total'] ?? 0) <=> ($a['total'] ?? 0)
        );

        $details = [];
        $highest = null;

        foreach ($stats as $file => $row) {
            $highest ??= [$file, $row];

            $details[] = sprintf(
                '%2d  %s',
                (int) ($row['total'] ?? 0),
                $file
            );

            if (count($details) === 5) {
                break;
            }
        }

        $highestCount =
            (int) ($highest[1]['total'] ?? 0);

        return new CheckResult(
            title: 'Dependency Coupling',
            status:
                $highestCount >= 15
                    ? 'WARNING'
                    : 'PASS',
            summary: sprintf(
                '%s (%d dependencies)',
                $highest
                    ? $highest[0]
                    : 'N/A',
                $highest
                    ? $highestCount
                    : 0
            ),
            details: $details,
            recommendations:
                $highestCount >= 15
                    ? [
                        'Review the highest-coupled application component.',
                        'Prefer narrower collaborators when a component accumulates many dependencies.',
                    ]
                    : []
        );
    }

    public function category(): string
    {
        return 'Architecture';
    }

    public function priority(): int
    {
        return 24;
    }
}
