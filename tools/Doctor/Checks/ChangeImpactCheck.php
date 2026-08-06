<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class ChangeImpactCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $items = DoctorContext::snapshot()->metric('change-impact');

        if (!is_array($items) || $items === []) {
            return new CheckResult(
                title: 'Change Impact',
                status: 'PASS',
                summary: 'No dependency information.'
            );
        }

        usort(
            $items,
            fn(array $a, array $b) =>
                ($b['count'] ?? 0) <=> ($a['count'] ?? 0)
        );

        $worst = $items[0];

        $details = [];

        foreach (
            array_slice($worst['affected'] ?? [], 0, 10)
            as $file
        ) {
            $details[] = basename($file);
        }

        return new CheckResult(
            title: 'Change Impact',
            status: ($worst['count'] ?? 0) >= 8
                ? 'WARNING'
                : 'PASS',
            summary: sprintf(
                '%s affects %d file(s)',
                basename($worst['target']),
                $worst['count']
            ),
            details: $details,
            recommendations: ($worst['count'] ?? 0) >= 8
                ? ['Review all affected files before refactoring.']
                : []
        );
    }

    public function category(): string
    {
        return 'Architecture';
    }

    public function priority(): int
    {
        return 22;
    }
}
