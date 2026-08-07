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
        $items =
            DoctorContext::snapshot()
                ->metric('change-impact');

        if (!is_array($items) || $items === []) {
            return new CheckResult(
                title: 'Change Impact',
                status: 'PASS',
                summary: 'No dependency information.'
            );
        }

        $items = array_values(
            array_filter(
                $items,
                fn(mixed $item): bool =>
                    is_array($item)
                    && $this->isApplicationFile(
                        (string) ($item['target'] ?? '')
                    )
            )
        );

        if ($items === []) {
            return new CheckResult(
                title: 'Change Impact',
                status: 'PASS',
                summary: 'No application change-impact data.'
            );
        }

        usort(
            $items,
            fn(array $a, array $b): int =>
                ($b['count'] ?? 0) <=> ($a['count'] ?? 0)
        );

        $worst = $items[0];

        $count = (int) ($worst['count'] ?? 0);

        $details = [];

        foreach (
            array_slice(
                $worst['affected'] ?? [],
                0,
                10
            ) as $file
        ) {
            if ($this->isApplicationFile((string) $file)) {
                $details[] = basename((string) $file);
            }
        }

        return new CheckResult(
            title: 'Change Impact',
            status:
                $count >= 15
                    ? 'WARNING'
                    : 'PASS',
            summary: sprintf(
                '%s affects %d application file(s)',
                basename((string) ($worst['target'] ?? 'N/A')),
                $count
            ),
            details: $details,
            recommendations:
                $count >= 15
                    ? [
                        'Review the affected application files before changing this component.',
                        'Consider reducing unnecessary consumers of highly shared components.',
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
        return 22;
    }

    private function isApplicationFile(string $file): bool
    {
        $normalized =
            str_replace('\\', '/', $file);

        return !(
            str_starts_with($normalized, './tools/Doctor/')
            || str_starts_with($normalized, 'tools/Doctor/')
            || str_starts_with($normalized, './tests/')
            || str_starts_with($normalized, 'tests/')
        );
    }
}
