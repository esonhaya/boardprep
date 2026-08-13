<?php

declare(strict_types=1);

namespace Tools\Tests;

use Tools\Doctor\Metrics\MethodMetricsAnalyzer;
use Tools\Doctor\Rules\Rules;
use Tools\Doctor\Snapshot\ProjectSnapshot;

final class MethodMetricsAnalyzerTest extends TestCase
{
    public function run(): void
    {
        $snapshot = new ProjectSnapshot(
            methods: [
                [
                    'file' => 'app/Services/LargeService.php',
                    'name' => 'largeService',
                    'visibility' => 'public',
                    'line' => 10,
                    'endLine' => 100,
                    'lines' => 91,
                ],
                [
                    'file' => 'app/Services/SmallService.php',
                    'name' => 'smallService',
                    'visibility' => 'public',
                    'line' => 10,
                    'endLine' => 40,
                    'lines' => 31,
                ],
                [
                    'file' => 'app/Controllers/LargeController.php',
                    'name' => 'largeController',
                    'visibility' => 'public',
                    'line' => 10,
                    'endLine' => 100,
                    'lines' => 91,
                ],
                [
                    'file' => 'app/Services/Exactly60.php',
                    'name' => 'exactly60',
                    'visibility' => 'public',
                    'line' => 10,
                    'endLine' => 69,
                    'lines' => 60,
                ],
            ]
        );

        (new MethodMetricsAnalyzer())
            ->analyze($snapshot);

        $largeMethods =
            $snapshot->metric('largest-method');

        $largeServiceMethods =
            $snapshot->metric('large-service-method');

        $this->assertSame(
            2,
            count($largeMethods),
            'Only methods above the configured method threshold should be reported.'
        );

        $this->assertSame(
            'largeService',
            $largeMethods[0]['name'] ?? null,
            'Largest method should be ordered first.'
        );

        $this->assertSame(
            1,
            count($largeServiceMethods),
            'Only large methods inside Services should be service findings.'
        );

        $this->assertSame(
            'largeService',
            $largeServiceMethods[0]['name'] ?? null,
            'The large service method should be reported.'
        );
    }
}
