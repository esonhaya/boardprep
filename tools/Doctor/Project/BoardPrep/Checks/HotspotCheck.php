<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class HotspotCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $rows = DoctorContext::snapshot()->metric('hotspots');

        if (!is_array($rows) || $rows === []) {
            return new CheckResult(
                title: 'Hotspots',
                status: 'PASS',
                summary: 'No hotspot information.'
            );
        }

        usort(
            $rows,
            fn(array $a, array $b) =>
                ($b['score'] ?? 0) <=> ($a['score'] ?? 0)
        );

        $details = [];

        foreach (array_slice($rows, 0, 10) as $row) {

            $details[] = sprintf(
                '%3d  %s',
                $row['score'],
                basename($row['file'])
            );

        }

        return new CheckResult(
            title: 'Hotspots',
            status: ($rows[0]['score'] ?? 0) >= 10
                ? 'WARNING'
                : 'PASS',
            summary: sprintf(
                '%s (%d)',
                basename($rows[0]['file']),
                $rows[0]['score']
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
        return 11;
    }
}
