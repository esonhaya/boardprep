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
        $stats = DoctorContext::snapshot()->metric('graph-statistics');

        if (!is_array($stats) || $stats === []) {
            return new CheckResult(
                title: 'Dependency Coupling',
                status: 'PASS',
                summary: 'No dependency information.'
            );
        }

        uasort(
            $stats,
            fn(array $a, array $b) =>
                ($b['total'] ?? 0) <=> ($a['total'] ?? 0)
        );

        $details = [];
        $highest = null;

        foreach ($stats as $file => $row) {

            $highest ??= [$file, $row];

            $details[] = sprintf(
                '%2d  %s',
                $row['total'],
                $file
            );

            if (count($details) === 5) {
                break;
            }
        }

        return new CheckResult(
            title: 'Dependency Coupling',
            status: (($highest[1]['total'] ?? 0) >= 10)
                ? 'WARNING'
                : 'PASS',
            summary: sprintf(
                '%s (%d dependencies)',
                $highest ? $highest[0] : 'N/A',
                $highest ? $highest[1]['total'] : 0
            ),
            details: $details
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
